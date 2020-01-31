<?php

use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 8.6.0
 */
class ICWP_WPSF_Processor_LoginProtect_BackupCodes extends ICWP_WPSF_Processor_LoginProtect_IntentProviderBase {

	/**
	 * @param \WP_User $oUser
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
			$aFields[] = [
				'name'        => $this->getLoginFormParameter(),
				'type'        => 'text',
				'value'       => '',
				'placeholder' => __( 'Please use your Backup Code to login.', 'wp-simple-firewall' ),
				'text'        => __( 'Login Backup Code', 'wp-simple-firewall' ),
				'help_link'   => '',
			];
		}
		return $aFields;
	}

	/**
	 * Backup codes shouldn't make a user subject to login intent, but only be presented as required
	 * - i.e. they have other MFA options but they can't be used at the moment. So no MFA options =
	 * no need for backup codes
	 * @param bool     $bIsSubjectTo
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function filterUserSubjectToIntent( $bIsSubjectTo, $oUser ) {
		return $bIsSubjectTo;
	}

	/**
	 * @param \WP_User $oUser
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
	 * @param \WP_User $oUser
	 * @param bool     $bIsSuccess
	 */
	protected function auditLogin( $oUser, $bIsSuccess ) {
		$this->getCon()->fireEvent(
			$bIsSuccess ? '2fa_backupcode_verified' : '2fa_backupcode_fail',
			[
				'audit' => [
					'user_login' => $oUser->user_login,
					'method'     => 'Backup Code',
				]
			]
		);
	}

	/**
	 * @param \WP_User $oUser
	 * @param bool     $bIsOtpSuccess
	 * @param bool     $bOtpProvided - whether a OTP was actually provided
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
	 * @param \WP_User $oUser
	 */
	private function sendBackupCodeUsedEmail( $oUser ) {
		$aEmailContent = [
			__( 'This is a quick notice to inform you that your Backup Login code was just used.', 'wp-simple-firewall' ),
			__( "Your WordPress account had only 1 backup login code.", 'wp-simple-firewall' )
			.' '.__( "You must go to your profile and regenerate a new code if you want to use this method again.", 'wp-simple-firewall' ),
			'',
			sprintf( '<strong>%s</strong>', __( 'Login Details', 'wp-simple-firewall' ) ),
			sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), Services::WpGeneral()->getHomeUrl() ),
			sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $oUser->user_login ),
			sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
			'',
			__( 'Thank You.', 'wp-simple-firewall' ),
		];

		$sTitle = sprintf( __( "Notice: %s", 'wp-simple-firewall' ), __( "Backup Login Code Just Used", 'wp-simple-firewall' ) );
		$this->getEmailProcessor()
			 ->sendEmailWithWrap( $oUser->user_email, $sTitle, $aEmailContent );
	}

	/**
	 * @param \WP_User $oUser
	 * @return string
	 */
	protected function genNewSecret( \WP_User $oUser ) {
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