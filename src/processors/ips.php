<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class ICWP_WPSF_Processor_Ips extends Shield\Modules\BaseShield\ShieldProcessor {

	/**
	 */
	public function run() {
		( new IPs\Lib\BlacklistHandler() )
			->setMod( $this->getMod() )
			->run();
	}

	/**
	 * @deprecated 8.6.2
	 */
	private function doBlackMarkCurrentVisitor() {
	}
}