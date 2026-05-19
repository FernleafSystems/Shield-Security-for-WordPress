<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore;

class CacheStoreTestCacheDir {

	public string $root;

	public function __construct( string $root ) {
		$this->root = $root;
	}

	public function exists() :bool {
		return \is_dir( $this->root ) && \is_writable( $this->root );
	}

	public function dir( bool $retest = false ) :string {
		unset( $retest );
		return $this->root;
	}

	public function locateExistingDir() :string {
		return \is_dir( $this->root ) ? $this->root : '';
	}

	public function buildSubDir( string $subDir ) :string {
		if ( $this->root === '' ) {
			return '';
		}
		$dir = \rtrim( $this->root, '/\\' ).'/'.$subDir;
		return ( \is_dir( $dir ) || @\mkdir( $dir, 0777, true ) ) ? \str_replace( '\\', '/', $dir ) : '';
	}
}
