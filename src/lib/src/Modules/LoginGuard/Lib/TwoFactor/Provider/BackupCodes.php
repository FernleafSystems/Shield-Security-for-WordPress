<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class BackupCodes extends BaseProvider {

	const SLUG = 'backupcode';
	const STANDALONE = false;

	public function getProviderName() :string {
		return 'Backup Codes';
	}

	public function getJavascriptVars() :array {
		return [
			'ajax' => [
				'gen_backup_codes' => $this->getMod()->getAjaxActionData( 'gen_backup_codes' ),
				'del_backup_codes' => $this->getMod()->getAjaxActionData( 'del_backup_codes' ),
			],
		];
	}

	protected function getProviderSpecificRenderData( \WP_User $user ) :array {
		return [
			'strings' => [
				'button_gen_code'       => __( 'Generate ONE-Time Backup 2FA Login Code', 'wp-simple-firewall' ),
				'button_del_code'       => __( 'Delete Login Backup Code', 'wp-simple-firewall' ),
				'not_available'         => __( 'Backup login codes are not available if you do not have any other two-factor authentication modes active.', 'wp-simple-firewall' ),
				'description_code'      => __( 'Click to generate a backup login code for your two-factor authentication.', 'wp-simple-firewall' ),
				'description_code_ext1' => sprintf( '%s: %s',
					__( 'Important', 'wp-simple-firewall' ),
					__( 'This code will be displayed only once and you may use it to verify your login only once.', 'wp-simple-firewall' )
					.' '.__( 'Store it somewhere safe.', 'wp-simple-firewall' ) ),
				'description_code_ext2' => __( 'Generating a new code will replace your existing code.', 'wp-simple-firewall' ),
				'label_enter_code'      => __( 'Create Backup 2FA Login Code', 'wp-simple-firewall' ),
				'title'                 => __( 'Backup Login Code', 'wp-simple-firewall' ),
				'cant_add_other_user'   => sprintf( __( "Sorry, %s may not be added to another user's account.", 'wp-simple-firewall' ), 'Backup Codes' ),
				'cant_remove_admins'    => sprintf( __( "Sorry, %s may only be removed from another user's account by a Security Administrator.", 'wp-simple-firewall' ), __( 'Backup Codes', 'wp-simple-firewall' ) ),
				'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ), $this->getCon()
																									   ->getHumanName() ),
				'remove_more_info'      => __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' )
			]
		];
	}

	public function getFormField() :array {
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
	 * @param \WP_User $user
	 * @return bool
	 */
	public function hasValidatedProfile( $user ) {
		return $this->hasValidSecret( $user );
	}

	/**
	 * @return $this
	 */
	public function postSuccessActions( \WP_User $user ) {
		$this->deleteSecret( $user );
		$this->sendBackupCodeUsedEmail( $user );
		return $this;
	}

	protected function processOtp( \WP_User $user, string $otp ) :bool {
		return $this->validateBackupCode( $user, $otp );
	}

	/**
	 * @param string   $OTP
	 * @return bool
	 */
	private function validateBackupCode( \WP_User $user, $OTP ) :bool {
		return (bool)wp_check_password( str_replace( '-', '', $OTP ), $this->getSecret( $user ) );
	}

	/**
	 * @return string
	 */
	protected function genNewSecret( \WP_User $user ) {
		return wp_generate_password( 25, false );
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledBackupCodes();
	}

	/**
	 * @param \WP_User $user
	 * @param string   $sNewSecret
	 * @return $this
	 */
	protected function setSecret( $user, $sNewSecret ) {
		parent::setSecret( $user, wp_hash_password( $sNewSecret ) );
		return $this;
	}

	private function sendBackupCodeUsedEmail( \WP_User $user ) {
		$this->getMod()
			 ->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $user->user_email,
				 sprintf( __( "Notice: %s", 'wp-simple-firewall' ), __( "Backup Login Code Just Used", 'wp-simple-firewall' ) ),
				 [
					 __( 'This is a quick notice to inform you that your Backup Login code was just used.', 'wp-simple-firewall' ),
					 __( "Your WordPress account had only 1 backup login code.", 'wp-simple-firewall' )
					 .' '.__( "You must go to your profile and regenerate a new code if you want to use this method again.", 'wp-simple-firewall' ),
					 '',
					 sprintf( '<strong>%s</strong>', __( 'Login Details', 'wp-simple-firewall' ) ),
					 sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), Services::WpGeneral()->getHomeUrl() ),
					 sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
					 sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
					 '',
					 __( 'Thank You.', 'wp-simple-firewall' ),
				 ]
			 );
	}
}