<?php

class ICWP_WPSF_Processor_LoginProtect_TwoFactorAuth extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	/**
	 * @param WP_User|WP_Error|null $oUser
	 * @return WP_Error|WP_User|null    - WP_User when the login success AND the IP is authenticated. null when login
	 *                                  not successful but IP is valid. WP_Error otherwise.
	 */
	public function processLoginAttempt( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( !$this->isLoginCaptured() && $oUser instanceof WP_User
			 && $this->hasValidatedProfile( $oUser ) && !$oFO->canUserMfaSkip( $oUser ) ) {

			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpd */
			$oUpd = $oFO->getSessionsProcessor()->getDbHandler()->getQueryUpdater();
			$oUpd->setLoginIntentCodeEmail( $oFO->getSession(), $this->getSecret( $oUser ) );

			// Now send email with authentication link for user.
			$this->doStatIncrement( 'login.twofactor.started' )
				 ->sendEmailTwoFactorVerify( $oUser )
				 ->setLoginCaptured();
		}
		return $oUser;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		if ( $bIsSuccess ) {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" verified their identity using %s method.' ),
					$oUser->user_login, _wpsf__( 'Email Auth' )
				), 2, 'login_protect_emailauth_verified'
			);
			$this->doStatIncrement( 'login.emailauth.verified' );
		}
		else {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" failed to verify their identity using %s method.' ),
					$oUser->user_login, _wpsf__( 'Email Auth' )
				), 2, 'login_protect_emailauth_failed'
			);
			$this->doStatIncrement( 'login.emailauth.failed' );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		$bValid = !empty( $sOtpCode ) && ( $sOtpCode == $this->getStoredSessionHashCode() );
		if ( $bValid ) {
			/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
			$oFO = $this->getMod();
			/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpd */
			$oUpd = $oFO->getSessionsProcessor()->getDbHandler()->getQueryUpdater();
			$oUpd->clearLoginIntentCodeEmail( $oFO->getSession() );
		}
		return $bValid;
	}

	/**
	 * @param array $aFields
	 * @return array
	 */
	public function addLoginIntentField( $aFields ) {
		if ( $this->getCurrentUserHasValidatedProfile() ) {
			$aFields[] = array(
				'name'        => $this->getLoginFormParameter(),
				'type'        => 'text',
				'value'       => $this->fetchCodeFromRequest(),
				'placeholder' => _wpsf__( 'This code was just sent to your registered Email address.' ),
				'text'        => _wpsf__( 'Email OTP' ),
				'help_link'   => 'https://icwp.io/3t'
			);
		}
		return $aFields;
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function hasValidatedProfile( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		// Currently it's a global setting but this will evolve to be like Google Authenticator so that it's a user meta
		return ( $oFO->isEmailAuthenticationActive() && $this->isSubjectToEmailAuthentication( $oUser ) );
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	private function isSubjectToEmailAuthentication( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return count( array_intersect( $oFO->getEmail2FaRoles(), $oUser->roles ) ) > 0;
	}

	/**
	 * @return string
	 */
	protected function genSessionHash() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return hash_hmac(
			'sha1',
			$this->getCon()->getUniqueRequestId(),
			$oFO->getTwoAuthSecretKey()
		);
	}

	/**
	 * We don't use user meta as it's dependent on the particular user sessions in-use
	 * @param WP_User $oUser
	 * @return string
	 */
	protected function getSecret( WP_User $oUser ) {
		return strtoupper( substr( $this->genSessionHash(), 0, 6 ) );
	}

	/**
	 * @return string The unique 2FA 6-digit code
	 */
	protected function getStoredSessionHashCode() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->hasSession() ? $oFO->getSession()->getLoginIntentCodeEmail() : '';
	}

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		$sHash = $this->getStoredSessionHashCode();
		return !empty( $sHash );
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	protected function sendEmailTwoFactorVerify( WP_User $oUser ) {
		$sIpAddress = $this->ip();

		$aMessage = array(
			_wpsf__( 'Someone attempted to login into this WordPress site using your account.' ),
			_wpsf__( 'Login requires verification with the following code.' ),
			'',
			sprintf( _wpsf__( 'Verification Code: %s' ), sprintf( '<strong>%s</strong>', $this->getSecret( $oUser ) ) ),
			'',
			sprintf( '<strong>%s</strong>', _wpsf__( 'Login Details' ) ),
			sprintf( '%s: %s', _wpsf__( 'URL' ), $this->loadWp()->getHomeUrl() ),
			sprintf( '%s: %s', _wpsf__( 'Username' ), $oUser->user_login ),
			sprintf( '%s: %s', _wpsf__( 'IP Address' ), $sIpAddress ),
			'',
		);

		if ( !$this->getCon()->isRelabelled() ) {
			$aMessage[] = sprintf( '- <a href="%s" target="_blank">%s</a>', 'https://icwp.io/96', _wpsf__( 'Why no login link?' ) );
			$aContent[] = '';
		}

		$sEmailSubject = _wpsf__( 'Two-Factor Login Verification' );

		$bResult = $this->getEmailProcessor()
						->sendEmailWithWrap( $oUser->user_email, $sEmailSubject, $aMessage );
		if ( $bResult ) {
			$sAuditMessage = sprintf( _wpsf__( 'User "%s" was sent an email to verify their Identity using Two-Factor Login Auth for IP address "%s".' ), $oUser->user_login, $sIpAddress );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_two_factor_email_send' );
		}
		else {
			$sAuditMessage = sprintf( _wpsf__( 'Tried to send email to User "%s" to verify their identity using Two-Factor Login Auth for IP address "%s", but email sending failed.' ), $oUser->user_login, $sIpAddress );
			$this->addToAuditEntry( $sAuditMessage, 3, 'login_protect_two_factor_email_send_fail' );
		}
		return $this;
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oWp = $this->loadWpUsers();
		$bValidatedProfile = $this->hasValidatedProfile( $oUser );
		$aData = array(
			'user_has_email_authentication_active'   => $bValidatedProfile,
			'user_has_email_authentication_enforced' => $this->isSubjectToEmailAuthentication( $oUser ),
			'is_my_user_profile'                     => ( $oUser->ID == $oWp->getCurrentWpUserId() ),
			'i_am_valid_admin'                       => $this->getCon()->isPluginAdmin(),
			'user_to_edit_is_admin'                  => $oWp->isUserAdmin( $oUser ),
			'strings'                                => array(
				'label_email_authentication'                => _wpsf__( 'Email Authentication' ),
				'title'                                     => _wpsf__( 'Email Authentication' ),
				'description_email_authentication_checkbox' => _wpsf__( 'Check the box to enable email-based login authentication.' ),
				'provided_by'                               => sprintf( _wpsf__( 'Provided by %s' ), $this->getCon()
																										  ->getHumanName() )
			)
		);

		$aData[ 'bools' ] = array(
			'checked'  => $bValidatedProfile || $aData[ 'user_has_email_authentication_enforced' ],
			'disabled' => true || $aData[ 'user_has_email_authentication_enforced' ]
			//TODO: Make email authentication a per-user setting
		);

		echo $this->getMod()->renderTemplate( 'snippets/user_profile_emailauthentication.php', $aData );
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_Email;
	}

	/**
	 * @return string
	 */
	protected function get2FaCodeUserMetaKey() {
		return $this->getMod()->prefix( 'tfaemail_reqid' );
	}
}