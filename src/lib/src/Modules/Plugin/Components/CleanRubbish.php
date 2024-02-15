<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanRubbish {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return $this->opts()->isOpt( 'clean_wp_rubbish', 'Y' );
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