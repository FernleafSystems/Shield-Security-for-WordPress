<?php

class ICWP_WPSF_Processor_LoginProtect_BackupCodes extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oCon = $this->getCon();

		$bValidatedProfile = $this->hasValidatedProfile( $oUser );
		$aData = array(
			'has_mfa'                          => $this->isUserSubjectToLoginIntent( $oUser ),
			'has_validated_profile'            => $bValidatedProfile,
			'user_google_authenticator_secret' => $this->getSecret( $oUser ),
			'is_my_user_profile'               => ( $oUser->ID == $this->loadWpUsers()->getCurrentWpUserId() ),
			'i_am_valid_admin'                 => $oCon->isPluginAdmin(),
			'user_to_edit_is_admin'            => $this->loadWpUsers()->isUserAdmin( $oUser ),
			'strings'                          => array(
				'button_gen_code'       => _wpsf__( 'Generate ONE-Time Backup 2FA Login Code' ),
				'button_del_code'       => _wpsf__( 'Delete Login Backup Code' ),
				'not_available'         => _wpsf__( 'Backup login codes are not available if you do not have any other two-factor authentication modes active.' ),
				'description_code'      => _wpsf__( 'Click to generate a backup login code for your two-factor authentication.' ),
				'description_code_ext1' => sprintf( '%s: %s',
					_wpsf__( 'Important' ),
					_wpsf__( 'This code will be displayed only once and you may use it to verify your login only once.' )
					.' '._wpsf__( 'Store it somewhere safe.' ) ),
				'description_code_ext2' => _wpsf__( 'Generating a new code will replace your existing code.' ),
				'description_chart_url' => _wpsf__( 'Use your Google Authenticator app to scan this QR code and enter the one time password below.' ),
				'description_ga_secret' => _wpsf__( 'If you have a problem with scanning the QR code enter this code manually into the app.' ),
				'desc_remove'           => _wpsf__( 'Check the box to remove Google Authenticator login authentication.' ),
				'label_check_to_remove' => sprintf( _wpsf__( 'Remove %s' ), _wpsf__( 'Google Authenticator' ) ),
				'label_enter_code'      => _wpsf__( 'Create Backup 2FA Login Code' ),
				'label_ga_secret'       => _wpsf__( 'Manual Code' ),
				'label_scan_qr_code'    => _wpsf__( 'Scan This QR Code' ),
				'title'                 => _wpsf__( 'Backup Login Code' ),
				'cant_add_other_user'   => sprintf( _wpsf__( "Sorry, %s may not be added to another user's account." ), 'Backup Codes' ),
				'cant_remove_admins'    => sprintf( _wpsf__( "Sorry, %s may only be removed from another user's account by a Security Administrator." ), _wpsf__( 'Backup Codes' ) ),
				'provided_by'           => sprintf( _wpsf__( 'Provided by %s' ), $oCon->getHumanName() ),
				'remove_more_info'      => sprintf( _wpsf__( 'Understand how to remove Google Authenticator' ) )
			),
			'data'                             => array(
				'otp_field_name' => $this->getLoginFormParameter()
			)
		);

		echo $this->getMod()->renderTemplate( 'snippets/user_profile_backupcode.php', $aData );
	}

	/**
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserEditProfile( $oUser ) {
		// Allow no actions to be taken on other user profiles
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
				'value'       => '',
				'placeholder' => _wpsf__( 'Please use your Backup Code to login.' ),
				'text'        => _wpsf__( 'Login Backup Code' ),
				'help_link'   => '',
			);
		}
		return $aFields;
	}

	/**
	 * Backup codes shouldn't make a user subject to login intent, but only be presented as required
	 * - i.e. they have other MFA options but they can't be used at the moment. So no MFA options =
	 * no need for backup codes
	 * @param bool    $bIsSubjectTo
	 * @param WP_User $oUser
	 * @return bool
	 */
	public function filterUserSubjectToIntent( $bIsSubjectTo, $oUser ) {
		return $bIsSubjectTo;
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function hasValidatedProfile( $oUser ) {
		return $this->hasValidSecret( $oUser );
	}

	/**
	 * Backup Code are 1-time only and if you have MFA, then we need to remove all the other tracking factors
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	protected function processOtp( $oUser, $sOtpCode ) {
		$bValid = $this->validateBackupCode( $oUser, $sOtpCode );
		if ( $bValid ) {
			$this->deleteSecret( $oUser );
		}
		return $bValid;
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sOtpCode
	 * @return bool
	 */
	private function validateBackupCode( $oUser, $sOtpCode ) {
		return wp_check_password( str_replace( '-', '', $sOtpCode ), $this->getSecret( $oUser ) );
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		if ( $bIsSuccess ) {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" verified their identity using %s method.' ),
					$oUser->user_login, _wpsf__( 'Backup Code' )
				), 2, 'login_protect_bc_verified'
			);
			$this->doStatIncrement( 'login.backupcode.verified' );
		}
		else {
			$this->addToAuditEntry(
				sprintf( _wpsf__( 'User "%s" failed to verify their identity using %s method.' ),
					$oUser->user_login, _wpsf__( 'Backup Code' )
				), 2, 'login_protect_bc_failed'
			);
			$this->doStatIncrement( 'login.backupcode.fail' );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @param bool    $bIsOtpSuccess
	 * @param bool    $bOtpProvided - whether a OTP was actually provided
	 * @return $this
	 */
	protected function postOtpProcessAction( $oUser, $bIsOtpSuccess, $bOtpProvided ) {
		parent::postOtpProcessAction( $oUser, $bIsOtpSuccess, $bOtpProvided );

		if ( $bOtpProvided && $bIsOtpSuccess ) {
			$this->sendBackupCodeUsedEmail( $oUser );
		}
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 */
	private function sendBackupCodeUsedEmail( $oUser ) {
		$aEmailContent = array(
			_wpsf__( 'This is a quick notice to inform you that your Backup Login code was just used.' ),
			_wpsf__( "Your WordPress account had only 1 backup login code." )
			.' '._wpsf__( "You must go to your profile and regenerate a new code if you want to use this method again." ),
			'',
			sprintf( '<strong>%s</strong>', _wpsf__( 'Login Details' ) ),
			sprintf( '%s: %s', _wpsf__( 'URL' ), $this->loadWp()->getHomeUrl() ),
			sprintf( '%s: %s', _wpsf__( 'Username' ), $oUser->user_login ),
			sprintf( '%s: %s', _wpsf__( 'IP Address' ), $this->ip() ),
			'',
			_wpsf__( 'Thank You.' ),
		);

		$sTitle = sprintf( _wpsf__( "Notice: %s" ), _wpsf__( "Backup Login Code Just Used" ) );
		$this->getEmailProcessor()
			 ->sendEmailWithWrap( $oUser->user_email, $sTitle, $aEmailContent );
	}

	/**
	 * @return string
	 */
	protected function genNewSecret() {
		return wp_generate_password( 25, false );
	}

	/**
	 * @param WP_User $oUser
	 * @param string  $sNewSecret
	 * @return $this
	 */
	protected function setSecret( $oUser, $sNewSecret ) {
		parent::setSecret( $oUser, wp_hash_password( $sNewSecret ) );
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getStub() {
		return ICWP_WPSF_Processor_LoginProtect_Track::Factor_BackupCode;
	}
}