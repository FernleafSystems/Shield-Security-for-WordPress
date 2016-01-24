<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		// after GASP but before email-based two-factor auth
		add_filter( 'authenticate', array( $this, 'checkLoginForGoogleAuthenticator_Filter' ), 23, 2 );

		add_action( 'edit_user_profile', array( $this, '' ) );
	}

	/**
	 * @param $oUser
	 */
	public function addGoogleAuthenticatorOptionsToUserProfile( $oUser ) {
		echo $this->getFeatureOptions()->renderTemplate( 'snippets/user_profile_googleauthenticator.php', array() );
	}

	public function checkLoginForGoogleAuthenticator_Filter( $oUser, $sUsername, $sPassword ) {
		if ( empty( $sUsername ) ) {
			return $oUser;
		}

		$bUserLoginSuccess = is_object( $oUser ) && ( $oUser instanceof WP_User );
		$oDp = $this->loadDataProcessor();
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		if ( $bUserLoginSuccess && $oFO->getCurrentUserHasGoogleAuthenticator() ) {
			$sGaOtp = $oDp->FetchPost( $this->getLoginFormParameter(), '' );
			$sGaOtp = preg_replace( '/[^0_9]/', '', $sGaOtp );
			if ( empty( $sGaOtp ) || !$this->loadGoogleAuthenticatorProcessor()->verifyOtp( $oFO->getCurrentUserGoogleAuthenticatorSecret(), $sGaOtp ) ) {
				$oUser = new WP_Error( 'Google Authenticator OTP Failed' );
			}
		}
		return $oUser;
	}

	/**
	 * @return string
	 */
	protected function getLoginFormParameter() {
		return $this->getFeatureOptions()->prefixOptionKey( 'ga_otp' );
	}
}
endif;