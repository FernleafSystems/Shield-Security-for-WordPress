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