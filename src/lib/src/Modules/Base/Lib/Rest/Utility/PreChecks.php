<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class PreChecks {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		try {
			$this->testFileSystem();
		}
		catch ( \Exception $e ) {
			throw new \Exception( 'Service is temporarily unavailable. Report Code: '.$e->getCode() );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function testFileSystem() {
		$dir = $this->getMod()->getWorkingDir();
		$space = disk_free_space( $dir );
		if ( $space === false || $space < 2000000 ) {
			throw new \Exception( 'Not enough disk space: '.$space, 402 );
		}
	}
}