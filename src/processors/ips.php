<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Ips extends ShieldProcessor {

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
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$oTracker = $oMod->loadOffenseTracker();
		if ( !$this->getCon()->isPluginDeleting()
			 && $oTracker->hasVisitorOffended() && $oTracker->isCommit()
			 && !$oMod->isVerifiedBot() ) {

			( new IPs\Components\ProcessOffense() )
				->setMod( $oMod )
				->setIp( Services::IP()->getRequestIp() )
				->run();
		}
	}
}