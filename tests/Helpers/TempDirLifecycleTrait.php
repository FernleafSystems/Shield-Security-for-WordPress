<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use Symfony\Component\Filesystem\Path;

trait TempDirLifecycleTrait {

	/**
	 * @var string[]
	 */
	private array $trackedTempDirs = [];

	/**
	 * @var string[]
	 */
	private array $trackedTempFiles = [];

	/**
	 * @var string[]
	 */
	private array $trackedTempPaths = [];

	protected function createTrackedTempDir( string $prefix = 'shield-test-', ?string $parentDir = null ) :string {
		$path = $this->createTrackedTempPath( $prefix, '', $parentDir );
		if ( !\is_dir( $path ) && !\mkdir( $path, 0777, true ) && !\is_dir( $path ) ) {
			throw new \RuntimeException( 'Failed to create temporary directory: '.$path );
		}
		$this->trackedTempDirs[] = $path;
		return $path;
	}

	protected function createTrackedTempPath(
		string $prefix = 'shield-test-',
		string $suffix = '',
		?string $parentDir = null
	) :string {
		$this->assertSafeTempBasenamePart( $prefix, 'prefix' );
		$this->assertSafeTempBasenamePart( $suffix, 'suffix' );

		$parentDir = $this->normaliseTrackedTempPath( $parentDir ?? \sys_get_temp_dir() );
		if ( $parentDir === '' ) {
			throw new \RuntimeException( 'Temporary parent directory cannot be empty.' );
		}
		if ( !\is_dir( $parentDir ) ) {
			throw new \RuntimeException( 'Temporary parent directory does not exist: '.$parentDir );
		}

		$path = $this->normaliseTrackedTempPath( Path::join(
			$parentDir,
			$prefix.$this->uniqueTrackedTempSuffix().$suffix
		) );
		$this->assertTrackedTempPathIsOwned( $path, $parentDir );

		$this->trackedTempPaths[] = $path;
		return $path;
	}

	protected function createTrackedTempFile(
		string $prefix = 'shield-test-',
		string $suffix = '',
		string $contents = '',
		?string $parentDir = null
	) :string {
		$path = $this->createTrackedTempPath( $prefix, $suffix, $parentDir );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) && !\mkdir( $dir, 0777, true ) && !\is_dir( $dir ) ) {
			throw new \RuntimeException( 'Failed to create temporary file directory: '.$dir );
		}
		if ( \file_put_contents( $path, $contents ) === false ) {
			throw new \RuntimeException( 'Failed to create temporary file: '.$path );
		}
		$this->trackedTempFiles[] = $path;
		return $path;
	}

	protected function cleanupTrackedTempDirs() :void {
		foreach ( \array_reverse( \array_unique( \array_merge( $this->trackedTempFiles, $this->trackedTempPaths ) ) ) as $path ) {
			if ( \is_file( $path ) || \is_link( $path ) ) {
				$this->assertSafeTrackedTempCleanupPath( $path );
				@\unlink( $path );
			}
		}

		$dirs = \array_values( \array_unique( \array_merge(
			$this->trackedTempDirs,
			\array_filter( $this->trackedTempPaths, 'is_dir' )
		) ) );
		\usort(
			$dirs,
			static fn( string $a, string $b ) :int => \strlen( $b ) <=> \strlen( $a )
		);

		foreach ( $dirs as $path ) {
			if ( \is_dir( $path ) ) {
				$this->removeTrackedTempDir( $path );
			}
		}

		$this->trackedTempDirs = [];
		$this->trackedTempFiles = [];
		$this->trackedTempPaths = [];
	}

	private function removeTrackedTempDir( string $path ) :void {
		$this->assertSafeTrackedTempCleanupPath( $path );
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $path );
	}

	private function assertSafeTrackedTempCleanupPath( string $path ) :void {
		$path = $this->normaliseTrackedTempPath( $path );
		if ( $path === '' || !$this->trackedTempPathLooksOwned( $path ) ) {
			throw new \RuntimeException( 'Refusing to clean unowned temporary path: '.$path );
		}

		foreach ( $this->dangerousTrackedTempRoots() as $dangerousRoot ) {
			if ( $this->sameTrackedTempPath( $path, $dangerousRoot ) ) {
				throw new \RuntimeException( 'Refusing to clean dangerous temporary path: '.$path );
			}
		}
	}

	private function assertTrackedTempPathIsOwned( string $path, string $parentDir ) :void {
		if ( !$this->trackedTempPathLooksOwned( $path ) || !$this->isTrackedTempChildOf( $path, $parentDir ) ) {
			throw new \RuntimeException( 'Generated temporary path is not safely owned: '.$path );
		}
	}

	private function assertSafeTempBasenamePart( string $value, string $name ) :void {
		if ( \strpbrk( $value, "\\/\0" ) !== false ) {
			throw new \InvalidArgumentException( 'Temporary path '.$name.' must not contain path separators.' );
		}
	}

	private function trackedTempPathLooksOwned( string $path ) :bool {
		return \preg_match( '#(?:^|/)[^/]*\d+-[0-9a-f]{16}[^/]*$#', $path ) === 1;
	}

	private function uniqueTrackedTempSuffix() :string {
		return \sprintf(
			'%d-%s',
			\getmypid() ?: 0,
			\bin2hex( \random_bytes( 8 ) )
		);
	}

	private function dangerousTrackedTempRoots() :array {
		$roots = [
			\sys_get_temp_dir(),
			\dirname( __DIR__, 2 ),
		];
		foreach ( [ 'ABSPATH', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR' ] as $constant ) {
			if ( \defined( $constant ) ) {
				$roots[] = (string)\constant( $constant );
			}
		}

		return \array_filter(
			\array_map( fn( string $path ) :string => $this->normaliseTrackedTempPath( $path ), $roots ),
			static fn( string $path ) :bool => $path !== ''
		);
	}

	private function isTrackedTempChildOf( string $path, string $parentDir ) :bool {
		$path = \strtolower( $this->normaliseTrackedTempPath( $path ) );
		$parentDir = \strtolower( $this->normaliseTrackedTempPath( $parentDir ) );
		return \strpos( $path, \rtrim( $parentDir, '/' ).'/' ) === 0;
	}

	private function sameTrackedTempPath( string $a, string $b ) :bool {
		return \strtolower( $this->normaliseTrackedTempPath( $a ) ) === \strtolower( $this->normaliseTrackedTempPath( $b ) );
	}

	private function normaliseTrackedTempPath( string $path ) :string {
		return \rtrim( \str_replace( '\\', '/', Path::normalize( $path ) ), '/' );
	}
}
