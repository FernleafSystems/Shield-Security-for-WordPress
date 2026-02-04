<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class TmpFileStore {

	use ExecOnce;
	use PluginControllerConsumer;

	private static $slugs = [];

	protected function run() {
		if ( self::con()->cache_dir_handler->exists() ) {
			add_action( self::con()->prefix( 'plugin_shutdown' ), function () {
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
		if ( Services::WpFs()->isAccessibleFile( $tmpFile ) ) {
			$contents = Services::WpFs()->getFileContent( $tmpFile );
			if ( !empty( $contents ) ) {
				$data = \unserialize( $contents );
			}
		}
		return $data;
	}

	public function store( string $slug, $data ) {
		$fullPath = path_join( $this->getTmpDir(), $slug );
		Services::WpFs()->putFileContent( $fullPath, \serialize( $data ) );
		self::$slugs[] = $fullPath;
	}

	private function getTmpDir() :string {
		return self::con()->cache_dir_handler->buildSubDir( 'tmp_files' );
	}
}