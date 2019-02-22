<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

class ICWP_WPSF_Processor_UserManagement_Suspend extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isSuspendManualEnabled() ) {
			( new Suspend\Suspended() )
				->setCon( $this->getCon() )
				->run();
		}
		if ( $oFO->isSuspendAutoIdleEnabled() ) {
			( new Suspend\Idle() )
				->setVerifiedExpires( $oFO->getSuspendAutoIdleTime() )
				->setCon( $this->getCon() )
				->run();
		}
		if ( $oFO->isSuspendAutoPasswordEnabled() ) {
			( new Suspend\PasswordExpiry() )
				->setMaxPasswordAge( $oFO->getPassExpireTimeout() )
				->setCon( $this->getCon() )
				->run();
		}
	}
}