<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanRubbish {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'clean_wp_rubbish', 'Y' );
	}

	protected function run() {
		$FS = Services::WpFs();
		foreach ( $FS->getAllFilesInDir( ABSPATH, false ) as $file ) {
			if ( \in_array( \basename( $file ), $this->getFilesToClean() ) ) {
				$FS->deleteFile( $file );
			}
		}
	}

	private function getFilesToClean() :array {
		return [
			'wp-config-sample.php',
			'readme.html',
			'license.txt',
		];
	}
}