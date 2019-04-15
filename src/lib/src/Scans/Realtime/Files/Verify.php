<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Realtime\Files;

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
		return sha1_file( $sPath ) === $sHash;
	}
}