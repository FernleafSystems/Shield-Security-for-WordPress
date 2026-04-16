<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use Dolondro\GoogleAuthenticator\GoogleAuthenticator;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import\{
	ImportController,
	SupplierBridgeInterface,
	SupplierFactorData,
	WordpressTwoFactorBridge
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	LoginIntentRequestValidate,
	LoginRequestCapture
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\{
	BackupCodes,
	GoogleAuth
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class ThirdPartyMfaImportIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $optsSnapshot = [];

	private array $requestSnapshot = [];

	/** @var mixed */
	private $originalSiteEnabledProviders;

	private array $originalActivePlugins = [];

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->enablePremiumCapabilities( [ '2fa_login_backup_codes' ] );

		$this->optsSnapshot = $this->snapshotSelectedOptions( [
			'enable_google_authenticator',
			'allow_backupcodes',
			'enable_email_authentication',
			'email_any_user_set',
			'email_can_send_verified_at',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->originalSiteEnabledProviders = get_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, null );
		$this->originalActivePlugins = \is_array( get_option( 'active_plugins', [] ) ) ? get_option( 'active_plugins', [] ) : [];

		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
			'allow_backupcodes'           => 'Y',
			'enable_email_authentication' => 'Y',
			'email_any_user_set'          => 'Y',
			'email_can_send_verified_at'  => \time(),
		], true );

		\update_option( 'active_plugins', [] );
		\delete_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
	}

	public function tear_down() :void {
		if ( $this->isControllerConfigReady() ) {
			$this->restoreSelectedOptions( $this->optsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}

		\update_option( 'active_plugins', $this->originalActivePlugins );
		if ( $this->originalSiteEnabledProviders === null ) {
			\delete_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS );
		}
		else {
			\update_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, $this->originalSiteEnabledProviders );
		}

		parent::tear_down();
	}

	public function test_import_for_user_copies_supported_source_factors() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$totpSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
		$rawBackupCodes = [ 'alpha1234', 'bravo5678' ];
		$backupCodeHashes = \array_map( 'wp_hash_password', $rawBackupCodes );

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			$totpSecret,
			$backupCodeHashes
		);

		$results = ( new ImportController() )->importForUser( $user );
		$result = $results[ 0 ];

		$this->assertTrue( $result->checked );
		$this->assertEqualsCanonicalizing( [ 'ga', 'email', 'backupcode' ], $result->importedFactorSlugs );
		$this->assertTrue( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );

		$gaRecords = $this->loadRecordsForSlug( $user->ID, 'ga' );
		$backupRecords = $this->loadRecordsForSlug( $user->ID, 'backupcode' );
		$emailRecords = $this->loadRecordsForSlug( $user->ID, 'email' );

		$this->assertCount( 1, $gaRecords );
		$this->assertCount( 2, $backupRecords );
		$this->assertCount( 0, $emailRecords );
		$this->assertSame( $totpSecret, $gaRecords[ 0 ]->unique_id );
		$this->assertEqualsCanonicalizing(
			$backupCodeHashes,
			\array_values( \array_map( static fn( $record ) => $record->unique_id, $backupRecords ) ),
			'Imported backup codes should be stored as Shield MFA hashes.'
		);

		$flags = $this->requireController()->user_metas->for( $user )->flags;
		$this->assertEqualsCanonicalizing(
			[ 'ga', 'email', 'backupcode' ],
			$flags[ 'mfa_import' ][ 'suppliers' ][ 'wordpress_two_factor' ][ 'imported' ] ?? []
		);
		$this->assertGreaterThan( 0, (int)( $flags[ 'mfa_import' ][ 'suppliers' ][ 'wordpress_two_factor' ][ 'checked_at' ] ?? 0 ) );
	}

	public function test_imported_ga_secret_validates_through_shield_login_flow() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$totpSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

		$this->seedWordpressTwoFactorState( $user, [ 'Two_Factor_Totp' ], $totpSecret, [] );
		( new ImportController() )->importForUser( $user );

		$provider = new GoogleAuth( $user );
		$this->seedLoginIntent( $user, 'fixture-ga-login' );
		$this->mergeCurrentRequestTransport( [
			$provider->getLoginIntentFormParameter() => ( new GoogleAuthenticator() )->calculateCode( $totpSecret ),
		] );

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'fixture-ga-login' );

		$this->assertSame( GoogleAuth::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertGreaterThan( 0, (int)$this->requireController()->user_metas->for( $user )->record->last_2fa_verified_at );
	}

	public function test_imported_backup_code_hashes_validate_through_shield_login_flow() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$rawBackupCode = 'charlie2468';

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( $rawBackupCode ) ]
		);
		( new ImportController() )->importForUser( $user );

		$provider = new BackupCodes( $user );
		$this->seedLoginIntent( $user, 'fixture-backup-login' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			$provider->getLoginIntentFormParameter() => $rawBackupCode,
		] );
		$this->assertCount( 1, $this->loadRecordsForSlug( $user->ID, BackupCodes::ProviderSlug() ) );
		$this->assertArrayHasKey( GoogleAuth::ProviderSlug(), $this->requireController()->comps->mfa->getProvidersActiveForUser( $user ) );
		$this->assertArrayHasKey( BackupCodes::ProviderSlug(), $this->requireController()->comps->mfa->getProvidersActiveForUser( $user ) );
		$this->assertSame( $rawBackupCode, \FernleafSystems\Wordpress\Services\Services::Request()->request( $provider->getLoginIntentFormParameter() ) );

		$this->startLocalEmailCapture();
		try {
			$validatedSlug = ( new LoginIntentRequestValidate() )
				->setWpUser( $user )
				->run( 'fixture-backup-login' );
		}
		finally {
			$this->stopLocalEmailCapture();
		}

		$this->assertSame( BackupCodes::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertGreaterThan( 0, (int)$this->requireController()->user_metas->for( $user )->record->last_2fa_verified_at );
		$this->assertCount( 0, $this->loadRecordsForSlug( $user->ID, BackupCodes::ProviderSlug() ) );
		$this->assertCount( 1, $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() ) );
	}

	public function test_import_is_idempotent_and_does_not_overwrite_existing_shield_factors() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$shieldSecret = 'JBSWY3DPEHPK3PXP';
		$shieldBackupHash = \wp_hash_password( 'existing001' );

		TestDataFactory::insertMfaRecord( $userId, 'ga', [], [
			'unique_id' => $shieldSecret,
			'label'     => 'Existing GA',
		] );
		TestDataFactory::insertMfaRecord( $userId, 'backupcode', [], [
			'unique_id' => $shieldBackupHash,
			'label'     => 'Existing Backup',
		] );
		$this->requireController()->user_metas->for( $user )->email_2fa_enabled = true;

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'delta1111' ) ]
		);

		$controller = new ImportController();
		$controller->importForUser( $user );
		$controller->importForUser( $user );

		$gaRecords = $this->loadRecordsForSlug( $userId, 'ga' );
		$backupRecords = $this->loadRecordsForSlug( $userId, 'backupcode' );

		$this->assertCount( 1, $gaRecords );
		$this->assertCount( 1, $backupRecords );
		$this->assertSame( $shieldSecret, $gaRecords[ 0 ]->unique_id );
		$this->assertSame( $shieldBackupHash, $backupRecords[ 0 ]->unique_id );
		$this->assertTrue( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
	}

	public function test_import_skips_when_source_plugin_is_active() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		\update_option( 'active_plugins', [ WordpressTwoFactorBridge::ACTIVE_PLUGIN_FILE ] );
		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'echo1357' ) ]
		);

		$results = ( new ImportController() )->importForUser( $user );

		$this->assertFalse( $results[ 0 ]->checked );
		$this->assertEmpty( $results[ 0 ]->importedFactorSlugs );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'backupcode' ) );
		$this->assertFalse( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
		$this->assertEmpty( $this->requireController()->user_metas->for( $user )->flags[ 'mfa_import' ][ 'suppliers' ] ?? [] );
	}

	public function test_import_respects_site_enabled_provider_subset() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		\update_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, [ 'Two_Factor_Totp' ] );
		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'golf8642' ) ]
		);

		$results = ( new ImportController() )->importForUser( $user );
		$result = $results[ 0 ];

		$this->assertTrue( $result->checked );
		$this->assertSame( [ 'ga' ], $result->importedFactorSlugs );
		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'backupcode' ) );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'email' ) );
		$this->assertFalse( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );

		$flags = $this->requireController()->user_metas->for( $user )->flags;
		$this->assertGreaterThan( 0, (int)( $flags[ 'mfa_import' ][ 'suppliers' ][ 'wordpress_two_factor' ][ 'checked_at' ] ?? 0 ) );
		$this->assertSame( [ 'ga' ], $flags[ 'mfa_import' ][ 'suppliers' ][ 'wordpress_two_factor' ][ 'imported' ] ?? [] );
	}

	public function test_email_only_import_sets_flag_without_creating_email_mfa_rows() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedWordpressTwoFactorState( $user, [ 'Two_Factor_Email' ], '', [] );

		$results = ( new ImportController() )->importForUser( $user );
		$result = $results[ 0 ];

		$this->assertTrue( $result->checked );
		$this->assertSame( [ 'email' ], $result->importedFactorSlugs );
		$this->assertTrue( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'email' ) );
		$this->assertArrayHasKey( 'email', $this->requireController()->comps->mfa->getProvidersActiveForUser( $user ) );
		$this->assertTrue( $this->requireController()->comps->mfa->isSubjectToLoginIntent( $user ) );
	}

	public function test_multiple_supplier_bridges_only_fill_missing_factor_slots() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$firstBridge = new class() implements SupplierBridgeInterface {
			public function getSupplierSlug() :string {
				return 'bridge_one';
			}

			public function isApplicable() :bool {
				return true;
			}

			public function discoverForUser( \WP_User $user ) :SupplierFactorData {
				$data = new SupplierFactorData();
				$data->hasSourceState = true;
				$data->gaSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
				$data->emailEnabled = true;
				return $data;
			}
		};
		$secondBridge = new class() implements SupplierBridgeInterface {
			public function getSupplierSlug() :string {
				return 'bridge_two';
			}

			public function isApplicable() :bool {
				return true;
			}

			public function discoverForUser( \WP_User $user ) :SupplierFactorData {
				$data = new SupplierFactorData();
				$data->hasSourceState = true;
				$data->gaSecret = 'KRUGS4ZANFZSAYJANRSWC43FMNZGK5DS';
				$data->emailEnabled = true;
				$data->backupCodeHashes = [ \wp_hash_password( 'foxtrot987' ) ];
				return $data;
			}
		};

		$results = ( new ImportController( [ $firstBridge, $secondBridge ] ) )->importForUser( $user );

		$this->assertSame( [ 'ga', 'email' ], $results[ 0 ]->importedFactorSlugs );
		$this->assertSame( [ 'backupcode' ], $results[ 1 ]->importedFactorSlugs );
		$this->assertSame( 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', $this->loadRecordsForSlug( $userId, 'ga' )[ 0 ]->unique_id );
		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, 'backupcode' ) );
		$this->assertTrue( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
	}

	public function test_first_login_capture_imports_before_subject_check() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[]
		);

		$method = new \ReflectionMethod( LoginRequestCapture::class, 'captureLogin' );
		$method->setAccessible( true );
		\add_filter( 'shield/2fa_skip', '__return_true' );

		try {
			$method->invoke( new LoginRequestCapture(), $user );
		}
		finally {
			\remove_filter( 'shield/2fa_skip', '__return_true' );
		}

		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertTrue( $this->requireController()->comps->mfa->isSubjectToLoginIntent( $user ) );
	}

	private function seedWordpressTwoFactorState(
		\WP_User $user,
		array $enabledProviders,
		string $totpSecret,
		array $backupCodeHashes
	) :void {
		\update_user_meta( $user->ID, WordpressTwoFactorBridge::META_ENABLED_PROVIDERS, $enabledProviders );

		if ( $totpSecret === '' ) {
			\delete_user_meta( $user->ID, WordpressTwoFactorBridge::META_TOTP_SECRET );
		}
		else {
			\update_user_meta( $user->ID, WordpressTwoFactorBridge::META_TOTP_SECRET, $totpSecret );
		}

		if ( empty( $backupCodeHashes ) ) {
			\delete_user_meta( $user->ID, WordpressTwoFactorBridge::META_BACKUP_CODES );
		}
		else {
			\update_user_meta( $user->ID, WordpressTwoFactorBridge::META_BACKUP_CODES, $backupCodeHashes );
		}
	}

	private function seedLoginIntent( \WP_User $user, string $plainNonce ) :void {
		$hash = \wp_hash_password( $plainNonce.$user->ID );
		$this->requireController()->user_metas->for( $user )->login_intents = [
			$hash => [
				'hash'     => $hash,
				'start'    => \time(),
				'attempts' => 0,
			],
		];
	}

	private function loadRecordsForSlug( int $userId, string $slug ) :array {
		return \array_values( \array_filter(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult(),
			static fn( $record ) => $record->slug === $slug
		) );
	}
}
