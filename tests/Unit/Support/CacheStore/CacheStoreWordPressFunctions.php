<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

use Brain\Monkey\Functions;

trait CacheStoreWordPressFunctions {

	private CacheStoreTestFs $cacheStoreFs;

	private string $cacheStoreTmpDir;

	protected function registerCacheStoreWordPressFunctions( CacheStoreTestFs $fs, string $tmpDir ) :void {
		$this->cacheStoreFs = $fs;
		$this->cacheStoreTmpDir = $this->normaliseCacheStorePath( $tmpDir );

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias(
			fn( string $base, string $path ) :string => $this->normaliseCacheStorePath(
				\rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' )
			)
		);
		Functions\when( 'wp_normalize_path' )->alias(
			fn( string $path ) :string => $this->normaliseCacheStorePath( $path )
		);
		Functions\when( 'untrailingslashit' )->alias(
			fn( string $path ) :string => \rtrim( $this->normaliseCacheStorePath( $path ), '/' )
		);
		Functions\when( 'trailingslashit' )->alias(
			fn( string $path ) :string => \rtrim( $this->normaliseCacheStorePath( $path ), '/' ).'/'
		);
		Functions\when( 'path_is_absolute' )->alias(
			fn( string $path ) :bool => \preg_match( '#^([A-Za-z]:)?/#', $this->normaliseCacheStorePath( $path ) ) === 1
		);
		Functions\when( 'get_temp_dir' )->alias(
			fn() :string => $this->cacheStoreTmpDir
		);
		Functions\when( 'wp_is_writable' )->alias(
			fn( string $path ) :bool => $this->cacheStorePathIsWritable( $path )
		);
		Functions\when( 'wp_mkdir_p' )->alias(
			fn( string $path ) :bool => (bool)$this->cacheStoreFs->mkdir( $path )
		);
		Functions\when( 'wp_generate_password' )->alias(
			static fn( int $length, bool $specialChars = true ) :string => \substr( \str_repeat( 'a', $length ), 0, $length )
		);
	}

	protected function normaliseCacheStorePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}

	private function cacheStorePathIsWritable( string $path ) :bool {
		$path = $this->normaliseCacheStorePath( $path );
		foreach ( $this->cacheStoreFs->failedDirs as $failedDir ) {
			if ( $path === $failedDir || \str_starts_with( $path.'/', $failedDir.'/' ) ) {
				return false;
			}
		}
		return \is_dir( $path ) && \is_writable( $path );
	}
}
