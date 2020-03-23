<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_License extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		$oCon = $this->getCon();
		$oReq = Services::Request();

		// performs the license check on-demand
		add_action( $oCon->prefix( 'adhoc_cron_license_check' ), function () {
			/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
			$oMod = $this->getMod();
			try {
				$oMod->getLicenseHandler()->verify( true );
			}
			catch ( \Exception $oE ) {
			}
		} );

		switch ( $oCon->getShieldAction() ) {

			case 'keyless_handshake':
			case 'ssapi_handshake':
				$sNonce = $oReq->query( 'nonce' );
				if ( !empty( $sNonce ) ) {
					die( json_encode( [
						'success' => ( new HandshakingNonce() )
							->setMod( $this->getMod() )
							->verify( $sNonce )
					] ) );
				}
				break;

			case 'license_check':
				if ( !wp_next_scheduled( $oCon->prefix( 'adhoc_cron_license_check' ) ) ) {
					wp_schedule_single_event( $oReq->ts() + 20, $oCon->prefix( 'adhoc_cron_license_check' ) );
				}
				break;
		}
	}
}