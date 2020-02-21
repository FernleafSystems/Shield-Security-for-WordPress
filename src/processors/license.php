<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_License extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		$oCon = $this->getCon();
		/** @var Modules\License\Options $oOpts */
		$oOpts = $this->getOptions();
		$oReq = Services::Request();

		// performs the license check
		add_action( $oCon->prefix( 'adhoc_cron_license_check' ), function () {
			/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
			$oMod = $this->getMod();
			$oMod->getLicenseHandler()->verify( true );
		} );

		switch ( $oCon->getShieldAction() ) {

			case 'keyless_handshake':
				$sNonce = $oReq->query( 'nonce' );
				if ( !empty( $sNonce ) && $sNonce === $oOpts->getOpt( 'keyless_handshake_hash' ) ) {
					die( json_encode( [
						'success' => $oOpts->getOpt( 'keyless_handshake_until', 0 ) >= $oReq->ts()
					] ) );
				}
				break;

			case 'license_check':
				if ( !wp_next_scheduled( $oCon->prefix( 'adhoc_cron_license_check' ) ) ) {
					wp_schedule_single_event( $oReq->ts() + 20, $oCon->prefix( 'adhoc_cron_license_check' ), [ true ] );
				}
				break;
		}
	}
}