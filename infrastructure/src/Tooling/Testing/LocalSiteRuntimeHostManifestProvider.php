<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * @phpstan-type RuntimeManifest array{
 *   schema_version:int,
 *   generated_at_unix:int,
 *   files:array<string,array{sha256:string,size:int}>
 * }
 * @phpstan-type RuntimeFileMetadata array{
 *   relative_path:string,
 *   absolute_path:string,
 *   size:int,
 *   mtime:int
 * }
 * @phpstan-type RuntimeManifestCache array{
 *   schema_version:int,
 *   managed_roots_hash:string,
 *   fingerprint:string,
 *   manifest:RuntimeManifest
 * }
 */
class LocalSiteRuntimeHostManifestProvider {

	public const MODE_AUTO = 'auto';
	public const MODE_FULL = 'full';
	public const STATE_SCHEMA_VERSION = 1;
	// Browser lanes intentionally mirror the source runtime only.
	// Packaged-only vendor_prefixed coverage belongs to packaged lanes.
	private const MANAGED_ROOTS = [
		'icwp-wpsf.php',
		'plugin.json',
		'plugin_compatibility.php',
		'plugin_init.php',
		'unsupported.php',
		'src',
		'templates',
		'languages',
		'vendor',
		'assets/dist',
		'assets/images',
		'flags',
		'tests/Helpers/RuntimeTestState.php',
		'tests/Helpers/TestDataFactory.php',
		'tests/Helpers/BrowserFixtureRegistry.php',
		'tests/Helpers/ActionRouter',
		'tests/Helpers/CrossSite',
		'tests/browser/support/shield-browser-fixtures.php',
	];
	private const CACHE_SCHEMA_VERSION = 1;
	private const CACHE_DIR = 'tmp/.browser-runtime-manifest-cache';
	private const CACHE_FILE = 'host-manifest-cache.json';

	/**
	 * @param callable|null $onOutput Receives (string $type, string $buffer)
	 * @return RuntimeManifest
	 */
	public function manifest( string $rootDir, string $mode = self::MODE_FULL, ?callable $onOutput = null ) :array {
		if ( $mode !== self::MODE_FULL && $mode !== self::MODE_AUTO ) {
			throw new \InvalidArgumentException( 'Runtime refresh mode must be "full" or "auto".' );
		}

		$scanStartedAt = \microtime( true );
		$fileMetadata = $this->scanFileMetadata( $rootDir );
		$fingerprint = null;
		if ( $mode === self::MODE_AUTO ) {
			$fingerprint = $this->fingerprint( $fileMetadata );
			$cachedManifest = $this->readCachedManifest( $rootDir, $fingerprint, $fileMetadata );
			if ( $cachedManifest !== null ) {
				$this->writeProgress(
					'Runtime refresh cache: hit for '
					.\count( $cachedManifest[ 'files' ] )
					.' managed files in '
					.$this->formatDuration( \microtime( true ) - $scanStartedAt ),
					$onOutput
				);

				return $cachedManifest;
			}
		}

		$manifest = $this->buildManifestFromMetadata( $fileMetadata );
		$this->writeProgress(
			'Runtime refresh scan: '
			.\count( $manifest[ 'files' ] )
			.' managed files in '
			.$this->formatDuration( \microtime( true ) - $scanStartedAt ),
			$onOutput
		);
		if ( $mode === self::MODE_AUTO ) {
			$this->writeCache( $rootDir, $fingerprint ?? $this->fingerprint( $fileMetadata ), $manifest );
		}

		return $manifest;
	}

	/**
	 * @return RuntimeFileMetadata[]
	 */
	private function scanFileMetadata( string $rootDir ) :array {
		$files = [];
		foreach ( self::MANAGED_ROOTS as $relativePath ) {
			$absolutePath = Path::join( $rootDir, $relativePath );
			if ( !\file_exists( $absolutePath ) ) {
				continue;
			}

			if ( \is_file( $absolutePath ) ) {
				$files[] = $this->fileMetadata( $rootDir, $relativePath, $absolutePath );
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $absolutePath, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $fileInfo ) {
				if ( !$fileInfo->isFile() ) {
					continue;
				}
				$files[] = $this->fileMetadata(
					$rootDir,
					Path::makeRelative( $fileInfo->getPathname(), $rootDir ),
					$fileInfo->getPathname()
				);
			}
		}

		\usort(
			$files,
			static fn( array $a, array $b ) :int => $a[ 'relative_path' ] <=> $b[ 'relative_path' ]
		);
		if ( empty( $files ) ) {
			throw new \RuntimeException( 'Local browser runtime manifest is empty; no managed runtime files were found.' );
		}

		return $files;
	}

	/**
	 * @return RuntimeFileMetadata
	 */
	private function fileMetadata( string $rootDir, string $relativePath, string $absolutePath ) :array {
		$size = \filesize( $absolutePath );
		$mtime = \filemtime( $absolutePath );
		if ( $size === false || $mtime === false ) {
			throw new \RuntimeException( 'Failed to inspect runtime file metadata: '.$absolutePath );
		}

		return [
			'relative_path' => $this->normalizeRelativePath( $relativePath ),
			'absolute_path' => Path::join( $rootDir, $this->normalizeRelativePath( $relativePath ) ),
			'size' => (int)$size,
			'mtime' => (int)$mtime,
		];
	}

	/**
	 * @param RuntimeFileMetadata[] $fileMetadata
	 * @return RuntimeManifest
	 */
	private function buildManifestFromMetadata( array $fileMetadata ) :array {
		$files = [];
		foreach ( $fileMetadata as $metadata ) {
			$files[ $metadata[ 'relative_path' ] ] = [
				'sha256' => $this->hashFile( $metadata[ 'absolute_path' ] ),
				'size' => $metadata[ 'size' ],
			];
		}

		return [
			'schema_version' => self::STATE_SCHEMA_VERSION,
			'generated_at_unix' => \time(),
			'files' => $files,
		];
	}

	private function hashFile( string $filePath ) :string {
		$hash = \hash_file( 'sha256', $filePath );
		if ( !\is_string( $hash ) ) {
			throw new \RuntimeException( 'Failed to hash runtime file: '.$filePath );
		}

		return $hash;
	}

	/**
	 * @param RuntimeFileMetadata[] $fileMetadata
	 */
	private function fingerprint( array $fileMetadata ) :string {
		$payload = [
			'schema_version' => self::CACHE_SCHEMA_VERSION,
			'managed_roots_hash' => $this->managedRootsHash(),
			'files' => \array_map(
				static fn( array $metadata ) :array => [
					'path' => $metadata[ 'relative_path' ],
					'size' => $metadata[ 'size' ],
					'mtime' => $metadata[ 'mtime' ],
				],
				$fileMetadata
			),
		];

		return \hash( 'sha256', \json_encode( $payload, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR ) );
	}

	/**
	 * @param RuntimeFileMetadata[] $fileMetadata
	 * @return RuntimeManifest|null
	 */
	private function readCachedManifest( string $rootDir, string $fingerprint, array $fileMetadata ) :?array {
		$cachePath = $this->cachePath( $rootDir );
		if ( !\is_file( $cachePath ) ) {
			return null;
		}

		try {
			$content = \file_get_contents( $cachePath );
			if ( !\is_string( $content ) || $content === '' ) {
				return null;
			}
			$decoded = \json_decode( $content, true, 512, \JSON_THROW_ON_ERROR );
		}
		catch ( \Throwable $e ) {
			return null;
		}

		if ( !\is_array( $decoded )
			|| ( $decoded[ 'schema_version' ] ?? null ) !== self::CACHE_SCHEMA_VERSION
			|| !\is_string( $decoded[ 'managed_roots_hash' ] ?? null )
			|| $decoded[ 'managed_roots_hash' ] !== $this->managedRootsHash()
			|| !\is_string( $decoded[ 'fingerprint' ] ?? null )
			|| $decoded[ 'fingerprint' ] !== $fingerprint
			|| !\is_array( $decoded[ 'manifest' ] ?? null )
		) {
			return null;
		}
		$manifest = $decoded[ 'manifest' ];
		if ( !$this->cachedManifestMatchesMetadata( $manifest, $fileMetadata ) ) {
			return null;
		}

		/** @var RuntimeManifest $manifest */
		return $manifest;
	}

	/**
	 * @param array<string,mixed> $manifest
	 * @param RuntimeFileMetadata[] $fileMetadata
	 */
	private function cachedManifestMatchesMetadata( array $manifest, array $fileMetadata ) :bool {
		if ( ( $manifest[ 'schema_version' ] ?? null ) !== self::STATE_SCHEMA_VERSION
			|| !\is_int( $manifest[ 'generated_at_unix' ] ?? null )
			|| !\is_array( $manifest[ 'files' ] ?? null )
		) {
			return false;
		}

		$expectedSizes = [];
		foreach ( $fileMetadata as $metadata ) {
			$expectedSizes[ $metadata[ 'relative_path' ] ] = $metadata[ 'size' ];
		}

		$cachedFiles = $manifest[ 'files' ];
		if ( \array_diff_key( $expectedSizes, $cachedFiles ) !== []
			|| \array_diff_key( $cachedFiles, $expectedSizes ) !== []
		) {
			return false;
		}

		foreach ( $cachedFiles as $relativePath => $fileInfo ) {
			if ( !\is_array( $fileInfo )
				|| !\is_string( $fileInfo[ 'sha256' ] ?? null )
				|| \preg_match( '/^[a-f0-9]{64}$/i', $fileInfo[ 'sha256' ] ) !== 1
				|| !\array_key_exists( 'size', $fileInfo )
				|| !\is_int( $fileInfo[ 'size' ] )
				|| $fileInfo[ 'size' ] !== $expectedSizes[ $relativePath ]
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param RuntimeManifest $manifest
	 */
	private function writeCache( string $rootDir, string $fingerprint, array $manifest ) :void {
		$cachePath = $this->cachePath( $rootDir );
		$cacheDir = \dirname( $cachePath );
		if ( !\is_dir( $cacheDir ) && !\mkdir( $cacheDir, 0777, true ) && !\is_dir( $cacheDir ) ) {
			throw new \RuntimeException( 'Failed to create local browser runtime manifest cache directory: '.$cacheDir );
		}

		$payload = [
			'schema_version' => self::CACHE_SCHEMA_VERSION,
			'managed_roots_hash' => $this->managedRootsHash(),
			'fingerprint' => $fingerprint,
			'manifest' => $manifest,
		];
		$json = \json_encode( $payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR );
		$tmpPath = $cachePath.'.tmp.'.\getmypid();
		if ( \file_put_contents( $tmpPath, $json.\PHP_EOL ) === false ) {
			throw new \RuntimeException( 'Failed to write local browser runtime manifest cache: '.$tmpPath );
		}
		if ( !\rename( $tmpPath, $cachePath ) ) {
			@\unlink( $tmpPath );
			throw new \RuntimeException( 'Failed to replace local browser runtime manifest cache: '.$cachePath );
		}
	}

	private function cachePath( string $rootDir ) :string {
		return Path::join( $rootDir, self::CACHE_DIR, self::CACHE_FILE );
	}

	private function managedRootsHash() :string {
		return \hash( 'sha256', \json_encode( self::MANAGED_ROOTS, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR ) );
	}

	private function normalizeRelativePath( string $path ) :string {
		return \str_replace( '\\', '/', Path::normalize( $path ) );
	}

	private function formatDuration( float $seconds ) :string {
		return \sprintf( '%.2fs', $seconds );
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
}
