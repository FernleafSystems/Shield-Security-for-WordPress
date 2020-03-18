<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Utilities\File\TestFileWritable;

/**
 * Class TestWritable
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class TestWritable {

	/**
	 * @param string $sPath
	 * @return bool
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