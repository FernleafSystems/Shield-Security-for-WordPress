<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class LocalDevSiteRuntimeRefresher {

	private const STATE_SCHEMA_VERSION = 1;
	private const PLUGIN_ROOT = '/var/www/html/wp-content/plugins/wp-simple-firewall';
	private const MANIFEST_FILE = '.shield-browser-runtime-manifest.json';
	private const REQUIRED_SENTINELS = [
		'icwp-wpsf.php',
		'plugin.json',
		'plugin_autoload.php',
	];
	// The local browser lane intentionally mirrors source runtime only.
	// Packaged-only vendor_prefixed coverage belongs to packaged lanes.
	private const MANAGED_ROOTS = [
		'icwp-wpsf.php',
		'plugin.json',
		'plugin_autoload.php',
		'plugin_compatibility.php',
		'plugin_init.php',
		'uninstall.php',
		'unsupported.php',
		'src',
		'templates',
		'languages',
		'vendor',
		'assets/dist',
		'assets/images',
		'flags',
	];
	private const TEMP_DIR = 'tmp/.browser-runtime-refresh';
	private const ARCHIVE_FILE = 'runtime-refresh.tar';
	private const FILE_LIST_FILE = 'runtime-files.txt';
	private const DELETE_LIST_FILE = 'deleted-managed-paths.json';
	private const MANIFEST_EXPORT_FILE = 'runtime-manifest.json';
	private const CONTAINER_ARCHIVE_PATH = '/tmp/shield-browser-runtime-refresh.tar';
	private const CONTAINER_DELETE_LIST_PATH = '/tmp/shield-browser-runtime-deletes.json';
	private const CONTAINER_MANIFEST_PATH = '/tmp/shield-browser-runtime-manifest.json';

	private ProcessRunner $processRunner;

	public function __construct( ?ProcessRunner $processRunner = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	/**
	 * @param string[] $composeFiles
	 * @param array<string,string|false> $envOverrides
	 */
	public function resolveServiceContainerId(
		string $rootDir,
		array $composeFiles,
		string $serviceName,
		array $envOverrides
	) :string {
		$process = $this->processRunner->run(
			$this->buildComposeCommand( $composeFiles, [ 'ps', '-q', $serviceName ] ),
			$rootDir,
			static function () :void {
			},
			$envOverrides
		);

		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$error = \trim( $process->getErrorOutput() );
			throw new \RuntimeException(
				'Failed to resolve Docker container for service '.$serviceName
				.( $error !== '' ? ': '.$error : '.' )
			);
		}

		return \trim( $process->getOutput() );
	}

	public function refresh( string $rootDir, string $containerId ) :void {
		echo "Refreshing local browser plugin runtime".\PHP_EOL;

		$scanStartedAt = \microtime( true );
		$hostManifest = $this->runPhase(
			'scan',
			fn() => $this->buildHostManifest( $rootDir )
		);
		echo 'Runtime refresh scan: '
			 .\count( $hostManifest[ 'files' ] )
			 .' managed files in '
			 .$this->formatDuration( \microtime( true ) - $scanStartedAt )
			 .\PHP_EOL;

		$state = $this->readContainerState( $rootDir, $containerId );

		if ( !$state[ 'manifest_exists' ] && !$state[ 'has_any_required_sentinel' ] ) {
			$this->runSeedRefresh( $rootDir, $containerId, $hostManifest );
			return;
		}

		if ( !$state[ 'manifest_exists' ] || !$state[ 'all_required_sentinels_present' ] ) {
			throw new \RuntimeException(
				'Local browser plugin runtime is inconsistent: '
				.'manifest_exists='.( $state[ 'manifest_exists' ] ? 'yes' : 'no' )
				.', required_sentinels='.\implode(
					',',
					\array_map(
						static fn( string $path, bool $exists ) :string => $path.'='.( $exists ? 'yes' : 'no' ),
						\array_keys( $state[ 'sentinels' ] ),
						\array_values( $state[ 'sentinels' ] )
					)
				)
			);
		}

		$deployedManifest = $this->runPhase(
			'read deployed manifest',
			fn() => $this->readDeployedManifest( $rootDir, $containerId )
		);
		$diffStartedAt = \microtime( true );
		$diff = $this->runPhase(
			'diff',
			fn() => $this->computeDiff( $hostManifest, $deployedManifest )
		);
		echo 'Runtime refresh diff: '
			 .\count( $diff[ 'changed_or_new' ] )
			 .' changed/new, '
			 .\count( $diff[ 'deleted' ] )
			 .' deleted in '
			 .$this->formatDuration( \microtime( true ) - $diffStartedAt )
			 .\PHP_EOL;

		if ( empty( $diff[ 'changed_or_new' ] ) && empty( $diff[ 'deleted' ] ) ) {
			echo "Runtime refresh mode: skip".\PHP_EOL;
			echo "Runtime refresh: up to date".\PHP_EOL;
			return;
		}

		$this->runPatchRefresh( $rootDir, $containerId, $hostManifest, $diff[ 'changed_or_new' ], $diff[ 'deleted' ] );
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @return string[]
	 */
	private function buildComposeCommand( array $composeFiles, array $subCommand ) :array {
		$command = [ 'docker', 'compose' ];
		foreach ( $composeFiles as $composeFile ) {
			$command[] = '-f';
			$command[] = $composeFile;
		}
		return \array_merge( $command, $subCommand );
	}

	/**
	 * @return array{
	 *   schema_version:int,
	 *   generated_at_unix:int,
	 *   files:array<string,array{sha256:string,size:int}>
	 * }
	 */
	private function buildHostManifest( string $rootDir ) :array {
		$files = [];
		foreach ( self::MANAGED_ROOTS as $relativePath ) {
			$absolutePath = Path::join( $rootDir, $relativePath );
			if ( !\file_exists( $absolutePath ) ) {
				continue;
			}

			if ( \is_file( $absolutePath ) ) {
				$files[ $this->normalizeRelativePath( $relativePath ) ] = $this->describeFile( $absolutePath );
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $absolutePath, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $fileInfo ) {
				if ( !$fileInfo->isFile() ) {
					continue;
				}

				$relativeFilePath = $this->normalizeRelativePath(
					Path::makeRelative( $fileInfo->getPathname(), $rootDir )
				);
				$files[ $relativeFilePath ] = $this->describeFile( $fileInfo->getPathname() );
			}
		}

		\ksort( $files );
		if ( empty( $files ) ) {
			throw new \RuntimeException( 'Local browser runtime manifest is empty; no managed runtime files were found.' );
		}

		return [
			'schema_version' => self::STATE_SCHEMA_VERSION,
			'generated_at_unix' => \time(),
			'files' => $files,
		];
	}

	/**
	 * @return array{manifest_exists:bool,sentinels:array<string,bool>,all_required_sentinels_present:bool,has_any_required_sentinel:bool}
	 */
	private function readContainerState( string $rootDir, string $containerId ) :array {
		$script = <<<'PHP'
$pluginRoot = getenv('SHIELD_PLUGIN_ROOT');
$manifestPath = $pluginRoot.'/'.getenv('SHIELD_RUNTIME_MANIFEST');
$sentinels = array_filter(explode("\n", (string)getenv('SHIELD_REQUIRED_SENTINELS')));
$sentinelStates = [];
$allPresent = true;
$anyPresent = false;
foreach ( $sentinels as $sentinel ) {
	$present = is_file($pluginRoot.'/'.$sentinel);
	$sentinelStates[ $sentinel ] = $present;
	$allPresent = $allPresent && $present;
	$anyPresent = $anyPresent || $present;
}
echo json_encode([
	'manifest_exists' => is_file($manifestPath),
	'sentinels' => $sentinelStates,
	'all_required_sentinels_present' => $allPresent,
	'has_any_required_sentinel' => $anyPresent,
], JSON_UNESCAPED_SLASHES);
PHP;
		$process = $this->processRunner->run(
			[
				'docker',
				'exec',
				'-e',
				'SHIELD_PLUGIN_ROOT='.self::PLUGIN_ROOT,
				'-e',
				'SHIELD_RUNTIME_MANIFEST='.self::MANIFEST_FILE,
				'-e',
				'SHIELD_REQUIRED_SENTINELS='.\implode( "\n", self::REQUIRED_SENTINELS ),
				$containerId,
				'php',
				'-r',
				$script,
			],
			$rootDir,
			static function () :void {
			}
		);

		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$error = \trim( $process->getErrorOutput() );
			throw new \RuntimeException(
				'Failed to inspect local browser plugin runtime state'
				.( $error !== '' ? ': '.$error : '.' )
			);
		}

		$decoded = \json_decode( \trim( $process->getOutput() ), true );
		if ( !\is_array( $decoded ) ) {
			throw new \RuntimeException( 'Failed to decode local browser plugin runtime state JSON.' );
		}

		/** @var array{manifest_exists:bool,sentinels:array<string,bool>,all_required_sentinels_present:bool,has_any_required_sentinel:bool} $decoded */
		return $decoded;
	}

	/**
	 * @return array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>}
	 */
	private function readDeployedManifest( string $rootDir, string $containerId ) :array {
		$process = $this->processRunner->run(
			[
				'docker',
				'exec',
				$containerId,
				'php',
				'-r',
				'echo file_get_contents('.\var_export( self::PLUGIN_ROOT.'/'.self::MANIFEST_FILE, true ).');',
			],
			$rootDir,
			static function () :void {
			}
		);

		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			$error = \trim( $process->getErrorOutput() );
			throw new \RuntimeException(
				'Failed to read deployed local browser runtime manifest'
				.( $error !== '' ? ': '.$error : '.' )
			);
		}

		$decoded = \json_decode( $process->getOutput(), true );
		if ( !\is_array( $decoded ) ) {
			throw new \RuntimeException( 'Deployed local browser runtime manifest is not valid JSON.' );
		}
		if ( ( $decoded[ 'schema_version' ] ?? null ) !== self::STATE_SCHEMA_VERSION ) {
			throw new \RuntimeException( 'Deployed local browser runtime manifest schema version is unsupported.' );
		}
		if ( !\is_array( $decoded[ 'files' ] ?? null ) ) {
			throw new \RuntimeException( 'Deployed local browser runtime manifest has no files map.' );
		}

		/** @var array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>} $decoded */
		return $decoded;
	}

	/**
	 * @param array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>} $hostManifest
	 * @param array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>} $deployedManifest
	 * @return array{changed_or_new:string[],deleted:string[]}
	 */
	private function computeDiff( array $hostManifest, array $deployedManifest ) :array {
		$changedOrNew = [];
		foreach ( $hostManifest[ 'files' ] as $relativePath => $hostInfo ) {
			$deployedInfo = $deployedManifest[ 'files' ][ $relativePath ] ?? null;
			if ( $deployedInfo === null
				|| ( $deployedInfo[ 'sha256' ] ?? '' ) !== $hostInfo[ 'sha256' ]
				|| (int)( $deployedInfo[ 'size' ] ?? -1 ) !== $hostInfo[ 'size' ]
			) {
				$changedOrNew[] = $relativePath;
			}
		}

		$deleted = [];
		foreach ( \array_keys( $deployedManifest[ 'files' ] ) as $relativePath ) {
			if ( !isset( $hostManifest[ 'files' ][ $relativePath ] ) ) {
				$deleted[] = $relativePath;
			}
		}

		\sort( $changedOrNew );
		\sort( $deleted );

		return [
			'changed_or_new' => $changedOrNew,
			'deleted' => $deleted,
		];
	}

	/**
	 * @param array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>} $hostManifest
	 */
	private function runSeedRefresh( string $rootDir, string $containerId, array $hostManifest ) :void {
		echo "Runtime refresh mode: seed".\PHP_EOL;
		$this->applyArchiveRefresh(
			$rootDir,
			$containerId,
			\array_keys( $hostManifest[ 'files' ] ),
			[],
			$hostManifest
		);
	}

	/**
	 * @param array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>} $hostManifest
	 * @param string[] $changedOrNew
	 * @param string[] $deleted
	 */
	private function runPatchRefresh(
		string $rootDir,
		string $containerId,
		array $hostManifest,
		array $changedOrNew,
		array $deleted
	) :void {
		echo "Runtime refresh mode: patch".\PHP_EOL;
		echo 'Changed/new managed files: '.\count( $changedOrNew ).\PHP_EOL;
		echo 'Deleted managed files: '.\count( $deleted ).\PHP_EOL;

		$this->applyArchiveRefresh( $rootDir, $containerId, $changedOrNew, $deleted, $hostManifest );
	}

	/**
	 * @param string[] $archivePaths
	 * @param string[] $deletedPaths
	 * @param array{schema_version:int,generated_at_unix:int,files:array<string,array{sha256:string,size:int}>} $hostManifest
	 */
	private function applyArchiveRefresh(
		string $rootDir,
		string $containerId,
		array $archivePaths,
		array $deletedPaths,
		array $hostManifest
	) :void {
		$workspace = Path::join( $rootDir, self::TEMP_DIR );
		if ( !\is_dir( $workspace ) && !\mkdir( $workspace, 0777, true ) && !\is_dir( $workspace ) ) {
			throw new \RuntimeException( 'Failed to create local browser runtime refresh workspace: '.$workspace );
		}

		$archivePath = Path::join( $workspace, self::ARCHIVE_FILE );
		$listPath = Path::join( $workspace, self::FILE_LIST_FILE );
		$deleteListPath = Path::join( $workspace, self::DELETE_LIST_FILE );
		$manifestPath = Path::join( $workspace, self::MANIFEST_EXPORT_FILE );
		$copyDuration = 0.0;

		if ( !empty( $archivePaths ) ) {
			$buildStartedAt = \microtime( true );
			$this->runPhase( 'build', function () use ( $archivePath, $archivePaths, $listPath, $rootDir ) :void {
				if ( \file_put_contents( $listPath, \implode( "\n", $archivePaths )."\n" ) === false ) {
					throw new \RuntimeException( 'Failed to write local browser runtime archive file list: '.$listPath );
				}
				$this->processRunner->runOrThrow(
					[
						'tar',
						'-cf',
						Path::makeRelative( $archivePath, $rootDir ),
						'-T',
						Path::makeRelative( $listPath, $rootDir ),
					],
					$rootDir
				);
			} );
			$buildDuration = \microtime( true ) - $buildStartedAt;
			$archiveBytes = $this->runPhase( 'build output', function () use ( $archivePath ) :int {
				if ( !\is_file( $archivePath ) ) {
					throw new \RuntimeException( 'Runtime archive was not created: '.$archivePath );
				}
				$size = \filesize( $archivePath );
				if ( $size === false ) {
					throw new \RuntimeException( 'Failed to read runtime archive size: '.$archivePath );
				}
				return (int)$size;
			} );
			echo 'Runtime refresh build: '
				 .\count( $archivePaths )
				 .' files, '
				 .$this->formatBytes( $archiveBytes )
				 .' in '
				 .$this->formatDuration( $buildDuration )
				 .\PHP_EOL;

			$copyStartedAt = \microtime( true );
			$this->runPhase(
				'copy archive',
				fn() => $this->copyFileToContainer( $rootDir, $containerId, $archivePath, self::CONTAINER_ARCHIVE_PATH )
			);
			$copyDuration += \microtime( true ) - $copyStartedAt;
		}

		if ( !empty( $deletedPaths ) ) {
			$this->runPhase(
				'prepare delete list',
				function () use ( $deleteListPath, $deletedPaths ) :void {
					$this->assertManagedPathsAreSafe( $deletedPaths );
					$json = \json_encode( \array_values( $deletedPaths ), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );
					if ( !\is_string( $json ) ) {
						throw new \RuntimeException( 'Failed to encode local browser runtime delete list.' );
					}
					if ( \file_put_contents( $deleteListPath, $json."\n" ) === false ) {
						throw new \RuntimeException( 'Failed to write local browser runtime delete list: '.$deleteListPath );
					}
				}
			);
			$copyStartedAt = \microtime( true );
			$this->runPhase(
				'copy delete list',
				fn() => $this->copyFileToContainer( $rootDir, $containerId, $deleteListPath, self::CONTAINER_DELETE_LIST_PATH )
			);
			$copyDuration += \microtime( true ) - $copyStartedAt;

			$deleteStartedAt = \microtime( true );
			$this->runPhase(
				'delete',
				fn() => $this->deleteManagedPaths( $rootDir, $containerId )
			);
			echo 'Runtime refresh delete: '
				 .\count( $deletedPaths )
				 .' paths in '
				 .$this->formatDuration( \microtime( true ) - $deleteStartedAt )
				 .\PHP_EOL;
		}

		if ( !empty( $archivePaths ) ) {
			$extractStartedAt = \microtime( true );
			$this->runPhase( 'extract', function () use ( $containerId, $rootDir ) :void {
				$this->processRunner->runOrThrow(
					[
						'docker',
						'exec',
						$containerId,
						'tar',
						'-xf',
						self::CONTAINER_ARCHIVE_PATH,
						'-C',
						self::PLUGIN_ROOT,
					],
					$rootDir
				);
			} );
			echo 'Runtime refresh extract: '
				 .$this->formatDuration( \microtime( true ) - $extractStartedAt )
				 .\PHP_EOL;
		}

		$verifyStartedAt = \microtime( true );
		$this->runPhase(
			'verify',
			fn() => $this->verifyRequiredSentinels( $rootDir, $containerId )
		);
		echo 'Runtime refresh verify: '.$this->formatDuration( \microtime( true ) - $verifyStartedAt ).\PHP_EOL;

		$manifestStartedAt = \microtime( true );
		$this->runPhase( 'manifest write', function () use ( $containerId, $hostManifest, $manifestPath, $rootDir, &$copyDuration ) :void {
			$manifestJson = \json_encode( $hostManifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );
			if ( !\is_string( $manifestJson ) ) {
				throw new \RuntimeException( 'Failed to encode local browser runtime manifest.' );
			}
			if ( \file_put_contents( $manifestPath, $manifestJson."\n" ) === false ) {
				throw new \RuntimeException( 'Failed to write local browser runtime manifest export: '.$manifestPath );
			}
			$copyStartedAt = \microtime( true );
			$this->copyFileToContainer( $rootDir, $containerId, $manifestPath, self::CONTAINER_MANIFEST_PATH );
			$copyDuration += \microtime( true ) - $copyStartedAt;
			$this->processRunner->runOrThrow(
				[
					'docker',
					'exec',
					$containerId,
					'php',
					'-r',
					'if (!rename('.\var_export( self::CONTAINER_MANIFEST_PATH, true ).','. \var_export( self::PLUGIN_ROOT.'/'.self::MANIFEST_FILE, true ).')) { fwrite(STDERR, "manifest move failed\n"); exit(1); }',
				],
				$rootDir
			);
		} );
		echo 'Runtime refresh manifest write: '
			 .$this->formatDuration( \microtime( true ) - $manifestStartedAt )
			 .\PHP_EOL;
		if ( $copyDuration > 0 ) {
			echo 'Runtime refresh copy total: '.$this->formatDuration( $copyDuration ).\PHP_EOL;
		}
	}

	private function copyFileToContainer( string $rootDir, string $containerId, string $sourcePath, string $targetPath ) :void {
		$this->processRunner->runOrThrow(
			[
				'docker',
				'cp',
				Path::makeRelative( $sourcePath, $rootDir ),
				$containerId.':'.$targetPath,
			],
			$rootDir
		);
	}

	/**
	 * @param string[] $deletedPaths
	 */
	private function assertManagedPathsAreSafe( array $deletedPaths ) :void {
		foreach ( $deletedPaths as $relativePath ) {
			if ( $relativePath === ''
				|| \str_starts_with( $relativePath, '/' )
				|| \str_contains( $relativePath, '\\' )
				|| \str_contains( $relativePath, '..' )
			) {
				throw new \RuntimeException( 'Unsafe managed delete path detected: '.$relativePath );
			}
		}
	}

	private function deleteManagedPaths( string $rootDir, string $containerId ) :void {
		$script = <<<'PHP'
$pluginRoot = getenv('SHIELD_PLUGIN_ROOT');
$deleteFile = getenv('SHIELD_DELETE_LIST');
$decoded = json_decode((string)file_get_contents($deleteFile), true);
if ( !is_array($decoded) ) {
	fwrite(STDERR, "delete list invalid\n");
	exit(2);
}
function fail_delete(string $message): void {
	fwrite(STDERR, $message."\n");
	exit(4);
}
foreach ( $decoded as $relativePath ) {
	if ( !is_string($relativePath) || $relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/') || str_contains($relativePath, '\\') ) {
		fwrite(STDERR, "unsafe delete path\n");
		exit(3);
	}
	$targetPath = $pluginRoot.'/'.$relativePath;
	if ( !file_exists($targetPath) && !is_link($targetPath) ) {
		continue;
	}
	if ( is_dir($targetPath) && !is_link($targetPath) ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($targetPath, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && !$item->isLink() ) {
				if ( !rmdir($item->getPathname()) ) {
					fail_delete("failed to remove directory: ".$item->getPathname());
				}
			}
			else {
				if ( !unlink($item->getPathname()) ) {
					fail_delete("failed to remove file: ".$item->getPathname());
				}
			}
		}
		if ( !rmdir($targetPath) ) {
			fail_delete("failed to remove directory: ".$targetPath);
		}
	}
	else {
		if ( !unlink($targetPath) ) {
			fail_delete("failed to remove file: ".$targetPath);
		}
	}
}
PHP;
		$this->processRunner->runOrThrow(
			[
				'docker',
				'exec',
				'-e',
				'SHIELD_PLUGIN_ROOT='.self::PLUGIN_ROOT,
				'-e',
				'SHIELD_DELETE_LIST='.self::CONTAINER_DELETE_LIST_PATH,
				$containerId,
				'php',
				'-r',
				$script,
			],
			$rootDir
		);
	}

	private function verifyRequiredSentinels( string $rootDir, string $containerId ) :void {
		$script = <<<'PHP'
$pluginRoot = getenv('SHIELD_PLUGIN_ROOT');
$sentinels = array_filter(explode("\n", (string)getenv('SHIELD_REQUIRED_SENTINELS')));
$missing = [];
foreach ( $sentinels as $sentinel ) {
	if ( !is_file($pluginRoot.'/'.$sentinel) ) {
		$missing[] = $sentinel;
	}
}
if ( !empty($missing) ) {
	fwrite(STDERR, implode(",", $missing));
	exit(1);
}
PHP;
		$process = $this->processRunner->run(
			[
				'docker',
				'exec',
				'-e',
				'SHIELD_PLUGIN_ROOT='.self::PLUGIN_ROOT,
				'-e',
				'SHIELD_REQUIRED_SENTINELS='.\implode( "\n", self::REQUIRED_SENTINELS ),
				$containerId,
				'php',
				'-r',
				$script,
			],
			$rootDir,
			static function () :void {
			}
		);
		if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
			throw new \RuntimeException(
				'Local browser runtime verification failed. Missing required sentinels: '.\trim( $process->getErrorOutput() )
			);
		}
	}

	/**
	 * @return array{sha256:string,size:int}
	 */
	private function describeFile( string $filePath ) :array {
		$hash = \hash_file( 'sha256', $filePath );
		if ( !\is_string( $hash ) ) {
			throw new \RuntimeException( 'Failed to hash runtime file: '.$filePath );
		}

		return [
			'sha256' => $hash,
			'size' => (int)\filesize( $filePath ),
		];
	}

	private function normalizeRelativePath( string $path ) :string {
		return \str_replace( '\\', '/', Path::normalize( $path ) );
	}

	private function formatDuration( float $seconds ) :string {
		return \sprintf( '%.2fs', $seconds );
	}

	private function formatBytes( int $bytes ) :string {
		if ( $bytes >= 1024*1024 ) {
			return \sprintf( '%.2f MB', $bytes/( 1024*1024 ) );
		}
		if ( $bytes >= 1024 ) {
			return \sprintf( '%.2f KB', $bytes/1024 );
		}
		return $bytes.' B';
	}

	/**
	 * @template T
	 * @param callable():T $callback
	 * @return T
	 */
	private function runPhase( string $phase, callable $callback ) {
		try {
			return $callback();
		}
		catch ( \Throwable $e ) {
			throw new \RuntimeException(
				'Local browser runtime refresh phase '.$phase.' failed: '.$e->getMessage(),
				0,
				$e
			);
		}
	}
}
