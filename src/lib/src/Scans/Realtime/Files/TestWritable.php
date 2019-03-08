<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Realtime\Files;

use FernleafSystems\Wordpress\Services\Utilities\File\TestFileWritable;

/**
 * Class TestWritable
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class TestWritable {

	/**
	 * @param string $sPath
	 * @return bool
	 * @throws \Exception
	 */
	public function run( $sPath ) {
		try {
			$bCan = ( new TestFileWritable() )->run( $sPath );
		}
		catch ( \Exception $oE ) {
			$bCan = false;
		}
		return $bCan;
	}
}