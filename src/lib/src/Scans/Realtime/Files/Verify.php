<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Realtime\Files;

use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class Verify
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Verify {

	/**
	 * @param string $sPath
	 * @param string $sHash
	 * @return bool
	 */
	public function run( $sPath, $sHash ) {
		try {
			return ( new CompareHash() )->isEqualFileSha1( $sPath, $sHash );
		}
		catch ( \InvalidArgumentException $oE ) {
			return false;
		}
	}
}