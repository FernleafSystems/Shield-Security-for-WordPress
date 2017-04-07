<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth', false ) ):
	return;
endif;

require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'loginprotect_intent_base.php' );

class ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth extends ICWP_WPSF_Processor_LoginProtect_IntentBase {

	public function run() {
		parent::run();
		add_action( 'init', array( $this, 'onWpInit' ) );  // TODO: temporary, remove ASAP
	}

	/**
	 * TODO: Temporary
	 */
	public function onWpInit() {
		if ( $this->getController()->getIsValidAdminArea() ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getFeatureOptions();
			$oDb = $this->loadDbProcessor();
			$sTableName = $oDb->getPrefix().$oFO->getTwoFactorAuthTableName();
			if ( $oDb->getIfTableExists( $sTableName ) ) {
				$oDb->doDropTable( $sTableName );
				$sCronName = $oFO->doPluginPrefix( $oFO->getFeatureSlug().'_db_cleanup' );
				wp_clear_scheduled_hook( $sCronName );
			}
		}
	}

	/**
	 * @param WP_User|WP_Error|null $oUser
	 * @return WP_Error|WP_User|null	- WP_User when the login success AND the IP is authenticated. null when login not successful but IP is valid. WP_Error otherwise.
	 */
	public function processLoginAttempt_Filter( $oUser ) {

		$bUserLoginSuccess = is_object( $oUser ) && ( $oUser instanceof WP_User );
		if ( $bUserLoginSuccess && $this->hasValidatedProfile( $oUser ) ) {

			// Now send email with authentication link for user.
			$this->doStatIncrement( 'login.twofactor.started' );
			$this->sendEmailTwoFactorVerify(
				$oUser,
				$this->loadDataProcessor()->getVisitorIpAddress( true ),
				$this->getController()->getSessionId()
			);
		}
		return $oUser;
	}

	/**
	 * @param bool $bIsSuccess
	 */
	protected function auditLogin( $bIsSuccess ) {
		if ( $bIsSuccess ) {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" verified their identity using Email Two-Factor Authentication.' ), $this->loadWpUsersProcessor()->getCurrentWpUser()->get( 'user_login' ) ),
				2, 'login_protect_two_factor_verified'
			);
			$this->doStatIncrement( 'login.twofactor.verified' );
		}
		else {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" failed to verify their identity using Email Two-Factor Authentication.' ), $this->loadWpUsersProcessor()->getCurrentWpUser()->get( 'user_login' ) ),
				2, 'login_protect_two_factor_failed'
			);
			$this->doStatIncrement( 'login.twofactor.failed' );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		return $sOtpCode == $this->getSessionHashCode();
	}

	/**
	 * @param array $aFields
	 * @return array
	 */
	public function addLoginIntentField( $aFields ) {
		if ( $this->getCurrentUserHasValidatedProfile() ) {
			$aFields[] = array(
				'name' => $this->getLoginFormParameter(),
				'type' => 'text',
				'value' => $this->fetchCodeFromRequest(),
				'placeholder' => _wpsf__( 'This code was just sent to your registered Email address.' ),
				'text' => _wpsf__( 'Email OTP' ),
				'help_link' => 'http://icwp.io/3t'
			);
		}
		return $aFields;
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	public function hasValidatedProfile( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		// Currently it's a global setting but this will evolve to be like Google Authenticator so that it's a user meta
		return ( $oFO->getIsEmailAuthenticationEnabled() && $oFO->getIsUserSubjectToEmailAuthentication( $oUser ) );
	}

	/**
	 * @return string
	 */
	protected function genSessionHash() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		return hash_hmac(
			'sha1',
			$this->getController()->getSessionId(),
			$oFO->getTwoAuthSecretKey()
		);
	}

	/**
	 * @return string
	 */
	protected function getSessionHashCode() {
		return strtoupper( substr( $this->genSessionHash(), 0, 6 ) );
	}

	/**
	 * Given the necessary components, creates the 2-factor verification link for giving to the user.
	 *
	 * @param string $sUser
	 * @param string $sSessionId
	 * @return string
	 */
	protected function generateTwoFactorVerifyLink( $sUser, $sSessionId ) {
		$sUrl = $this->buildTwoFactorVerifyUrl( $sUser, $sSessionId );
		return sprintf( '<a href="%s" target="_blank">%s</a>', $sUrl, $sUrl );
	}

	/**
	 * @param string $sUser
	 * @param string $sSessionId
	 * @return string
	 */
	protected function buildTwoFactorVerifyUrl( $sUser, $sSessionId ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$aQueryArgs = array(
			$this->getLoginFormParameter()    => $this->getSessionHashCode(),
			$oFO->getLoginIntentRequestFlag() => 1,
			'username'                        => rawurlencode( $sUser ),
			'sessionid'                       => $sSessionId
		);
		$sRedirectTo = esc_url( $this->loadDataProcessor()->FetchPost( 'redirect_to' ) );
		if ( !empty( $sRedirectTo ) ) {
			$aQueryArgs[ 'redirect_to' ] = urlencode( $sRedirectTo );
		}
		return add_query_arg( $aQueryArgs, $this->loadWpFunctionsProcessor()->getHomeUrl() );
	}

	/**
	 * @param WP_User $oUser
	 * @param string $sIpAddress
	 * @param string $sSessionId
	 * @return boolean
	 */
	public function sendEmailTwoFactorVerify( WP_User $oUser, $sIpAddress, $sSessionId ) {

		$sEmail = $oUser->get( 'user_email' );
		$sAuthLink = $this->generateTwoFactorVerifyLink( $oUser->get( 'user_login' ), $sSessionId );

		$aMessage = array(
			_wpsf__( 'You, or someone pretending to be you, just attempted to login into your WordPress site.' ),
			_wpsf__( 'The IP Address / Cookie from which they tried to login is not currently verified.' ),
			sprintf( _wpsf__( 'Username: %s' ), $oUser->get( 'user_login' ) ),
			sprintf( _wpsf__( 'IP Address: %s' ), $sIpAddress ),
			_wpsf__('Use the following code in the Login Verification page.'),
			'',
			sprintf( _wpsf__( 'Authentication Code: %s' ), $this->getSessionHashCode() ),
			_wpsf__('Or:'),
			'',
			_wpsf__('Click the following link to validate your login by email.').' '._wpsf__('You will be logged in automatically upon successful authentication.'),
			sprintf( _wpsf__( 'Authentication Link: %s' ), $sAuthLink ),
			''
		);
		$sEmailSubject = sprintf( _wpsf__( 'Two-Factor Login Verification for %s' ), $this->loadWpFunctionsProcessor()->getHomeUrl() );

		$bResult = $this->getEmailProcessor()->sendEmailTo( $sEmail, $sEmailSubject, $aMessage );
		if ( $bResult ) {
			$sAuditMessage = sprintf( _wpsf__('User "%s" was sent an email to verify their Identity using Two-Factor Login Auth for IP address "%s".'), $oUser->get( 'user_login' ), $sIpAddress );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_two_factor_email_send' );
		}
		else {
			$sAuditMessage = sprintf( _wpsf__('Tried to send email to User "%s" to verify their identity using Two-Factor Login Auth for IP address "%s", but email sending failed.'), $oUser->get( 'user_login' ), $sIpAddress );
			$this->addToAuditEntry( $sAuditMessage, 3, 'login_protect_two_factor_email_send_fail' );
		}
		return $bResult;
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeatureOptions();
		$bValidatedProfile = $this->hasValidatedProfile( $oUser );
		$aData = array(
			'user_has_email_authentication_active' => $bValidatedProfile,
			'user_has_email_authentication_enforced' => $oFO->getIsUserSubjectToEmailAuthentication( $oUser ),
			'is_my_user_profile' => ( $oUser->ID == $this->loadWpUsersProcessor()->getCurrentWpUserId() ),
			'i_am_valid_admin' => $this->getController()->getIsValidAdminArea( true ),
			'user_to_edit_is_admin' => $this->loadWpUsersProcessor()->isUserAdmin( $oUser ),
			'strings' => array(
				'label_email_authentication' => _wpsf__( 'Email Authentication' ),
				'title' => _wpsf__( 'Email Authentication' ),
				'description_email_authentication_checkbox' => _wpsf__( 'Check the box to enable email-based login authentication.' ),
				'provided_by' => sprintf( _wpsf__( 'Provided by %s' ), $this->getController()->getHumanName() )
			)
		);

		$aData['bools'] = array(
			'checked' => $bValidatedProfile || $aData[ 'user_has_email_authentication_enforced' ],
			'disabled' => true || $aData[ 'user_has_email_authentication_enforced' ] //TODO: Make email authentication a per-user setting
		);

		echo $this->getFeatureOptions()->renderTemplate( 'snippets/user_profile_emailauthentication.php', $aData );
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_Email;
	}
}