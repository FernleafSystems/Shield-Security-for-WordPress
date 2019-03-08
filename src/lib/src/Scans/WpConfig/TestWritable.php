<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\TestFileWritable;

/**
 * Class TestWritable
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class TestWritable {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function run() {
		try {
			$bCan = ( new TestFileWritable() )->run( Services::WpGeneral()->getPath_WpConfig() );
		}
		catch ( \Exception $oE ) {
			$bCan = false;
		}
		return $bCan;
	}
}