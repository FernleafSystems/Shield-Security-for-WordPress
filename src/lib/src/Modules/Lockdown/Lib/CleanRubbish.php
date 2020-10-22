<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Lib;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanRubbish {

	use ModConsumer;
	use OneTimeExecute;

	protected function run() {
		if ( Services::WpGeneral()->isCron() && $this->getOptions()->isOpt( 'clean_wp_rubbish', 'Y' ) ) {
			add_action( $this->getCon()->prefix( 'daily_cron' ), function () {
				$FS = Services::WpFs();
				$allFiles = $FS->getAllFilesInDir( ABSPATH, false );
				foreach ( $allFiles as $file ) {
					if ( in_array( basename( $file ), $this->getFilesToClean() ) ) {
						$FS->deleteFile( $file );
					}
				}
			} );
		}
	}

	/**
	 * @return string[]
	 */
	private function getFilesToClean() {
		return [
			'wp-config-sample.php',
			'readme.html',
			'license.txt',
		];
	}
}