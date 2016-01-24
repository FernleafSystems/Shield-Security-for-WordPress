<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		// after GASP but before email-based two-factor auth
		add_filter( 'authenticate', array( $this, 'checkLoginForGoogleAuthenticator_Filter' ), 23, 2 );

		add_action( 'show_user_profile', array( $this, 'addGoogleAuthenticatorOptionsToUserProfile' ) );
		add_action( 'personal_options_update', array( $this, 'handleUserProfileSubmit' ) );
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addGoogleAuthenticatorOptionsToUserProfile( $oUser ) {

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$aData = array(
			'user_has_google_authenticator_validated' => $oFO->getCurrentUserHasGoogleAuthenticator(),
			'google_authenticator_secret' => $oFO->getCurrentUserGoogleAuthenticatorSecret(),
		);

		if ( !$oFO->getCurrentUserHasGoogleAuthenticator() ) {
			$sChartUrl = $this->loadGoogleAuthenticatorProcessor()->getGoogleQrChartUrl(
				$oFO->getCurrentUserGoogleAuthenticatorSecret(),
				$oUser->get('user_login').'@'.$this->loadWpFunctionsProcessor()->getSiteName()
			);
			$aData[ 'chart_url' ] = $sChartUrl;
		}

		echo $this->getFeatureOptions()->renderTemplate( 'snippets/user_profile_googleauthenticator.php', $aData );
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param $oUser
	 */
	public function handleUserProfileSubmit( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oDp = $this->loadDataProcessor();
		$oWpUsers = $this->loadWpUsersProcessor();

		$sSecret = $oFO->getCurrentUserGoogleAuthenticatorSecret();

		// Trying to validate a new QR.
		$sShieldQrCodeOtp = $oDp->FetchPost( 'shield_qr_code_otp' );
		if ( !$oFO->getCurrentUserHasGoogleAuthenticator() && !empty( $sShieldQrCodeOtp ) ) {
			$bValid = $this->loadGoogleAuthenticatorProcessor()->verifyOtp( $sSecret, $sShieldQrCodeOtp );
			if ( $bValid ) {
				$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_validated' ), 'Y', $oUser );
			}
			else {
				// TODO: ERROR MESSAGE
			}
		}

		$sShieldTurnOff = $oDp->FetchPost( 'shield_turn_off_google_authenticator' );
		if ( !empty( $sShieldTurnOff ) && $sShieldTurnOff == 'Y' ) {
			$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_validated' ), 'N', $oUser );
			$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_secret' ), '', $oUser );
		}
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