<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaBackupCodeAdd,
	MfaBackupCodeDelete
};
use FernleafSystems\Wordpress\Services\Services;

class BackupCodes extends AbstractShieldProviderMfaDB {

	protected const SLUG = 'backupcode';

	public static function ProviderEnabled() :bool {
		return parent::ProviderEnabled() && self::con()->opts->optIs( 'allow_backupcodes', 'Y' );
	}

	protected function maybeMigrate() :void {
		$meta = self::con()->user_metas->for( $this->getUser() );
		$legacySecret = $meta->backupcode_secret;
		if ( !empty( $legacySecret ) ) {
			$this->removeFromProfile();
			$this->createNewSecretRecord( $legacySecret, 'Backup Code' );
			unset( $meta->backupcode_secret );
			unset( $meta->backupcode_validated );
		}
	}

	public function isProviderStandalone() :bool {
		return false;
	}

	public static function ProviderName() :string {
		return __( 'Backup Codes', 'wp-simple-firewall' );
	}

	public function getJavascriptVars() :array {
		$record = \current( $this->loadMfaRecords() );
		return Services::DataManipulation()->mergeArraysRecursive(
			parent::getJavascriptVars(),
			[
				'ajax'  => [
					'profile_backup_codes_gen' => ActionData::Build( MfaBackupCodeAdd::class ),
					'profile_backup_codes_del' => ActionData::Build( MfaBackupCodeDelete::class ),
				],
				'flags' => [
					'has_backup_code' => !empty( $record ),
				],
			]
		);
	}

	protected function getUserProfileFormRenderData() :array {
		$record = \current( $this->loadMfaRecords() );
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
					'provided_by'           => sprintf( __( 'Provided by %s', 'wp-simple-firewall' ),
						self::con()->labels->Name ),
					'remove_more_info'      => __( 'Understand how to remove Google Authenticator', 'wp-simple-firewall' ),
					'generated_at'          => sprintf( '%s: %s', __( 'Code Generated', 'wp-simple-firewall' ),
						empty( $record ) ? '' : Services::Request()
														->carbon()
														->setTimestamp( $record->created_at )
														->diffForHumans()
					),
				],
			]
		);
	}

	public function getFormField() :array {
		return [
			'slug'        => static::ProviderSlug(),
			'name'        => $this->getLoginIntentFormParameter(),
			'type'        => 'text',
			'value'       => '',
			'placeholder' => __( 'Supply Backup Code', 'wp-simple-firewall' ),
			'text'        => __( 'Backup Code', 'wp-simple-firewall' ),
			'help_link'   => '',
			'description' => __( "When you can't access your 2FA codes.", 'wp-simple-firewall' ),
		];
	}

	public function postSuccessActions() :void {
		parent::postSuccessActions();
		$this->sendBackupCodeUsedEmail();
	}

	protected function processOtp( string $otp ) :bool {
		$valid = false;
		foreach ( $this->loadMfaRecords() as $loadMfaRecord ) {
			if ( wp_check_password( \str_replace( '-', '', $otp ), $loadMfaRecord->unique_id ) ) {
				$valid = true;
				self::con()->db_con->mfa->getQueryDeleter()->deleteRecord( $loadMfaRecord );
			}
		}
		return $valid;
	}

	protected function genNewSecret() :string {
		return wp_generate_password( 25, false );
	}

	public function isProviderEnabled() :bool {
		return static::ProviderEnabled();
	}

	public function resetSecret() :string {
		$this->removeFromProfile();
		$temp = $this->genNewSecret();
		$this->createNewSecretRecord( wp_hash_password( $temp ), 'Backup Code' );
		return $temp;
	}

	private function sendBackupCodeUsedEmail() {
		$user = $this->getUser();
		self::con()->email_con->sendEmailWithWrap(
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
				sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), self::con()->this_req->ip ),
				'',
				__( 'Thank You.', 'wp-simple-firewall' ),
			]
		);
	}
}