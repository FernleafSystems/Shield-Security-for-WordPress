<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_HackProtect extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		die('hasdf');
	}

	public function getSubProScanner() :\ICWP_WPSF_Processor_HackProtect_Scanner {
		return $this->getSubPro( 'scanner' );
	}

	protected function getSubProMap() :array {
		return [
			'scanner' => 'ICWP_WPSF_Processor_HackProtect_Scanner',
		];
	}
}