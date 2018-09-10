<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_BackupCodes', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/loginprotect_intentprovider_base.php' );

class ICWP_WPSF_Processor_LoginProtect_BackupCodes extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oCon = $this->getController();

		$bValidatedProfile = $this->hasValidatedProfile( $oUser );
		$aData = array(
			'has_validated_profile'            => $bValidatedProfile,
			'user_google_authenticator_secret' => $this->getSecret( $oUser ),
			'is_my_user_profile'               => ( $oUser->ID == $this->loadWpUsers()->getCurrentWpUserId() ),
			'i_am_valid_admin'                 => $oCon->getHasPermissionToManage(),
			'user_to_edit_is_admin'            => $this->loadWpUsers()->isUserAdmin( $oUser ),
			'strings'                          => array(
				'button_gen_code'       => _wpsf__( 'Generate Login Backup Code' ),
				'button_del_code'       => _wpsf__( 'Delete Login Backup Code' ),
				'description_code'      => _wpsf__( 'Click to generate a new login backup code.' ),
				'description_code_ext'  => _wpsf__( 'Generating a new code will replace the existing.' ),
				'description_chart_url' => _wpsf__( 'Use your Google Authenticator app to scan this QR code and enter the one time password below.' ),
				'description_ga_secret' => _wpsf__( 'If you have a problem with scanning the QR code enter this code manually into the app.' ),
				'desc_remove'           => _wpsf__( 'Check the box to remove Google Authenticator login authentication.' ),
				'label_check_to_remove' => sprintf( _wpsf__( 'Remove %s' ), _wpsf__( 'Google Authenticator' ) ),
				'label_enter_code'      => _wpsf__( 'Generate Backup Login Code' ),
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
				sprintf(
					_wpsf__( 'User "%s" verified their identity using Google Authenticator Two-Factor Authentication.' ),
					$oUser->user_login ), 2, 'login_protect_ga_verified'
			);
			$this->doStatIncrement( 'login.googleauthenticator.verified' );
		}
		else {
			$this->addToAuditEntry(
				sprintf(
					_wpsf__( 'User "%s" failed to verify their identity using Google Authenticator Two-Factor Authentication.' ),
					$oUser->user_login ), 2, 'login_protect_ga_failed'
			);
			$this->doStatIncrement( 'login.googleauthenticator.fail' );
		}
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

	/**
	 * @param string $sSecret
	 * @return bool
	 */
	protected function isSecretValid( $sSecret ) {
		return parent::isSecretValid( $sSecret )
			   && preg_match( '#^[a-z0-9]{25}$#i', str_replace( '-', '', $sSecret ) );
	}
}