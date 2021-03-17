<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanRubbish {

	use ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return $this->getOptions()->isOpt( 'clean_wp_rubbish', 'Y' );
	}

	protected function run() {
		$FS = Services::WpFs();
		foreach ( $FS->getAllFilesInDir( ABSPATH, false ) as $file ) {
			if ( in_array( basename( $file ), $this->getFilesToClean() ) ) {
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