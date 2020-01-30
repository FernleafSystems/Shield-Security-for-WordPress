<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_License extends Modules\BaseShield\ShieldProcessor {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_License $oFO */
		$oFO = $this->getMod();
		$oReq = Services::Request();

		// performs the license check
		add_action( $oFO->prefix( 'adhoc_cron_license_check' ), [ $oFO, 'verifyLicense' ] );

		switch ( $this->getCon()->getShieldAction() ) {

			case 'keyless_handshake':
				$sNonce = $oReq->query( 'nonce' );
				if ( !empty( $sNonce ) && $sNonce == $oFO->getKeylessRequestHash() ) {
					$aHandshakeData = [ 'success' => false ];
					if ( !$oFO->isKeylessHandshakeExpired() ) {
						$aHandshakeData[ 'success' ] = true;
					}
					die( json_encode( $aHandshakeData ) );
				}
				break;

			case 'license_check':
				if ( !wp_next_scheduled( $oFO->prefix( 'adhoc_cron_license_check' ) ) ) {
					wp_schedule_single_event( $oReq->ts() + 20, $oFO->prefix( 'adhoc_cron_license_check' ), [ true ] );
				}
				break;
		}
	}
}