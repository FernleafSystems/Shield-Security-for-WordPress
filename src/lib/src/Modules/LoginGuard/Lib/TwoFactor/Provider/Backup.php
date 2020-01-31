<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class Backup extends BaseProvider {

	const SLUG = 'backupcode';

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param \WP_User $oUser
	 */
	public function addOptionsToUserProfile( $oUser ) {
		$oCon = $this->getCon();

		$aData = [
			'has_validated_profile'            => $this->hasValidatedProfile( $oUser ),
			'user_google_authenticator_secret' => $this->getSecret( $oUser ),
			'is_my_user_profile'               => ( $oUser->ID == Services::WpUsers()->getCurrentWpUserId() ),
			'i_am_valid_admin'                 => $oCon->isPluginAdmin(),
			'user_to_edit_is_admin'            => Services::WpUsers()->isUserAdmin( $oUser ),
			'strings'                          => [
				'button_gen_code'       => __( 'Generate ONE-Time Backup 2FA Login Code', 'wp-simple-firewall' ),
				'button_del_code'       => __( 'Delete Login Backup Code', 'wp-simple-firewall' ),
				'not_available'         => __( 'Backup login codes are not available if you do not have any other two-factor authentication modes active.', 'wp-simple-firewall' ),
				'description_code'      => __( 'Click to generate a backup login code for your two-factor authentication.', 'wp-simple-firewall' ),
				'description_code_ext1' => sprintf( '%s: %s',
					__( 'Important', 'wp-simple-firewall' ),
					__( 'This code will be displayed only once and you may use it to verify your login only once.', 'wp-simple-firewall' )
					.' '.__( 'Store it somewhere safe.', 'wp-simple-firewall' ) ),
				'description_code_ext2' => __( 'Generating a new code will replace your existing code.', 'wp-simple-firewall' ),
				'description_chart_url' => __( 'Use your Google Authenticator app to scan this QR code and enter the one time password below.', 'wp-simple-firewall' ),
				'description_ga_secret' => __( 'If you have a problem with scanning the QR code enter this code manually into the app.', 'wp-simple-firewall' ),
				'desc_remove'           => __( 'Check the box to remove Google Authenticator login authentication.', 'wp-simple-firewall' ),
				'label_check_to_remove' => sprintf( __( 'Remove %s', 'wp-simple-firewall' ), __( 'Google Authenticator', 'wp-simple-firewall' ) ),
				'label_enter_code'      => __( 'Create Backup 2FA Login Code', 'wp-simple-firewall' ),
				'label_ga_secret'       => __( 'Manual Code', 'wp-simple-firewall' ),
				'label_scan_qr_code'    => __( 'Scan This QR Code', 'wp-simple-firewall' ),
				'title'                 => __( 'Backup Login Code', 'wp-simple-firewall' ),
				'cant_add_other_user'   => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Backup Codes' ),
				'cant_remove_admins'    => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Backup Codes', 'wp-simple-firewall' ) ),
				'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $oCon->getHumanName() ),
				'remove_more_info'      => sprintf( __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' ) )
			],
			'data'                             => [
				'otp_field_name' => $this->getLoginFormParameter()
			]
		];

		return $this->getMod()
					->renderTemplate(
						'/snippets/user/profile/mfa/mfa_backup.twig',
						$aData,
						true
					);
	}

	/**
	 * @param \WP_User $oUser
	 */
	public function addOptionsToUserEditProfile( $oUser ) {
		// Allow no actions to be taken on other user profiles
	}

	/**
	 * @return array
	 */
	public function getFormField() {
		return [
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => '',
			'placeholder' => __( 'Please use your Backup Code to login.', 'wp-simple-firewall' ),
			'text'        => __( 'Login Backup Code', 'wp-simple-firewall' ),
			'help_link'   => '',
		];
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function hasValidatedProfile( $oUser ) {
		return $this->hasValidSecret( $oUser );
	}

	/**
	 * Backup Code are 1-time only and if you have MFA, then we need to remove all the other tracking factors
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
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
	 * @param \WP_User $oUser
	 * @param string   $sOtpCode
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
		$this->getMod()
			 ->getEmailProcessor()
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
	 * @param \WP_User $oUser
	 * @param string   $sNewSecret
	 * @return $this
	 */
	protected function setSecret( $oUser, $sNewSecret ) {
		parent::setSecret( $oUser, wp_hash_password( $sNewSecret ) );
		return $this;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	public function isProviderAvailable( \WP_User $oUser ) {
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isEnabledBackupCodes();
	}
}