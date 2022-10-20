<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\MfaBackupCodeAdd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\MfaBackupCodeDelete;
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
				'profile_backup_codes_gen' => ActionData::Build( MfaBackupCodeAdd::SLUG ),
				'profile_backup_codes_del' => ActionData::Build( MfaBackupCodeDelete::SLUG ),
			],
		];
	}

	public function getUserProfileFormRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getUserProfileFormRenderData(),
			[
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
			]
		);
	}

	public function getFormField() :array {
		return [
			'slug'        => static::SLUG,
			'name'        => $this->getLoginFormParameter(),
			'type'        => 'text',
			'value'       => '',
			'placeholder' => __( 'Please use your Backup Code to login.', 'wp-simple-firewall' ),
			'text'        => __( 'Login Backup Code', 'wp-simple-firewall' ),
			'help_link'   => '',
		];
	}

	public function hasValidatedProfile() :bool {
		$this->setProfileValidated( $this->hasValidSecret() );
		return parent::hasValidatedProfile();
	}

	/**
	 * @inheritDoc
	 */
	public function postSuccessActions() {
		parent::postSuccessActions();
		$this->remove();
		$this->sendBackupCodeUsedEmail();
		return $this;
	}

	protected function processOtp( string $otp ) :bool {
		return (bool)wp_check_password( str_replace( '-', '', $otp ), $this->getSecret() );
	}

	/**
	 * @return string
	 */
	protected function genNewSecret() {
		return (string)wp_generate_password( 25, false );
	}

	public function isProviderEnabled() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledBackupCodes();
	}

	/**
	 * @param string $secret
	 * @return $this
	 */
	protected function setSecret( $secret ) {
		parent::setSecret( wp_hash_password( $secret ) );
		return $this;
	}

	private function sendBackupCodeUsedEmail() {
		$user = $this->getUser();
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
					 sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $this->getCon()->this_req->ip ),
					 '',
					 __( 'Thank You.', 'wp-simple-firewall' ),
				 ]
			 );
	}
}