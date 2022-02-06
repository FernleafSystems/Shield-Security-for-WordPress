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
		if ( $this->getCon()->cache_dir_handler->dirExists() ) {
			add_action( $this->getCon()->prefix( 'plugin_shutdown' ), function () {
				$FS = Services::WpFs();
				foreach ( self::$slugs as $file ) {
					$FS->deleteFile( $file );
				}
			} );
		}
	}

	/**
	 * @return mixed|null
	 */
	public function load( string $slug ) {
		$data = null;
		$tmpFile = path_join( $this->getTmpDir(), $slug );
		if ( Services::WpFs()->isFile( $tmpFile ) ) {
			$contents = Services::WpFs()->getFileContent( $tmpFile );
			if ( !empty( $contents ) ) {
				$data = unserialize( $contents );
			}
		}
		return $data;
	}

	public function store( string $slug, $data ) {
		$fullPath = path_join( $this->getTmpDir(), $slug );
		Services::WpFs()->putFileContent( $fullPath, serialize( $data ) );
		self::$slugs[] = $fullPath;
	}

	private function getTmpDir() :string {
		return ( new CacheDir() )
			->setCon( $this->getCon() )
			->buildSubDir( 'tmp_files' );
	}
}