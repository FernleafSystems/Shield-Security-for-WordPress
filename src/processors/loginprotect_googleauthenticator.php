<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		// after GASP but before email-based two-factor auth
		add_filter( 'wp_authenticate_user', array( $this, 'checkLoginForGoogleAuthenticator_Filter' ), 23, 2 );

		add_action( 'show_user_profile', array( $this, 'addGoogleAuthenticatorOptionsToUserProfile' ) );
		add_action( 'personal_options_update', array( $this, 'handleUserProfileSubmit' ) );

		// Add field to login Form
		add_action( 'login_form',			array( $this, 'printGoogleAuthenticatorLoginField' ) );
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
			'user_has_google_authenticator_validated' => $oFO->getUserHasGoogleAuthenticator( $oUser ),
			'user_google_authenticator_secret' => $oFO->getUserGoogleAuthenticatorSecret( $oUser ),
		);

		if ( !$aData['user_has_google_authenticator_validated'] ) {
			$sChartUrl = $this->loadGoogleAuthenticatorProcessor()->getGoogleQrChartUrl(
				$aData['user_google_authenticator_secret'],
				$oUser->get('user_login').'@'.$this->loadWpFunctionsProcessor()->getSiteName()
			);
			$aData[ 'chart_url' ] = $sChartUrl;
		}

		echo $this->getFeatureOptions()->renderTemplate( 'snippets/user_profile_googleauthenticator.php', $aData );
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param int $nUserId
	 */
	public function handleUserProfileSubmit( $nUserId ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$oDp = $this->loadDataProcessor();
		$oWpUsers = $this->loadWpUsersProcessor();

		$oUser = $oWpUsers->getUserById( $nUserId );

		// Trying to validate a new QR.
		$sShieldQrCodeOtp = $oDp->FetchPost( 'shield_qr_code_otp' );
		if ( !$oFO->getUserHasGoogleAuthenticator( $oUser ) && !empty( $sShieldQrCodeOtp ) ) {
			$bValid = $this->loadGoogleAuthenticatorProcessor()->verifyOtp( $oFO->getUserGoogleAuthenticatorSecret( $oUser ), $sShieldQrCodeOtp );
			if ( $bValid ) {
				$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_validated' ), 'Y', $nUserId );
			}
			else {
				// TODO: ERROR MESSAGE
			}
		}

		$sShieldTurnOff = $oDp->FetchPost( 'shield_turn_off_google_authenticator' );
		if ( !empty( $sShieldTurnOff ) && $sShieldTurnOff == 'Y' ) {
			$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_validated' ), 'N', $nUserId );
			$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_secret' ), '', $nUserId );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return WP_Error
	 */
	public function checkLoginForGoogleAuthenticator_Filter( $oUser ) {

		$bIsUser = is_object( $oUser ) && ( $oUser instanceof WP_User );
		$oDp = $this->loadDataProcessor();
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		if ( $bIsUser && $oFO->getUserHasGoogleAuthenticator( $oUser ) ) {
			$sGaOtp = $oDp->FetchPost( $this->getLoginFormParameter(), '' );
			$sGaOtp = preg_replace( '/[^0_9]/', '', $sGaOtp );
			if ( empty( $sGaOtp ) || !$this->loadGoogleAuthenticatorProcessor()->verifyOtp( $oFO->getUserGoogleAuthenticatorSecret( $oUser ), $sGaOtp ) ) {
				$oUser = new WP_Error( 'Google Authenticator OTP Failed' );
			}
		}
		return $oUser;
	}

	/**
	 */
	public function printGoogleAuthenticatorLoginField() {
		$sHtml =
			'<p class="shield-google-authenticator-otp">
				<label>%s<br />
					<input type="text" name="%s" class="input" value="" size="20" />
				</label>
			</p>
		';
		echo sprintf( $sHtml,
			'<a href="http://icwp.io/4i" target="_blank">'._wpsf__('Google Authenticator').'</a>',
			$this->getLoginFormParameter()
		);
	}

	/**
	 * @return string
	 */
	protected function getLoginFormParameter() {
		return $this->getFeatureOptions()->prefixOptionKey( 'ga_otp' );
	}
}
endif;