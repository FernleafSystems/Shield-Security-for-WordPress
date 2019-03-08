<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Hash
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpConfig
 */
class Hash {
	/**
	 * @return string
	 */
	public function run() {
		return sha1_file( Services::WpGeneral()->getPath_WpConfig() );
	}
}