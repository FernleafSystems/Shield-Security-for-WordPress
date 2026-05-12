<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use Base32\Base32;
use Dolondro\GoogleAuthenticator\GoogleAuthenticator;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import\{
	ImportController,
	ImportQueue,
	ImportUserProcessor,
	ProcessUserPage,
	SolidSecurityBridge,
	WordfenceLoginSecurityBridge,
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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class ThirdPartyMfaImportIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $optsSnapshot = [];

	private array $requestSnapshot = [];

	/** @var mixed */
	private $originalSiteEnabledProviders;

	/** @var mixed */
	private $originalItsecStorage;

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->enablePremiumCapabilities( [ '2fa_login_backup_codes' ] );
		$this->clearThirdPartyMfaFixtureState();
		$this->resetMfaProviderCache();

		$this->optsSnapshot = $this->snapshotSelectedOptions( [
			'enable_google_authenticator',
			'allow_backupcodes',
			'enable_email_authentication',
			'email_any_user_set',
			'email_can_send_verified_at',
			ImportController::OPT_RUN_STATE,
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->originalSiteEnabledProviders = get_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, null );
		$this->originalItsecStorage = get_site_option( 'itsec-storage', null );

		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
			'allow_backupcodes'           => 'Y',
			'enable_email_authentication' => 'Y',
			'email_any_user_set'          => 'Y',
			'email_can_send_verified_at'  => \time(),
			ImportController::OPT_RUN_STATE => [],
		], true );

		\delete_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS );
		\delete_site_option( 'itsec-storage' );
		$this->createWordfenceLoginSecuritySecretsTable();
		$this->resetImportRuntime();
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
		\add_filter( 'pre_http_request', [ $this, 'interceptImportQueueDispatch' ], 10, 3 );
	}

	public function tear_down() :void {
		\remove_filter( 'pre_http_request', [ $this, 'interceptImportQueueDispatch' ], 10 );
		$this->resetImportRuntime();
		$this->clearThirdPartyMfaFixtureState();
		$this->resetMfaProviderCache();

		if ( $this->isControllerConfigReady() ) {
			$this->restoreSelectedOptions( $this->optsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}

		if ( $this->originalSiteEnabledProviders === null ) {
			\delete_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS );
		}
		else {
			\update_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, $this->originalSiteEnabledProviders );
		}
		if ( $this->originalItsecStorage === null ) {
			\delete_site_option( 'itsec-storage' );
		}
		else {
			\update_site_option( 'itsec-storage', $this->originalItsecStorage );
		}
		$this->dropWordfenceLoginSecuritySecretsTable();

		parent::tear_down();
	}

	public function test_start_import_run_rejects_unknown_supplier() :void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Unrecognised MFA import supplier.' );

		( new ImportController() )->startImportRun( 'unknown_supplier' );
	}

	public function test_start_import_run_rejects_while_active() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedWordpressTwoFactorState( $user, [ 'Two_Factor_Email' ], '', [] );

		$controller = new ImportController();
		$state = $controller->startImportRun( 'wordpress_two_factor' );

		$this->assertSame( ImportController::STATUS_QUEUED, $state[ 'status' ] );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'An MFA import is already queued or running.' );

		$controller->startImportRun( 'solid_security' );
	}

	public function test_login_capture_no_longer_imports_third_party_mfa() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'alpha0001' ) ]
		);
		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'N',
			'allow_backupcodes'           => 'N',
			'enable_email_authentication' => 'N',
		], true );

		$method = new \ReflectionMethod( LoginRequestCapture::class, 'captureLogin' );
		$method->setAccessible( true );
		$method->invoke( new LoginRequestCapture(), $user );

		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, GoogleAuth::ProviderSlug() ) );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, BackupCodes::ProviderSlug() ) );
		$this->assertFalse( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
	}

	public function test_wordpress_import_run_imports_supported_factors_and_updates_summary() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$totpSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
		$backupCodeHashes = \array_map( 'wp_hash_password', [ 'bravo1111', 'charlie2222' ] );

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			$totpSecret,
			$backupCodeHashes
		);

		$state = $this->runImportAndProcessAllPages( 'wordpress_two_factor' );

		$this->assertSame( ImportController::STATUS_COMPLETED, $state[ 'status' ] );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'ga' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'email' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'backupcode' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'users_with_source_state' ] );
		$this->assertGreaterThanOrEqual( 1, $state[ 'users_with_imports' ] );
		$this->assertTrue( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );

		$gaRecords = $this->loadRecordsForSlug( $userId, 'ga' );
		$backupRecords = $this->loadRecordsForSlug( $userId, 'backupcode' );
		$this->assertCount( 1, $gaRecords );
		$this->assertCount( 2, $backupRecords );
		$this->assertSame( $totpSecret, $gaRecords[ 0 ]->unique_id );
	}

	public function test_wordpress_import_run_respects_site_enabled_provider_subset() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		\update_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, [ 'Two_Factor_Totp' ] );
		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'delta3333' ) ]
		);

		$state = $this->runImportAndProcessAllPages( 'wordpress_two_factor' );

		$this->assertSame( [ 'ga' => 1 ], $state[ 'imported_factors' ] );
		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'backupcode' ) );
		$this->assertFalse( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
	}

	public function test_rerun_preserves_existing_shield_factor_slots() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'echo4444' ) ]
		);

		$this->runImportAndProcessAllPages( 'wordpress_two_factor' );

		$gaBefore = $this->loadRecordsForSlug( $userId, 'ga' )[ 0 ]->unique_id;
		$backupBefore = $this->loadRecordsForSlug( $userId, 'backupcode' )[ 0 ]->unique_id;

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'KRUGS4ZANFZSAYJANRSWC43FMNZGK5DS',
			[ \wp_hash_password( 'foxtrot5555' ) ]
		);

		$state = $this->runImportAndProcessAllPages( 'wordpress_two_factor' );

		$this->assertSame( ImportController::STATUS_COMPLETED, $state[ 'status' ] );
		$this->assertSame( $gaBefore, $this->loadRecordsForSlug( $userId, 'ga' )[ 0 ]->unique_id );
		$this->assertSame( $backupBefore, $this->loadRecordsForSlug( $userId, 'backupcode' )[ 0 ]->unique_id );
		$this->assertTrue( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
	}

	public function test_multi_page_import_run_processes_all_pages() :void {
		for ( $i = 0; $i < 251; $i++ ) {
			$userId = $this->factory()->user->create( [ 'role' => 'administrator' ] );
			$this->seedWordpressTwoFactorState(
				\get_user_by( 'id', $userId ),
				[ 'Two_Factor_Email' ],
				'',
				[]
			);
		}

		$controller = new ImportController();
		$started = $controller->startImportRun( 'wordpress_two_factor' );

		$this->assertGreaterThan( 1, $started[ 'pages_total' ] );
		for ( $page = 1; $page <= $started[ 'pages_total' ]; $page++ ) {
			$controller->processPage( $page );
		}
		$state = $controller->markRunCompleted();

		$this->assertSame( $state[ 'pages_total' ], $state[ 'pages_processed' ] );
		$this->assertSame( $state[ 'users_total' ], $state[ 'users_processed' ] );
		$this->assertGreaterThanOrEqual( 251, $state[ 'imported_factors' ][ 'email' ] ?? 0 );
		$this->assertSame( ImportController::STATUS_COMPLETED, $state[ 'status' ] );
	}

	public function test_wordfence_import_run_imports_ga_and_backup_codes() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$recoveryCodes = [ 'abcdef1234567890', '1122334455667788' ];

		$this->seedWordfenceLoginSecurityState(
			$user,
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			$recoveryCodes
		);

		$state = $this->runImportAndProcessAllPages( 'wordfence_login_security' );
		$backupRecords = $this->loadRecordsForSlug( $userId, 'backupcode' );

		$this->assertSame( 1, $state[ 'imported_factors' ][ 'ga' ] ?? 0 );
		$this->assertSame( 1, $state[ 'imported_factors' ][ 'backupcode' ] ?? 0 );
		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertImportedBackupCodesMatch( $recoveryCodes, $backupRecords );
	}

	public function test_wordfence_missing_table_fails_run_start() :void {
		$this->dropWordfenceLoginSecuritySecretsTable();

		$state = ( new ImportController() )->startImportRun( 'wordfence_login_security' );

		$this->assertSame( ImportController::STATUS_FAILED, $state[ 'status' ] );
		$this->assertSame( 'wordfence_login_security', $state[ 'supplier_slug' ] );
		$this->assertStringContainsString( 'source table is missing', $state[ 'last_error' ] );
	}

	public function test_solid_plaintext_import_run_imports_supported_factors() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedSolidSecuritySiteSettings( [ 'available_methods' => 'all' ] );
		$this->seedSolidSecurityState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'golf6666' ) ]
		);

		$state = $this->runImportAndProcessAllPages( 'solid_security' );

		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'ga' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'email' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'backupcode' ] ?? 0 );
	}

	public function test_solid_import_falls_back_when_site_settings_missing() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedSolidSecurityState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'hotel7777' ) ]
		);

		$state = $this->runImportAndProcessAllPages( 'solid_security' );

		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'ga' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'email' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'backupcode' ] ?? 0 );
	}

	public function test_solid_import_respects_site_method_restrictions() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedSolidSecuritySiteSettings( [ 'available_methods' => 'not_email' ] );
		$this->seedSolidSecurityState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( 'india8888' ) ]
		);

		$state = $this->runImportAndProcessAllPages( 'solid_security' );

		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'ga' ] ?? 0 );
		$this->assertGreaterThanOrEqual( 1, $state[ 'imported_factors' ][ 'backupcode' ] ?? 0 );
		$this->assertArrayNotHasKey( 'email', $state[ 'imported_factors' ] );
		$this->assertFalse( (bool)$this->requireController()->user_metas->for( $user )->email_2fa_enabled );
	}

	public function test_selected_supplier_arbitrates_shared_meta_contract() :void {
		$wpUserId = $this->createAdministratorUser();
		$solidUserId = $this->createAdministratorUser();
		$wpUser = \get_user_by( 'id', $wpUserId );
		$solidUser = \get_user_by( 'id', $solidUserId );

		\update_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, [ 'Two_Factor_Email' ] );
		$this->seedSolidSecuritySiteSettings( [ 'available_methods' => 'not_email' ] );

		foreach ( [ $wpUser, $solidUser ] as $user ) {
			$this->seedWordpressTwoFactorState(
				$user,
				[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
				'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
				[ \wp_hash_password( 'juliet9999' ) ]
			);
		}

		$processor = new ImportUserProcessor();
		$wpResult = $processor->process( $wpUser, new WordpressTwoFactorBridge() );
		$solidResult = $processor->process( $solidUser, new SolidSecurityBridge() );

		$this->assertSame( [ 'email' ], $wpResult->importedFactorSlugs );
		$this->assertSame( [ 'ga', 'backupcode' ], $solidResult->importedFactorSlugs );
		$this->assertFalse( (bool)$this->requireController()->user_metas->for( $solidUser )->email_2fa_enabled );
	}

	public function test_solid_encrypted_user_import_with_matching_key() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$secret = 'test-solid-encryption-secret-123';
		$totpSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

		$this->seedSolidSecuritySiteSettings( [ 'available_methods' => 'all' ] );
		$this->seedSolidSecurityState(
			$user,
			[ 'Two_Factor_Totp' ],
			$this->buildSolidEncryptedTotpSecret( $totpSecret, $user->ID, $secret ),
			[]
		);

		$result = ( new ImportUserProcessor() )->process( $user, $this->buildSolidSecurityBridge( $secret ) );

		$this->assertSame( [ 'ga' ], $result->importedFactorSlugs );
		$this->assertSame( [], $result->skippedFactorReasons );
		$this->assertSame( $totpSecret, $this->loadRecordsForSlug( $userId, 'ga' )[ 0 ]->unique_id );
	}

	public function test_solid_encrypted_user_import_missing_key_skips_ga_but_imports_other_factors() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedSolidSecuritySiteSettings( [ 'available_methods' => 'all' ] );
		$this->seedSolidSecurityState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Email', 'Two_Factor_Backup_Codes' ],
			$this->buildSolidEncryptedTotpSecret(
				'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
				$user->ID,
				'test-solid-encryption-secret-123'
			),
			[ \wp_hash_password( 'kilo0000' ) ]
		);

		$result = ( new ImportUserProcessor() )->process( $user, $this->buildSolidSecurityBridge( null ) );

		$this->assertEqualsCanonicalizing( [ 'email', 'backupcode' ], $result->importedFactorSlugs );
		$this->assertSame( [ 'ga' => 'encrypted_missing_key' ], $result->skippedFactorReasons );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'ga' ) );
	}

	public function test_solid_encrypted_user_import_wrong_key_records_decrypt_failed() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedSolidSecuritySiteSettings( [ 'available_methods' => 'all' ] );
		$this->seedSolidSecurityState(
			$user,
			[ 'Two_Factor_Totp' ],
			$this->buildSolidEncryptedTotpSecret(
				'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
				$user->ID,
				'test-solid-encryption-secret-123'
			),
			[]
		);

		$result = ( new ImportUserProcessor() )->process(
			$user,
			$this->buildSolidSecurityBridge( 'wrong-solid-encryption-secret-999' )
		);

		$this->assertSame( [], $result->importedFactorSlugs );
		$this->assertSame( [ 'ga' => 'decrypt_failed' ], $result->skippedFactorReasons );
	}

	public function test_imported_ga_secret_validates_through_shield_login_flow() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$totpSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

		$this->seedWordpressTwoFactorState( $user, [ 'Two_Factor_Totp' ], $totpSecret, [] );
		$this->runImportAndProcessAllPages( 'wordpress_two_factor' );

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
	}

	public function test_imported_backup_code_hashes_validate_through_shield_login_flow() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$rawBackupCode = 'lima1111';

		$this->seedWordpressTwoFactorState(
			$user,
			[ 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' ],
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ \wp_hash_password( $rawBackupCode ) ]
		);
		$this->runImportAndProcessAllPages( 'wordpress_two_factor' );

		$provider = new BackupCodes( $user );
		$this->seedLoginIntent( $user, 'fixture-backup-login' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			$provider->getLoginIntentFormParameter() => $rawBackupCode,
		] );

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
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, BackupCodes::ProviderSlug() ) );
		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, GoogleAuth::ProviderSlug() ) );
	}

	public function interceptImportQueueDispatch( $preempt, array $parsedArgs, string $url ) {
		$action = 'action='.$this->getImportQueueIdentifier();
		if ( \strpos( $url, $action ) === false ) {
			return $preempt;
		}

		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function runImportAndProcessAllPages( string $supplierSlug ) :array {
		$controller = new ImportController();
		$state = $controller->startImportRun( $supplierSlug );

		for ( $page = 1; $page <= $state[ 'pages_total' ]; $page++ ) {
			$controller->processPage( $page );
		}

		if ( $state[ 'pages_total' ] > 0 ) {
			$controller->markRunCompleted();
		}

		return $controller->getRunState();
	}

	private function resetImportRuntime() :void {
		$this->requireController()->opts->optSet( ImportController::OPT_RUN_STATE, [] )->store();
		( new ImportQueue() )->cleanupTransportState();
	}

	private function clearThirdPartyMfaFixtureState() :void {
		global $wpdb;

		$metaKeys = [
			WordpressTwoFactorBridge::META_ENABLED_PROVIDERS,
			WordpressTwoFactorBridge::META_TOTP_SECRET,
			WordpressTwoFactorBridge::META_BACKUP_CODES,
			'email_2fa_enabled',
			'login_intents',
		];
		$placeholders = \implode( ', ', \array_fill( 0, \count( $metaKeys ), '%s' ) );
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM `{$wpdb->usermeta}` WHERE `meta_key` IN ({$placeholders})",
			...$metaKeys
		) );
	}

	private function resetMfaProviderCache() :void {
		$ref = new \ReflectionClass( $this->requireController()->comps->mfa );
		if ( $ref->hasProperty( 'providers' ) ) {
			$prop = $ref->getProperty( 'providers' );
			$prop->setAccessible( true );
			$prop->setValue( $this->requireController()->comps->mfa, [] );
		}
	}

	private function getImportQueueIdentifier() :string {
		return $this->requireController()->prefix().'_mfa_import_pages';
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

	private function seedSolidSecuritySiteSettings( array $settings ) :void {
		\update_site_option( 'itsec-storage', [
			'two-factor' => \array_merge(
				[
					'available_methods'        => 'all',
					'custom_available_methods' => [],
				],
				$settings
			),
		] );
	}

	private function seedSolidSecurityState(
		\WP_User $user,
		array $enabledProviders,
		string $totpSecret,
		array $backupCodeHashes
	) :void {
		\update_user_meta( $user->ID, SolidSecurityBridge::META_ENABLED_PROVIDERS, $enabledProviders );

		if ( $totpSecret === '' ) {
			\delete_user_meta( $user->ID, SolidSecurityBridge::META_TOTP_SECRET );
		}
		else {
			\update_user_meta( $user->ID, SolidSecurityBridge::META_TOTP_SECRET, $totpSecret );
		}

		if ( empty( $backupCodeHashes ) ) {
			\delete_user_meta( $user->ID, SolidSecurityBridge::META_BACKUP_CODES );
		}
		else {
			\update_user_meta( $user->ID, SolidSecurityBridge::META_BACKUP_CODES, $backupCodeHashes );
		}
	}

	private function buildSolidSecurityBridge( ?string $secret = null ) :SolidSecurityBridge {
		return new class( $secret ) extends SolidSecurityBridge {

			private ?string $secret;

			public function __construct( ?string $secret ) {
				$this->secret = $secret;
			}

			protected function getItsecEncryptionSecret() :?string {
				return $this->secret;
			}
		};
	}

	private function buildSolidEncryptedTotpSecret( string $totpSecret, int $userId, string $secret ) :string {
		if ( !\function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) && \defined( 'ABSPATH' ) ) {
			$sodiumCompat = ABSPATH.WPINC.'/sodium_compat/autoload.php';
			if ( \is_file( $sodiumCompat ) ) {
				require_once $sodiumCompat;
			}
		}

		$nonce = \random_bytes( 24 );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
			$totpSecret,
			'$t1$'.$nonce.\pack( 'N', $userId ),
			$nonce,
			\hash_hmac( 'sha256', $secret, 'itsec-user-encryption', true )
		);

		return '$t1$'.\base64_encode( $nonce.$ciphertext );
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

	private function seedWordfenceLoginSecurityState( \WP_User $user, string $totpSecret, array $recoveryCodes ) :void {
		global $wpdb;

		$this->deleteWordfenceLoginSecurityState( $user->ID );
		$secretHex = $totpSecret === '' ? '' : \bin2hex( Base32::decode( $totpSecret ) );
		$recoveryHex = \implode( '', \array_map(
			static fn( string $code ) => \strtolower( (string)\preg_replace( '#[^a-f0-9]#i', '', $code ) ),
			$recoveryCodes
		) );
		$table = $this->getWordfenceLoginSecuritySecretsTable();

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO `{$table}` (`user_id`, `secret`, `recovery`, `ctime`, `vtime`, `mode`) VALUES (%d, UNHEX(%s), UNHEX(%s), %d, %d, %s)",
			$user->ID,
			$secretHex,
			$recoveryHex,
			\time(),
			\time(),
			'authenticator'
		) );
	}

	private function deleteWordfenceLoginSecurityState( int $userId ) :void {
		global $wpdb;
		$table = $this->getWordfenceLoginSecuritySecretsTable();
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE `user_id` = %d", $userId ) );
	}

	private function loadRecordsForSlug( int $userId, string $slug ) :array {
		return \array_values( \array_filter(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult(),
			static fn( $record ) => $record->slug === $slug
		) );
	}

	private function assertImportedBackupCodesMatch( array $recoveryCodes, array $backupRecords ) :void {
		$this->assertCount( \count( $recoveryCodes ), $backupRecords );

		foreach ( $recoveryCodes as $recoveryCode ) {
			$matches = \array_filter(
				$backupRecords,
				static fn( $record ) => \wp_check_password( \strtolower( $recoveryCode ), $record->unique_id )
			);
			$this->assertNotEmpty( $matches, 'Missing imported backup code hash for '.$recoveryCode );
		}
	}

	private function getWordfenceLoginSecuritySecretsTable() :string {
		global $wpdb;
		return $wpdb->base_prefix.WordfenceLoginSecurityBridge::TABLE_2FA_SECRETS;
	}

	private function createWordfenceLoginSecuritySecretsTable() :void {
		$this->runWithoutWordpressTemporaryTableQueryHooks( function () {
			global $wpdb;
			$table = $this->getWordfenceLoginSecuritySecretsTable();
			$wpdb->query(
				"CREATE TABLE IF NOT EXISTS `{$table}` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`user_id` bigint(20) unsigned NOT NULL,
					`secret` tinyblob NOT NULL,
					`recovery` blob NOT NULL,
					`ctime` int(10) unsigned NOT NULL,
					`vtime` int(10) unsigned NOT NULL,
					`mode` enum('authenticator') NOT NULL DEFAULT 'authenticator',
					PRIMARY KEY (`id`),
					KEY `user_id` (`user_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
			);
		} );
		\FernleafSystems\Wordpress\Services\Services::WpDb()->clearResultShowTables();
	}

	private function dropWordfenceLoginSecuritySecretsTable() :void {
		$this->runWithoutWordpressTemporaryTableQueryHooks( function () {
			global $wpdb;
			$table = $this->getWordfenceLoginSecuritySecretsTable();
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		} );
		\FernleafSystems\Wordpress\Services\Services::WpDb()->clearResultShowTables();
	}

	// WP_UnitTestCase can rewrite ad hoc CREATE/DROP TABLE queries into temporary tables.
	// Suspending those query hooks keeps Wordfence's table visible to SHOW TABLES probes.
	private function runWithoutWordpressTemporaryTableQueryHooks( callable $callback ) {
		$createHook = [ $this, '_create_temporary_tables' ];
		$dropHook = [ $this, '_drop_temporary_tables' ];
		$removedCreateHook = \method_exists( $this, '_create_temporary_tables' ) && \has_filter( 'query', $createHook ) !== false;
		$removedDropHook = \method_exists( $this, '_drop_temporary_tables' ) && \has_filter( 'query', $dropHook ) !== false;

		if ( $removedCreateHook ) {
			\remove_filter( 'query', $createHook, 10 );
		}
		if ( $removedDropHook ) {
			\remove_filter( 'query', $dropHook, 10 );
		}

		try {
			return $callback();
		}
		finally {
			if ( $removedCreateHook ) {
				\add_filter( 'query', $createHook, 10 );
			}
			if ( $removedDropHook ) {
				\add_filter( 'query', $dropHook, 10 );
			}
		}
	}
}
