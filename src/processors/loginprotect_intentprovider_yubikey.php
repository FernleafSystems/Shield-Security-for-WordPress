<?php

class ICWP_WPSF_Processor_LoginProtect_Yubikey extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	const OTP_LENGTH = 12;
	/**
	 * @const string
	 */
	const URL_YUBIKEY_VERIFY = 'https://api.yubico.com/wsapi/2.0/verify';

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oCon = $this->getCon();
		$oWpUsers = $this->loadWpUsers();

		$bValidatedProfile = $this->hasValidatedProfile( $oUser );
		$aData = array(
			'has_validated_profile' => $bValidatedProfile,
			'is_my_user_profile'    => ( $oUser->ID == $oWpUsers->getCurrentWpUserId() ),
			'i_am_valid_admin'      => $oCon->isPluginAdmin(),
			'user_to_edit_is_admin' => $oWpUsers->isUserAdmin( $oUser ),
			'strings'               => array(
				'description_otp_code'     => _wpsf__( 'This is your unique Yubikey Device ID.' ),
				'description_otp_code_ext' => '['._wpsf__( 'Pro Only' ).'] '
											  ._wpsf__( 'Multiple Yubikey Device IDs are separated by a comma.' ),
				'description_otp'          => _wpsf__( 'Provide a One Time Password from your Yubikey.' ),
				'description_otp_ext'      => $bValidatedProfile ?
					_wpsf__( 'This will remove the Yubikey Device ID from your profile.' )
					: _wpsf__( 'This will add the Yubikey Device ID to your profile.' ),
				'description_otp_ext_2'    => $bValidatedProfile ?
					'['._wpsf__( 'Pro Only' ).'] '._wpsf__( 'If you provide a OTP from an alternative Yubikey device, it will also be added to your profile.' )
					: '',
				'label_enter_code'         => _wpsf__( 'Yubikey ID' ),
				'label_enter_otp'          => _wpsf__( 'Yubikey OTP' ),
				'title'                    => _wpsf__( 'Yubikey Authentication' ),
				'cant_add_other_user'      => sprintf( _wpsf__( "Sorry, %s may not be added to another user's account." ), 'Yubikey' ),
				'cant_remove_admins'       => sprintf( _wpsf__( "Sorry, %s may only be removed from another user's account by a Security Administrator." ), _wpsf__( 'Yubikey' ) ),
				'provided_by'              => sprintf( _wpsf__( 'Provided by %s' ), $oCon->getHumanName() ),
				'remove_more_info'         => sprintf( _wpsf__( 'Understand how to remove Google Authenticator' ) )
			),
			'data'                  => array(
				'otp_field_name' => $this->getLoginFormParameter(),
				'secret'         => str_replace( ',', ', ', $this->getSecret( $oUser ) ),
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

		// If it's your own account, you CANT do anything without your OTP (except turn off via email).
		$sOtp = $this->fetchCodeFromRequest();

		// At this stage, if the OTP was empty, then we have no further processing to do.
		if ( empty( $sOtp ) ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( !$this->sendYubiOtpRequest( $sOtp ) ) {
			$oFO->setFlashAdminNotice(
				_wpsf__( 'One Time Password (OTP) was not valid.' ).' '._wpsf__( 'Please try again.' ),
				true
			);
			return;
		}

		/*
		 * How we proceed depends on :
		 * 1) Is the OTP for a registered ID - if so, remove it; If not, add it;
		 * 2) Is this a premium Shield installation - if so, multiple yubikeys are permitted
		 */

		$oSavingUser = $this->loadWpUsers()->getUserById( $nSavingUserId );
		$sYubiId = $this->getYubiIdFromOtp( $sOtp );

		$bError = false;
		if ( $this->hasYubiIdInProfile( $oSavingUser, $sYubiId ) ) {
			$this->removeYubiIdFromProfile( $oSavingUser, $sYubiId );
			$sMsg = sprintf(
				_wpsf__( '%s was removed from your profile.' ),
				_wpsf__( 'Yubikey Device' ).sprintf( ' "%s"', $sYubiId )
			);
		}
		else if ( count( $this->getYubiIds( $oSavingUser ) ) == 0 || $oFO->isPremium() ) {
			$this->addYubiIdToProfile( $oSavingUser, $sYubiId );
			$sMsg = sprintf(
				_wpsf__( '%s was added to your profile.' ),
				_wpsf__( 'Yubikey Device' ).sprintf( ' (%s)', $sYubiId )
			);
		}
		else {
			$bError = true;
			$sMsg = _wpsf__( 'No changes were made to your Yubikey configuration' );
		}

		$this->setProfileValidated( $oSavingUser, $this->hasValidSecret( $oSavingUser ) );
		$oFO->setFlashAdminNotice( $sMsg, $bError );
	}

	/**
	 * @param WP_User $oUser
	 * @return array
	 */
	protected function getYubiIds( WP_User $oUser ) {
		return explode( ',', parent::getSecret( $oUser ) );
	}

	/**
	 * @param string $sOTP
	 * @return string
	 */
	protected function getYubiIdFromOtp( $sOTP ) {
		return substr( $sOTP, 0, $this->getYubiOtpLength() );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sKey
	 * @return bool
	 */
	protected function hasYubiIdInProfile( WP_User $oUser, $sKey ) {
		return in_array( $sKey, $this->getYubiIds( $oUser ) );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOneTimePassword
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOneTimePassword ) {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();
		$bSuccess = false;

		$aYubiKeys = $this->getYubiIds( $oUser );

		// Only process the 1st secret if premium
		if ( !$oFO->isPremium() ) {
			$aYubiKeys = array_slice( $aYubiKeys, 0, 1 );
		}

		foreach ( $aYubiKeys as $sKey ) {
			$bSuccess = strpos( $sOneTimePassword, $sKey ) === 0
						&& $this->sendYubiOtpRequest( $sOneTimePassword );
			if ( $bSuccess ) {
				break;
			}
		}

		return $bSuccess;
	}

	/**
	 * @param string $sOTP
	 * @return bool
	 */
	private function sendYubiOtpRequest( $sOTP ) {
		$sOTP = trim( $sOTP );
		$bSuccess = preg_match( '#^[a-z]{44}$#', $sOTP );

		if ( $bSuccess ) {
			$aParts = array(
				'otp'   => $sOTP,
				'nonce' => md5( uniqid( rand() ) ),
				'id'    => $this->getOption( 'yubikey_app_id' )
			);
			$sYubiResponse = trim( $this->loadFS()
										->getUrlContent( add_query_arg( $aParts, self::URL_YUBIKEY_VERIFY ) ) );

			unset( $aParts[ 'id' ] );
			$aParts[ 'status' ] = 'OK';

			$bSuccess = true;
			foreach ( $aParts as $sKey => $mVal ) {
				$bSuccess = $bSuccess && preg_match( sprintf( '#%s=%s#', $sKey, $mVal ), $sYubiResponse );
			}
		}

		return $bSuccess;
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sNewKey
	 * @return $this
	 */
	protected function addYubiIdToProfile( $oUser, $sNewKey ) {
		$aKeys = $this->getYubiIds( $oUser );
		$aKeys[] = $sNewKey;
		return $this->storeYubiIdInProfile( $oUser, $aKeys );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sKey
	 * @return $this
	 */
	protected function removeYubiIdFromProfile( $oUser, $sKey ) {
		$aKeys = $this->loadDP()->removeFromArrayByValue( $this->getYubiIds( $oUser ), $sKey );
		return $this->storeYubiIdInProfile( $oUser, $aKeys );
	}

	/**
	 * @param WP_User $oUser
	 * @param array   $aKeys
	 * @return $this
	 */
	private function storeYubiIdInProfile( $oUser, $aKeys ) {
		parent::setSecret( $oUser, implode( ',', array_unique( array_filter( $aKeys ) ) ) );
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		if ( $bIsSuccess ) {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" verified their identity using %s method.' ),
					$oUser->user_login, _wpsf__( 'Yubikey OTP' )
				), 2, 'login_protect_yubikey_login_success'
			);
			$this->doStatIncrement( 'login.yubikey.verified' );
		}
		else {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" failed to verify their identity using %s method.' ),
					$oUser->user_login, _wpsf__( 'Yubikey OTP' )
				),2, 'login_protect_yubikey_failed'
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
		$bValid = parent::isSecretValid( $sSecret );
		if ( $bValid ) {
			foreach ( explode( ',', $sSecret ) as $sId ) {
				$bValid = $bValid && preg_match( sprintf( '#^[a-z]{%s}$#', $this->getYubiOtpLength() ), $sId );
			}
		}
		return $bValid;
	}

	/**
	 * @return int
	 */
	protected function getYubiOtpLength() {
		return self::OTP_LENGTH;
	}
}