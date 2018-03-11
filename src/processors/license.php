<?php

if ( class_exists( 'ICWP_WPSF_Processor_License' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Processor_License extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_License $oFO */
		$oFO = $this->getFeature();
		$oDp = $this->loadDP();

		switch ( $oDp->query( 'shield_action' ) ) {

			case 'keyless_handshake':
				$sNonce = $oDp->query( 'nonce' );
				if ( !empty( $sNonce ) && $sNonce == $oFO->getKeylessRequestHash() ) {
					$aHandshakeData = array( 'success' => false );
					if ( !$oFO->isKeylessHandshakeExpired() ) {
						$aHandshakeData[ 'success' ] = true;
					}
					die( json_encode( $aHandshakeData ) );
				}
				break;
		}
	}
}