<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDir;
use FernleafSystems\Wordpress\Services\Services;

class TmpFileStore {

	use ExecOnce;
	use PluginControllerConsumer;

	private static $slugs = [];

	protected function run() {
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
		$data = null;
		$tmpFile = path_join( $this->getTmpDir(), $slug );
		if ( Services::WpFs()->isFile( $tmpFile ) ) {
			$contents = Services::WpFs()->getFileContent( path_join( $this->getTmpDir(), $slug ) );
			if ( !empty( $contents ) ) {
				$data = unserialize( $contents );
			}
		}
		return $data;
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