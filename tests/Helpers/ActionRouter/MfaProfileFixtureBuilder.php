<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type FixtureState array{
 *   options_snapshot:array<string,mixed>,
 *   created_user_id:int,
 *   mfa_record_ids:list<int>,
 *   target_user_id:int,
 *   user_id:int
 * }
 */
class MfaProfileFixtureBuilder {

	private const OPTION_KEYS = [
		'allow_backupcodes',
		'enable_email_authentication',
		'enable_google_authenticator',
		'enable_passkeys',
		'enable_yubikey',
		'license_activated_at',
		'license_data',
		'license_deactivated_at',
		'mfa_user_setup_pages',
		'yubikey_api_key',
		'yubikey_app_id',
	];

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$userID = RuntimeTestState::loginAsSecurityAdmin();
		RuntimeTestState::ensureDb( [ 'mfa' ] );

		$state = [
			'created_user_id'  => 0,
			'options_snapshot' => RuntimeTestState::snapshotOptions( self::OPTION_KEYS ),
			'mfa_record_ids'   => [],
			'target_user_id'   => 0,
			'user_id'          => $userID,
		];

		try {
			RuntimeTestState::applyPremiumCapabilities( [
				'2fa_login_backup_codes',
				'2fa_multi_yubikey',
				'2fa_webauthn',
			] );
			RuntimeTestState::controller()->opts
				->optSet( 'allow_backupcodes', 'Y' )
				->optSet( 'enable_email_authentication', 'Y' )
				->optSet( 'enable_yubikey', 'Y' )
				->optSet( 'mfa_user_setup_pages', [ 'profile', 'dedicated' ] )
				->optSet( 'yubikey_app_id', '12345' )
				->optSet( 'yubikey_api_key', 'browser-fixture-yubikey-key' )
				->store();
			RuntimeTestState::forcePersistOptions( [
				'allow_backupcodes'           => 'Y',
				'enable_email_authentication' => 'Y',
				'enable_yubikey'              => 'Y',
				'mfa_user_setup_pages'        => [ 'profile', 'dedicated' ],
				'yubikey_app_id'              => '12345',
				'yubikey_api_key'             => 'browser-fixture-yubikey-key',
			] );

			$state[ 'mfa_record_ids' ][] = TestDataFactory::insertMfaRecord(
				$userID,
				'backupcode',
				[],
				[
					'label'     => 'Browser Backup Code',
					'unique_id' => \wp_hash_password( 'browser-fixture-backup-code' ),
				]
			);
			$state[ 'mfa_record_ids' ][] = TestDataFactory::insertMfaRecord(
				$userID,
				'yubi',
				[],
				[
					'label'     => 'Browser Yubikey',
					'unique_id' => 'cccccccccccc',
				]
			);
			$targetUserID = $this->createTargetUser();
			$state[ 'created_user_id' ] = $targetUserID;
			$state[ 'target_user_id' ] = $targetUserID;
			$state[ 'mfa_record_ids' ][] = TestDataFactory::insertMfaRecord(
				$targetUserID,
				'yubi',
				[],
				[
					'label'     => 'Browser Target Yubikey',
					'unique_id' => 'dddddddddddd',
				]
			);
			$this->clearMfaCache( $userID );
			$this->clearMfaCache( $targetUserID );

			return [
				'contract' => [
					'user_id'      => $userID,
					'profile_path' => '/wp-admin/profile.php',
					'edit_path'    => '/wp-admin/user-edit.php?user_id='.$targetUserID,
				],
				'state'    => $state,
			];
		}
		catch ( \Throwable $throwable ) {
			$this->cleanup( $state );
			throw $throwable;
		}
	}

	/**
	 * @phpstan-param FixtureState $state
	 */
	public function cleanup( array $state ) :void {
		RuntimeTestState::ensureDb( [ 'mfa' ] );
		$con = RuntimeTestState::controller();

		foreach ( \is_array( $state[ 'mfa_record_ids' ] ?? null ) ? $state[ 'mfa_record_ids' ] : [] as $recordID ) {
			$recordID = (int)$recordID;
			if ( $recordID > 0 ) {
				$record = $con->db_con->mfa->getQuerySelector()->byId( $recordID );
				if ( $record ) {
					$con->db_con->mfa->getQueryDeleter()->deleteRecord( $record );
				}
			}
		}

		$userID = (int)( $state[ 'user_id' ] ?? 0 );
		if ( $userID > 0 ) {
			$user = Services::WpUsers()->getUserById( $userID );
			if ( $user instanceof \WP_User ) {
				$meta = RuntimeTestState::controller()->user_metas->for( $user );
				$meta->sms_registration = [];
				$this->clearMfaCache( $userID );
			}
		}
		$targetUserID = (int)( $state[ 'target_user_id' ] ?? 0 );
		if ( $targetUserID > 0 ) {
			$this->clearMfaCache( $targetUserID );
		}
		$createdUserID = (int)( $state[ 'created_user_id' ] ?? 0 );
		if ( $createdUserID > 0 ) {
			if ( !\function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH.'wp-admin/includes/user.php';
			}
			\wp_delete_user( $createdUserID );
		}

		RuntimeTestState::restoreOptions(
			\is_array( $state[ 'options_snapshot' ] ?? null ) ? $state[ 'options_snapshot' ] : []
		);
		\wp_set_current_user( 0 );
		RuntimeTestState::controller()->this_req->is_security_admin = false;
	}

	private function clearMfaCache( int $userID ) :void {
		$user = Services::WpUsers()->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			( new MfaRecordsHandler() )->clearForUser( $user );
		}
	}

	private function createTargetUser() :int {
		$userID = \wp_insert_user( [
			'user_login' => 'shield-browser-mfa-target-'.\wp_generate_password( 8, false ),
			'user_email' => 'shield-browser-mfa-target-'.\wp_generate_password( 8, false ).'@example.com',
			'user_pass'  => \wp_generate_password( 24, true ),
			'role'       => 'subscriber',
		] );
		if ( \is_wp_error( $userID ) ) {
			throw new \RuntimeException( $userID->get_error_message() );
		}

		return (int)$userID;
	}
}
