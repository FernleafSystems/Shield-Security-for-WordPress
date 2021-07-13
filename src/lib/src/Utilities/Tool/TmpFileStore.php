<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDir;
use FernleafSystems\Wordpress\Services\Services;

class TmpFileStore {

	use PluginControllerConsumer;

	private static $slugs = [];

	/**
	 * @throws \Exception
	 */
	public function __construct() {
		if ( $this->getCon()->hasCacheDir() ) {
			add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
				$FS = Services::WpFs();
				foreach ( self::$slugs as $slug ) {
					$FS->deleteFile( path_join( $this->getTmpDir(), $slug ) );
				}
			} );
		}
		else {
			throw new \Exception( "Can't create the cache dir" );
		}
	}

	/**
	 * @param string $slug
	 * @return mixed|null
	 */
	public function load( string $slug ) {
		$data = Services::WpFs()->getFileContent( path_join( $this->getTmpDir(), $slug ) );
		return empty( $data ) ? null : unserialize( $data );
	}

	public function store( string $slug, $data ) {
		Services::WpFs()->putFileContent( path_join( $this->getTmpDir(), $slug ), serialize( $data ) );
		self::$slugs[] = $slug;
	}

	private function getTmpDir() :string {
		return ( new CacheDir() )
			->setCon( $this->getCon() )
			->buildSubDir( 'tmp_files' );
	}
}