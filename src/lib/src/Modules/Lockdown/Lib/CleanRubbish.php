<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanRubbish extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->getOptions()->isOpt( 'clean_wp_rubbish', 'Y' );
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