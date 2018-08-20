<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Yubikey', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/loginprotect_intentprovider_base.php' );

class ICWP_WPSF_Processor_LoginProtect_Yubikey extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	const SECRET_LENGTH = 12;
	/**
	 * @const string
	 */
	const YubikeyVerifyApiUrl = 'https://api.yubico.com/wsapi/2.0/verify?id=%s&otp=%s&nonce=%s';

	/**
	 */
	public function run() {
		if ( $this->getIsYubikeyConfigReady() ) {
			parent::run();
		}
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oCon = $this->getController();
		$oWpUsers = $this->loadWpUsers();

		$bValidatedProfile = $this->hasValidatedProfile( $oUser );
		$aData = array(
			'has_validated_profile' => $bValidatedProfile,
			'is_my_user_profile'    => ( $oUser->ID == $oWpUsers->getCurrentWpUserId() ),
			'i_am_valid_admin'      => $oCon->getHasPermissionToManage(),
			'user_to_edit_is_admin' => $oWpUsers->isUserAdmin( $oUser ),
			'strings'               => array(
				'description_otp_code' => _wpsf__( 'This is your unique Yubikey Device ID.' ),
				'description_otp'      => _wpsf__( 'Provide a One Time Password from your Yubikey.' ),
				'description_otp_ext'  => $bValidatedProfile ? _wpsf__( 'This will remove Yubikey Authentication from your account.' ) : _wpsf__( 'This will add Yubikey Authentication to your account.' ),
				'label_enter_code'     => _wpsf__( 'Yubikey ID' ),
				'label_enter_otp'      => _wpsf__( 'Yubikey OTP' ),
				'title'                => _wpsf__( 'Yubikey Authentication' ),
				'cant_add_other_user'  => sprintf( _wpsf__( "Sorry, %s may not be added to another user's account." ), 'Yubikey' ),
				'cant_remove_admins'   => sprintf( _wpsf__( "Sorry, %s may only be removed from another user's account by a Security Administrator." ), _wpsf__( 'Yubikey' ) ),
				'provided_by'          => sprintf( _wpsf__( 'Provided by %s' ), $oCon->getHumanName() ),
				'remove_more_info'     => sprintf( _wpsf__( 'Understand how to remove Google Authenticator' ) )
			),
			'data'                  => array(
				'otp_field_name' => $this->getLoginFormParameter(),
				'secret'         => $this->getSecret( $oUser ),
			)
		);

		echo $this->getMod()->renderTemplate( 'snippets/user_profile_yubikey.php', $aData );
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile,
	 * so we can use "current user" functions.  Otherwise we need to be careful of mixing up users.
	 * @param int $nSavingUserId
	 */
	public function handleUserProfileSubmit( $nSavingUserId ) {
		$oWpUsers = $this->loadWpUsers();
		$oWpNotices = $this->loadWpNotices();

		$oSavingUser = $oWpUsers->getUserById( $nSavingUserId );

		// If it's your own account, you CANT do anything without your OTP (except turn off via email).
		$sOtp = $this->fetchCodeFromRequest();
		$bValidOtp = $this->processOtp( $oSavingUser, $sOtp );

		$sMessageOtpInvalid = _wpsf__( 'One Time Password (OTP) was not valid.' ).' '._wpsf__( 'Please try again.' );

		// At this stage, if the OTP was empty, then we have no further processing to do.
		if ( empty( $sOtp ) ) {
			return;
		}

		if ( !$bValidOtp ) {
			$oWpNotices->addFlashErrorMessage( $sMessageOtpInvalid );
			return;
		}

		// We're trying to validate our OTP to activate
		if ( !$this->hasValidatedProfile( $oSavingUser ) ) {

			$this->setSecret( $oSavingUser, substr( $sOtp, 0, $this->getSecretLength() ) )
				 ->setProfileValidated( $oSavingUser );
			$oWpNotices->addFlashMessage(
				sprintf( _wpsf__( '%s was successfully added to your account.' ),
					_wpsf__( 'Yubikey' )
				)
			);
		}
		else {
			$this->setProfileValidated( $oSavingUser, false )
				 ->resetSecret( $oSavingUser );
			$oWpNotices->addFlashMessage(
				sprintf( _wpsf__( '%s was successfully removed from your account.' ),
					_wpsf__( 'Yubikey' )
				)
			);
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return WP_User|WP_Error
	 */
	public function processLoginAttempt_Filter( $oUser ) {
		return $oUser;
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$oLoginTrack = $this->getLoginTrack();

		$bNeedToCheckThisFactor = $oFO->isChainedAuth() || !$oLoginTrack->hasSuccessfulFactor();
		$bErrorOnFailure = $bNeedToCheckThisFactor && $oLoginTrack->isFinalFactorRemainingToTrack();
		$oLoginTrack->addUnSuccessfulFactor( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );

		if ( !$bNeedToCheckThisFactor || empty( $oUser ) || is_wp_error( $oUser ) ) {
			return $oUser;
		}

		$oError = new WP_Error();
		$sUsername = $oUser->get( 'user_login' );

		$sOneTimePassword = $this->fetchCodeFromRequest();
//			$sApiKey = $this->getOption('yubikey_api_key');

		// check that if we have a list of permitted keys, that the one used is on that list connected with the username.
		$sYubikey12 = substr( $sOneTimePassword, 0, $this->getSecretLength() );
		$fUsernameFound = false; // if username is never found, it means there's no yubikey specified which means we can bypass this authentication method.
		$fFoundMatch = false;
		foreach ( $this->getOption( 'yubikey_unique_keys' ) as $aUsernameYubikeyPair ) {
			if ( isset( $aUsernameYubikeyPair[ $sUsername ] ) ) {
				$fUsernameFound = true;
				if ( $aUsernameYubikeyPair[ $sUsername ] == $sYubikey12 ) {
					$fFoundMatch = true;
					break;
				}
			}
		}

		// If no yubikey-username pair found for given username, we by-pass Yubikey auth.
		if ( !$fUsernameFound ) {
			$sAuditMessage = sprintf( _wpsf__( 'User "%s" logged in without a Yubikey One Time Password because no username-yubikey pair was found for this user.' ), $sUsername );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_bypass' );
			return $oUser;
		}

		// Username was found in the list of key pairs, but the yubikey provided didn't match that username.
		if ( !$fFoundMatch ) {
			$sAuditMessage = sprintf( _wpsf__( 'User "%s" attempted to login but Yubikey ID "%s" used was not in list of authorised keys.' ), $sUsername, $sYubikey12 );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_fail_permitted_id' );

			if ( $bErrorOnFailure ) {
				$oError->add(
					'yubikey_not_allowed',
					sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__( 'The Yubikey provided is not on the list of permitted keys for this user.' ) )
				);
				return $oError;
			}
		}

		if ( $this->processOtp( null, $sOneTimePassword ) ) {
			$sAuditMessage = sprintf( _wpsf__( 'User "%s" successfully logged in using a validated Yubikey One Time Password.' ), $sUsername );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_login_success' );
			$this->getLoginTrack()->addSuccessfulFactor( ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey );
		}
		else {

			$sAuditMessage = sprintf( _wpsf__( 'User "%s" attempted to login but Yubikey One Time Password failed to validate due to invalid Yubi API response.".' ), $sUsername );
			$this->addToAuditEntry( $sAuditMessage, 2, 'login_protect_yubikey_fail_invalid_api_response' );

			$oError->add(
				'yubikey_validate_fail',
				sprintf( _wpsf__( 'ERROR: %s' ), _wpsf__( 'The Yubikey authentication was not validated successfully.' ) )
			);
		}

		return $bErrorOnFailure ? $oError : $oUser;
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOneTimePassword
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOneTimePassword ) {

		$sNonce = md5( uniqid( rand() ) );
		$sUrl = sprintf( self::YubikeyVerifyApiUrl,
			$this->getOption( 'yubikey_app_id' ),
			$sOneTimePassword,
			$sNonce
		);
		$sRawYubiRequest = $this->loadFS()->getUrlContent( $sUrl );

		$bMatchOtpAndNonce = preg_match( '/otp='.$sOneTimePassword.'/', $sRawYubiRequest, $aMatches )
							 && preg_match( '/nonce='.$sNonce.'/', $sRawYubiRequest, $aMatches );

		return $bMatchOtpAndNonce
			   && preg_match( '/status=([a-zA-Z0-9_]+)/', $sRawYubiRequest, $aMatchesStatus )
			   && ( $aMatchesStatus[ 1 ] == 'OK' ); // TODO: in preg_match
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		if ( $bIsSuccess ) {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" successfully logged in using a validated Yubikey One Time Password.' ),
					$oUser->user_login ), 2, 'login_protect_yubikey_login_success'
			);
			$this->doStatIncrement( 'login.yubikey.verified' );
		}
		else {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" failed to verify their identity using Yubikey One Time Password.' ),
					$oUser->user_login ), 2, 'login_protect_yubikey_failed'
			);
			$this->doStatIncrement( 'login.yubikey.failed' );
		}
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
				'placeholder' => _wpsf__( 'Use your Yubikey to generate a new code.' ),
				'value'       => '',
				'text'        => _wpsf__( 'Yubikey OTP' ),
				'help_link'   => 'https://icwp.io/4i'
			);
		}
		return $aFields;
	}

	/**
	 * @return bool
	 */
	protected function getIsYubikeyConfigReady() {
		$sAppId = $this->getOption( 'yubikey_app_id' );
		$sApiKey = $this->getOption( 'yubikey_api_key' );
		return !empty( $sAppId ) && !empty( $sApiKey );
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_Yubikey;
	}

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		return parent::isSecretValid( $sSecret )
			   && preg_match( sprintf( '#^[a-z]{%s}$#i', $this->getSecretLength() ), $sSecret );
	}

	/**
	 * @return int
	 */
	protected function getSecretLength() {
		return self::SECRET_LENGTH;
	}
}