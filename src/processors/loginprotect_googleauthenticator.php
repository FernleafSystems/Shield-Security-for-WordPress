<?php

if ( !class_exists( 'ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_Processor_LoginProtect_GoogleAuthenticator extends ICWP_WPSF_Processor_Base {

	/**
	 */
	public function run() {
		// after GASP but before email-based two-factor auth
		add_filter( 'wp_authenticate_user', array( $this, 'checkLoginForGoogleAuthenticator_Filter' ), 23, 2 );

		add_action( 'personal_options_update', array( $this, 'handleUserProfileSubmit' ) );
		add_action( 'show_user_profile', array( $this, 'addGoogleAuthenticatorOptionsToUserProfile' ) );

		if ( $this->getController()->getIsValidAdminArea( true ) ) {
			add_action( 'edit_user_profile_update', array( $this, 'handleUserProfileSubmit' ) );
			add_action( 'edit_user_profile', array( $this, 'addGoogleAuthenticatorOptionsToUserProfile' ) );
		}

		// Add field to login Form
		add_action( 'login_form', array( $this, 'printGoogleAuthenticatorLoginField' ) );
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
			'user_google_authenticator_secret' => $oFO->getUserGoogleAuthenticatorSecret( $oUser, true ),
			'is_my_user_profile' => ( $oUser->ID == $this->loadWpUsersProcessor()->getCurrentWpUserId() ),
			'i_am_valid_admin' => $this->getController()->getIsValidAdminArea( true ),
			'user_to_edit_is_admin' => $this->loadWpUsersProcessor()->isUserAdmin( $oUser ),
			'strings' => array(
				'description_otp_code' => _wpsf__( 'You must provide the current code from your Google Authenticator app.' ),
				'description_chart_url' => _wpsf__( 'Use your Google Authenticator app to scan this QR code and enter the one time password below.' ),
				'description_remove_google_authenticator' => _wpsf__( 'Check the box to remove Google Authenticator login authentication.' ),
				'label_check_to_remove' => _wpsf__( 'Remove Google Authenticator' ),
				'label_enter_code' => _wpsf__( 'Google Authenticator Code' ),
				'title' => _wpsf__( 'Google Authenticator' ),
				'sorry_cant_add_to_other_user' => _wpsf__( "Sorry, Google Authenticator may not be added to another user's account." ),
				'sorry_cant_remove_from_to_other_admins' => _wpsf__( "Sorry, Google Authenticator may not be removed from another administrator's account." ),
				'provided_by' => sprintf( _wpsf__( 'Provided by %s' ), $this->getController()->getHumanName() )
			)
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
		$oCurrentUser = $this->loadWpUsersProcessor()->getCurrentWpUser();
		$bEditingMyOwnProfile = $oCurrentUser->ID == $oUser->ID;

		if ( !$bEditingMyOwnProfile ) {
			// No OTP checking here as you're not on your own profile, but...
			if ( $oWpUsers->isUserAdmin( $oUser ) ) {
				// TODO: Show error to say you cannot turn on/off another Administrator GA setting
				return;
			}
		}
		else {
			$sGaOtpCode = $oDp->FetchPost( 'shield_ga_otp_code' );
			if ( empty( $sGaOtpCode ) ) {
				return; // not doing anything related to GA
			}
			else if ( !$this->loadGoogleAuthenticatorProcessor()->verifyOtp( $oFO->getUserGoogleAuthenticatorSecret( $oUser ), $sGaOtpCode ) ) {
				// TODO: ERROR MESSAGE because OTP wasn't valid.
				return;
			}
			// At this stage we have a validated GA Code for this user's Secret if applicable.
		}
		// Trying to validate a new QR for my own profile
		if ( $bEditingMyOwnProfile && !$oFO->getUserHasGoogleAuthenticator( $oUser ) ) {
			$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_validated' ), 'Y', $nUserId );
		}
		else {
			// Trying to turn it off.
			$sShieldTurnOff = $oDp->FetchPost( 'shield_turn_off_google_authenticator' );
			if ( !empty( $sShieldTurnOff ) && $sShieldTurnOff == 'Y' ) {
				$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_validated' ), 'N', $nUserId );
				$oWpUsers->updateUserMeta( $oFO->prefixOptionKey( 'ga_secret' ), '', $nUserId );
			}
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return WP_Error
	 */
	public function checkLoginForGoogleAuthenticator_Filter( $oUser ) {

		$oError = new WP_Error();

		$bIsUser = is_object( $oUser ) && ( $oUser instanceof WP_User );
		$oDp = $this->loadDataProcessor();
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		if ( $bIsUser && $oFO->getUserHasGoogleAuthenticator( $oUser ) ) {
			$sGaOtp = $oDp->FetchPost( $this->getLoginFormParameter(), '' );
			if ( empty( $sGaOtp ) ) {
				$oError->add( 'shield_google_authenticator_empty', _wpsf__( 'Whoops.' )
					.' '. _wpsf__( 'Did we forget to use the Google Authenticator?' ) );
				$oUser = $oError;
			}
			else {
				$sGaOtp = preg_replace( '/[^0-9]/', '', $sGaOtp );
				if ( empty( $sGaOtp ) || !$this->loadGoogleAuthenticatorProcessor()->verifyOtp( $oFO->getUserGoogleAuthenticatorSecret( $oUser, false ), $sGaOtp ) ) {
					$oError->add( 'shield_google_authenticator_empty', _wpsf__( 'Oh dear.' )
						.' '. _wpsf__( 'Google Authenticator Code Failed.' ) );
					$oUser = $oError;
				}
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
					<input type="text" name="%s" class="input" value="" size="20" autocomplete="off" />
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