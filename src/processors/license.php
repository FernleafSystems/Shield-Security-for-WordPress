<?php

class ICWP_WPSF_Processor_License extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_License $oFO */
		$oFO = $this->getMod();
		$oReq = $this->loadRequest();

		// performs the license check
		add_action( $oFO->prefix( 'adhoc_cron_license_check' ), array( $oFO, 'verifyLicense' ) );

		switch ( $oReq->query( 'shield_action' ) ) {

			case 'keyless_handshake':
				$sNonce = $oReq->query( 'nonce' );
				if ( !empty( $sNonce ) && $sNonce == $oFO->getKeylessRequestHash() ) {
					$aHandshakeData = array( 'success' => false );
					if ( !$oFO->isKeylessHandshakeExpired() ) {
						$aHandshakeData[ 'success' ] = true;
					}
					die( json_encode( $aHandshakeData ) );
				}
				break;

			case 'license_check':
				if ( !wp_next_scheduled( $oFO->prefix( 'adhoc_cron_license_check' ) ) ) {
					wp_schedule_single_event( $oReq->ts() + 20, $oFO->prefix( 'adhoc_cron_license_check' ), array( true ) );
				}
				break;
		}
	}
}