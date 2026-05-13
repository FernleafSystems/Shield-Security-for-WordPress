<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @phpstan-type RuntimeManifest array{
 *   schema_version:int,
 *   generated_at_unix:int,
 *   files:array<string,array{sha256:string,size:int}>
 * }
 * @phpstan-type RuntimeState array{
 *   manifest_exists:bool,
 *   sentinels:array<string,bool>,
 *   all_required_sentinels_present:bool,
 *   has_any_required_sentinel:bool
 * }
 * @phpstan-type RefreshPlan array{
 *   mode:'seed'|'patch'|'skip',
 *   archive_paths:list<string>,
 *   deleted_paths:list<string>,
 *   host_manifest:RuntimeManifest
 * }
 * @phpstan-type WorkspacePaths array{
 *   workspace:string,
 *   archive_path:string,
 *   list_path:string,
 *   delete_list_path:string,
 *   manifest_path:string
 * }
 */
class LocalSiteRuntimeRefresher {

	private const STATE_SCHEMA_VERSION = LocalSiteRuntimeHostManifestProvider::STATE_SCHEMA_VERSION;
	private const PLUGIN_ROOT = '/var/www/html/wp-content/plugins/wp-simple-firewall';
	private const MANIFEST_FILE = '.shield-browser-runtime-manifest.json';
	private const REQUIRED_SENTINELS = [
		'icwp-wpsf.php',
		'plugin.json',
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

	private LocalSiteRuntimeHostManifestProvider $hostManifestProvider;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?LocalSiteRuntimeHostManifestProvider $hostManifestProvider = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->hostManifestProvider = $hostManifestProvider ?? new LocalSiteRuntimeHostManifestProvider();
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

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @param RuntimeManifest|null $hostManifest
	 */
	public function refresh(
		string $rootDir,
		string $containerId,
		?callable $onOutput = null,
		?array $hostManifest = null
	) :void {
		$this->writeProgress( "Refreshing local browser plugin runtime", $onOutput );
		$hostManifest = $this->resolveHostManifest( $rootDir, $hostManifest, $onOutput );
		$refreshPlan = $this->buildRefreshPlan( $rootDir, $containerId, $hostManifest, $onOutput );
		if ( $refreshPlan[ 'mode' ] === 'skip' ) {
			return;
		}

		$this->applyRefreshPlan(
			$rootDir,
			$containerId,
			$refreshPlan,
			$this->workspacePaths( $rootDir, $containerId ),
			$onOutput
		);
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
	 * @return RuntimeState
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

		/** @var RuntimeState $decoded */
		return $decoded;
	}

	/**
	 * @return RuntimeManifest
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

		/** @var RuntimeManifest $decoded */
		return $decoded;
	}

	/**
	 * @param RuntimeManifest $hostManifest
	 * @param RuntimeManifest $deployedManifest
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
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @return RuntimeManifest
	 */
	private function resolveHostManifest( string $rootDir, ?array $hostManifest, ?callable $onOutput = null ) :array {
		if ( $hostManifest === null ) {
			return $this->runPhase(
				'scan',
				fn() => $this->hostManifestProvider->manifest(
					$rootDir,
					LocalSiteRuntimeHostManifestProvider::MODE_FULL,
					$onOutput
				)
			);
		}
		if ( ( $hostManifest[ 'schema_version' ] ?? null ) !== self::STATE_SCHEMA_VERSION
			|| !\is_array( $hostManifest[ 'files' ] ?? null )
		) {
			throw new \RuntimeException( 'Supplied local browser runtime host manifest is invalid.' );
		}
		$this->writeProgress(
			'Runtime refresh scan: using shared host manifest with '.\count( $hostManifest[ 'files' ] ).' managed files',
			$onOutput
		);

		return $hostManifest;
	}

	/**
	 * @param RuntimeManifest $hostManifest
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @return RefreshPlan
	 */
	private function buildRefreshPlan(
		string $rootDir,
		string $containerId,
		array $hostManifest,
		?callable $onOutput = null
	) :array {
		$state = $this->readContainerState( $rootDir, $containerId );

		if ( !$state[ 'manifest_exists' ] && !$state[ 'has_any_required_sentinel' ] ) {
			return [
				'mode'         => 'seed',
				'archive_paths'=> \array_keys( $hostManifest[ 'files' ] ),
				'deleted_paths'=> [],
				'host_manifest'=> $hostManifest,
			];
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
		$this->writeProgress(
			'Runtime refresh diff: '
			.\count( $diff[ 'changed_or_new' ] )
			.' changed/new, '
			.\count( $diff[ 'deleted' ] )
			.' deleted in '
			.$this->formatDuration( \microtime( true ) - $diffStartedAt ),
			$onOutput
		);

		if ( $diff[ 'changed_or_new' ] === [] && $diff[ 'deleted' ] === [] ) {
			$this->writeProgress( 'Runtime refresh mode: skip', $onOutput );
			$this->writeProgress( 'Runtime refresh: up to date', $onOutput );
			return [
				'mode'         => 'skip',
				'archive_paths'=> [],
				'deleted_paths'=> [],
				'host_manifest'=> $hostManifest,
			];
		}

		return [
			'mode'         => 'patch',
			'archive_paths'=> $diff[ 'changed_or_new' ],
			'deleted_paths'=> $diff[ 'deleted' ],
			'host_manifest'=> $hostManifest,
		];
	}

	/**
	 * @return WorkspacePaths
	 */
	private function workspacePaths( string $rootDir, string $containerId ) :array {
		$workspace = Path::join( $rootDir, self::TEMP_DIR, \substr( \sha1( $containerId ), 0, 12 ) );
		if ( !\is_dir( $workspace ) && !\mkdir( $workspace, 0777, true ) && !\is_dir( $workspace ) ) {
			throw new \RuntimeException( 'Failed to create local browser runtime refresh workspace: '.$workspace );
		}

		return [
			'workspace'        => $workspace,
			'archive_path'     => Path::join( $workspace, self::ARCHIVE_FILE ),
			'list_path'        => Path::join( $workspace, self::FILE_LIST_FILE ),
			'delete_list_path' => Path::join( $workspace, self::DELETE_LIST_FILE ),
			'manifest_path'    => Path::join( $workspace, self::MANIFEST_EXPORT_FILE ),
		];
	}

	/**
	 * @param RefreshPlan $refreshPlan
	 * @param WorkspacePaths $workspacePaths
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function applyRefreshPlan(
		string $rootDir,
		string $containerId,
		array $refreshPlan,
		array $workspacePaths,
		?callable $onOutput = null
	) :void {
		$this->writeProgress( 'Runtime refresh mode: '.$refreshPlan[ 'mode' ], $onOutput );
		if ( $refreshPlan[ 'mode' ] === 'patch' ) {
			$this->writeProgress( 'Changed/new managed files: '.\count( $refreshPlan[ 'archive_paths' ] ), $onOutput );
			$this->writeProgress( 'Deleted managed files: '.\count( $refreshPlan[ 'deleted_paths' ] ), $onOutput );
		}

		$copyDuration = 0.0;

		if ( $refreshPlan[ 'archive_paths' ] !== [] ) {
			$copyDuration += $this->buildAndCopyArchive(
				$rootDir,
				$containerId,
				$refreshPlan[ 'archive_paths' ],
				$workspacePaths,
				$onOutput
			);
		}

		if ( $refreshPlan[ 'deleted_paths' ] !== [] ) {
			$copyDuration += $this->prepareAndCopyDeleteList(
				$rootDir,
				$containerId,
				$refreshPlan[ 'deleted_paths' ],
				$workspacePaths,
				$onOutput
			);
			$this->deletePathsWithProgress( $rootDir, $containerId, \count( $refreshPlan[ 'deleted_paths' ] ), $onOutput );
		}

		if ( $refreshPlan[ 'archive_paths' ] !== [] ) {
			$this->extractArchiveWithProgress( $rootDir, $containerId, $onOutput );
		}

		$this->verifyRequiredSentinelsWithProgress( $rootDir, $containerId, $onOutput );
		$copyDuration += $this->writeDeployedManifest(
			$rootDir,
			$containerId,
			$refreshPlan[ 'host_manifest' ],
			$workspacePaths,
			$onOutput
		);
		if ( $copyDuration > 0 ) {
			$this->writeProgress( 'Runtime refresh copy total: '.$this->formatDuration( $copyDuration ), $onOutput );
		}
	}

	/**
	 * @param list<string> $archivePaths
	 * @param WorkspacePaths $workspacePaths
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function buildAndCopyArchive(
		string $rootDir,
		string $containerId,
		array $archivePaths,
		array $workspacePaths,
		?callable $onOutput = null
	) :float {
		$buildStartedAt = \microtime( true );
		$this->runPhase( 'build', function () use ( $workspacePaths, $archivePaths, $rootDir, $onOutput ) :void {
			if ( \file_put_contents( $workspacePaths[ 'list_path' ], \implode( "\n", $archivePaths )."\n" ) === false ) {
				throw new \RuntimeException( 'Failed to write local browser runtime archive file list: '.$workspacePaths[ 'list_path' ] );
			}
			$this->processRunner->runOrThrow(
				[
					'tar',
					'-cf',
					Path::makeRelative( $workspacePaths[ 'archive_path' ], $rootDir ),
					'-T',
					Path::makeRelative( $workspacePaths[ 'list_path' ], $rootDir ),
				],
				$rootDir,
				$onOutput
			);
		} );
		$buildDuration = \microtime( true ) - $buildStartedAt;
		$archiveBytes = $this->runPhase( 'build output', function () use ( $workspacePaths ) :int {
			if ( !\is_file( $workspacePaths[ 'archive_path' ] ) ) {
				throw new \RuntimeException( 'Runtime archive was not created: '.$workspacePaths[ 'archive_path' ] );
			}
			$size = \filesize( $workspacePaths[ 'archive_path' ] );
			if ( $size === false ) {
				throw new \RuntimeException( 'Failed to read runtime archive size: '.$workspacePaths[ 'archive_path' ] );
			}
			return (int)$size;
		} );
		$this->writeProgress(
			'Runtime refresh build: '
			.\count( $archivePaths )
			.' files, '
			.$this->formatBytes( $archiveBytes )
			.' in '
			.$this->formatDuration( $buildDuration ),
			$onOutput
		);

		$copyStartedAt = \microtime( true );
		$this->runPhase(
			'copy archive',
			fn() => $this->copyFileToContainer( $rootDir, $containerId, $workspacePaths[ 'archive_path' ], self::CONTAINER_ARCHIVE_PATH, $onOutput )
		);

		return \microtime( true ) - $copyStartedAt;
	}

	/**
	 * @param list<string> $deletedPaths
	 * @param WorkspacePaths $workspacePaths
	 */
	private function prepareAndCopyDeleteList(
		string $rootDir,
		string $containerId,
		array $deletedPaths,
		array $workspacePaths,
		?callable $onOutput = null
	) :float {
		$this->runPhase(
			'prepare delete list',
			function () use ( $deletedPaths, $workspacePaths ) :void {
				$this->assertManagedPathsAreSafe( $deletedPaths );
				$json = \json_encode( \array_values( $deletedPaths ), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );
				if ( !\is_string( $json ) ) {
					throw new \RuntimeException( 'Failed to encode local browser runtime delete list.' );
				}
				if ( \file_put_contents( $workspacePaths[ 'delete_list_path' ], $json."\n" ) === false ) {
					throw new \RuntimeException( 'Failed to write local browser runtime delete list: '.$workspacePaths[ 'delete_list_path' ] );
				}
			}
		);

		$copyStartedAt = \microtime( true );
		$this->runPhase(
			'copy delete list',
			fn() => $this->copyFileToContainer( $rootDir, $containerId, $workspacePaths[ 'delete_list_path' ], self::CONTAINER_DELETE_LIST_PATH, $onOutput )
		);

		return \microtime( true ) - $copyStartedAt;
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function deletePathsWithProgress(
		string $rootDir,
		string $containerId,
		int $deletedPathCount,
		?callable $onOutput = null
	) :void {
		$deleteStartedAt = \microtime( true );
		$this->runPhase(
			'delete',
			fn() => $this->deleteManagedPaths( $rootDir, $containerId, $onOutput )
		);
		$this->writeProgress(
			'Runtime refresh delete: '
			.$deletedPathCount
			.' paths in '
			.$this->formatDuration( \microtime( true ) - $deleteStartedAt ),
			$onOutput
		);
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function extractArchiveWithProgress( string $rootDir, string $containerId, ?callable $onOutput = null ) :void {
		$extractStartedAt = \microtime( true );
		$this->runPhase( 'extract', function () use ( $containerId, $rootDir, $onOutput ) :void {
			$this->processRunner->runOrThrow(
				[
					'docker',
					'exec',
					$containerId,
					'tar',
					'--overwrite',
					'-xf',
					self::CONTAINER_ARCHIVE_PATH,
					'-C',
					self::PLUGIN_ROOT,
				],
				$rootDir,
				$onOutput
			);
		} );
		$this->writeProgress(
			'Runtime refresh extract: '
			.$this->formatDuration( \microtime( true ) - $extractStartedAt ),
			$onOutput
		);
	}

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function verifyRequiredSentinelsWithProgress( string $rootDir, string $containerId, ?callable $onOutput = null ) :void {
		$verifyStartedAt = \microtime( true );
		$this->runPhase(
			'verify',
			fn() => $this->verifyRequiredSentinels( $rootDir, $containerId )
		);
		$this->writeProgress(
			'Runtime refresh verify: '.$this->formatDuration( \microtime( true ) - $verifyStartedAt ),
			$onOutput
		);
	}

	/**
	 * @param RuntimeManifest $hostManifest
	 * @param WorkspacePaths $workspacePaths
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function writeDeployedManifest(
		string $rootDir,
		string $containerId,
		array $hostManifest,
		array $workspacePaths,
		?callable $onOutput = null
	) :float {
		$copyDuration = 0.0;
		$manifestStartedAt = \microtime( true );
		$this->runPhase( 'manifest write', function () use ( $containerId, $hostManifest, $workspacePaths, $rootDir, $onOutput, &$copyDuration ) :void {
			$manifestJson = \json_encode( $hostManifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );
			if ( !\is_string( $manifestJson ) ) {
				throw new \RuntimeException( 'Failed to encode local browser runtime manifest.' );
			}
			if ( \file_put_contents( $workspacePaths[ 'manifest_path' ], $manifestJson."\n" ) === false ) {
				throw new \RuntimeException( 'Failed to write local browser runtime manifest export: '.$workspacePaths[ 'manifest_path' ] );
			}
			$copyStartedAt = \microtime( true );
			$this->copyFileToContainer( $rootDir, $containerId, $workspacePaths[ 'manifest_path' ], self::CONTAINER_MANIFEST_PATH, $onOutput );
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
				$rootDir,
				$onOutput
			);
		} );
		$this->writeProgress(
			'Runtime refresh manifest write: '.$this->formatDuration( \microtime( true ) - $manifestStartedAt ),
			$onOutput
		);

		return $copyDuration;
	}

	private function copyFileToContainer(
		string $rootDir,
		string $containerId,
		string $sourcePath,
		string $targetPath,
		?callable $onOutput = null
	) :void {
		$this->processRunner->runOrThrow(
			[
				'docker',
				'cp',
				Path::makeRelative( $sourcePath, $rootDir ),
				$containerId.':'.$targetPath,
			],
			$rootDir,
			$onOutput
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

	private function deleteManagedPaths( string $rootDir, string $containerId, ?callable $onOutput = null ) :void {
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
			$rootDir,
			$onOutput
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
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 */
	private function writeProgress( string $message, ?callable $onOutput = null ) :void {
		if ( $onOutput !== null ) {
			$onOutput( Process::OUT, $message.\PHP_EOL );
			return;
		}

		echo $message.\PHP_EOL;
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
