<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use Base32\Base32;
use Dolondro\GoogleAuthenticator\GoogleAuthenticator;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import\{
	ImportController,
	SupplierBridgeInterface,
	SupplierFactorData,
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

	private array $originalActiveSitewidePlugins = [];

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
			WordfenceLoginSecurityBridge::OPT_UNAVAILABLE,
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->originalSiteEnabledProviders = get_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, null );
		$this->originalActivePlugins = \is_array( get_option( 'active_plugins', [] ) ) ? get_option( 'active_plugins', [] ) : [];
		$this->originalActiveSitewidePlugins = \is_array( get_site_option( 'active_sitewide_plugins', [] ) ) ? get_site_option( 'active_sitewide_plugins', [] ) : [];
		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
			'allow_backupcodes'           => 'Y',
			'enable_email_authentication' => 'Y',
			'email_any_user_set'          => 'Y',
			'email_can_send_verified_at'  => \time(),
			WordfenceLoginSecurityBridge::OPT_UNAVAILABLE => false,
		], true );

		\update_option( 'active_plugins', [] );
		\update_site_option( 'active_sitewide_plugins', [] );
		\delete_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS );
		$this->createWordfenceLoginSecuritySecretsTable();
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
		\update_site_option( 'active_sitewide_plugins', $this->originalActiveSitewidePlugins );
		if ( $this->originalSiteEnabledProviders === null ) {
			\delete_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS );
		}
		else {
			\update_option( WordpressTwoFactorBridge::OPT_SITE_ENABLED_PROVIDERS, $this->originalSiteEnabledProviders );
		}
		$this->dropWordfenceLoginSecuritySecretsTable();

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

	public function test_wordfence_login_security_import_copies_supported_source_factors() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$totpSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
		$recoveryCodes = [ 'abcdef1234567890', '1122334455667788' ];

		$this->seedWordfenceLoginSecurityState( $user, $totpSecret, $recoveryCodes );
		$this->assertSame( [
			'mode'         => 'authenticator',
			'secret_hex'   => \strtoupper( \bin2hex( Base32::decode( $totpSecret ) ) ),
			'recovery_hex' => \strtoupper( \implode( '', $recoveryCodes ) ),
		], $this->loadWordfenceLoginSecurityStateRow( $user->ID ) );

		$discovered = ( new WordfenceLoginSecurityBridge() )->discoverForUser( $user );
		$this->assertTrue( $discovered->hasSourceState );
		$this->assertSame( $totpSecret, $discovered->gaSecret );
		$this->assertCount( 2, $discovered->backupCodeHashes );

		$result = $this->findImportResultBySlug(
			( new ImportController() )->importForUser( $user ),
			'wordfence_login_security'
		);

		$this->assertTrue( $result->checked );
		$this->assertEqualsCanonicalizing( [ 'ga', 'backupcode' ], $result->importedFactorSlugs );
		$this->assertSame( $totpSecret, $this->loadRecordsForSlug( $userId, 'ga' )[ 0 ]->unique_id );
		$this->assertImportedBackupCodesMatch( $recoveryCodes, $this->loadRecordsForSlug( $userId, 'backupcode' ) );

		$flags = $this->requireController()->user_metas->for( $user )->flags;
		$this->assertEqualsCanonicalizing(
			[ 'ga', 'backupcode' ],
			$flags[ 'mfa_import' ][ 'suppliers' ][ 'wordfence_login_security' ][ 'imported' ] ?? []
		);
		$this->assertGreaterThan( 0, (int)( $flags[ 'mfa_import' ][ 'suppliers' ][ 'wordfence_login_security' ][ 'checked_at' ] ?? 0 ) );
	}

	public function test_imported_wordfence_totp_validates_through_shield_login_flow() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$totpSecret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

		$this->seedWordfenceLoginSecurityState( $user, $totpSecret, [] );
		( new ImportController() )->importForUser( $user );

		$provider = new GoogleAuth( $user );
		$this->seedLoginIntent( $user, 'fixture-wordfence-ga-login' );
		$this->mergeCurrentRequestTransport( [
			$provider->getLoginIntentFormParameter() => ( new GoogleAuthenticator() )->calculateCode( $totpSecret ),
		] );

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'fixture-wordfence-ga-login' );

		$this->assertSame( GoogleAuth::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertGreaterThan( 0, (int)$this->requireController()->user_metas->for( $user )->record->last_2fa_verified_at );
	}

	public function test_imported_wordfence_recovery_code_validates_with_spaced_input() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$recoveryCode = 'abcdef1234567890';

		$this->seedWordfenceLoginSecurityState(
			$user,
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ $recoveryCode ]
		);
		( new ImportController() )->importForUser( $user );

		$provider = new BackupCodes( $user );
		$this->seedLoginIntent( $user, 'fixture-wordfence-backup-login' );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		], [], [
			$provider->getLoginIntentFormParameter() => 'ABCD EF12 3456 7890',
		] );

		$this->startLocalEmailCapture();
		try {
			$validatedSlug = ( new LoginIntentRequestValidate() )
				->setWpUser( $user )
				->run( 'fixture-wordfence-backup-login' );
		}
		finally {
			$this->stopLocalEmailCapture();
		}

		$this->assertSame( BackupCodes::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, BackupCodes::ProviderSlug() ) );
	}

	public function test_wordfence_import_is_idempotent_and_preserves_existing_shield_factors() :void {
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

		$this->seedWordfenceLoginSecurityState(
			$user,
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ 'abcdef1234567890' ]
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
	}

	public function test_wordfence_import_skips_when_source_plugin_is_active() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		foreach ( [
			WordfenceLoginSecurityBridge::ACTIVE_PLUGIN_FILE_CORE,
			WordfenceLoginSecurityBridge::ACTIVE_PLUGIN_FILE_STANDALONE,
		] as $pluginFile ) {
			\update_option( 'active_plugins', [ $pluginFile ] );
			$this->seedWordfenceLoginSecurityState(
				$user,
				'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
				[ 'abcdef1234567890' ]
			);

			$result = $this->findImportResultBySlug(
				( new ImportController() )->importForUser( $user ),
				'wordfence_login_security'
			);

			$this->assertFalse( $result->checked );
			$this->assertEmpty( $result->importedFactorSlugs );
			$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'ga' ) );
			$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'backupcode' ) );

			$this->deleteWordfenceLoginSecurityState( $user->ID );
			\update_option( 'active_plugins', [] );
		}
	}

	public function test_wordfence_import_without_source_table_marks_site_unavailable() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		global $wpdb;

		$this->dropWordfenceLoginSecuritySecretsTable();
		$wpdb->last_error = '';

		$result = $this->findImportResultBySlug(
			( new ImportController() )->importForUser( $user ),
			'wordfence_login_security'
		);

		$this->assertFalse( $result->checked );
		$this->assertEmpty( $result->importedFactorSlugs );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'backupcode' ) );
		$this->assertTrue( $this->isWordfenceLoginSecurityUnavailableFlagSet() );
		$this->assertSame( '', $wpdb->last_error );
	}

	public function test_wordfence_unavailable_cache_skips_import_when_source_table_exists() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->seedWordfenceLoginSecurityState(
			$user,
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ 'abcdef1234567890' ]
		);
		$this->setWordfenceLoginSecurityUnavailableFlag( true );

		$result = $this->findImportResultBySlug(
			( new ImportController() )->importForUser( $user ),
			'wordfence_login_security'
		);

		$this->assertFalse( $result->checked );
		$this->assertEmpty( $result->importedFactorSlugs );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertCount( 0, $this->loadRecordsForSlug( $userId, 'backupcode' ) );
		$this->assertTrue( $this->isWordfenceLoginSecurityUnavailableFlagSet() );
	}

	public function test_wordfence_active_source_clears_stale_unavailable_cache() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->setWordfenceLoginSecurityUnavailableFlag( true );
		\update_option( 'active_plugins', [ WordfenceLoginSecurityBridge::ACTIVE_PLUGIN_FILE_CORE ] );

		$result = $this->findImportResultBySlug(
			( new ImportController() )->importForUser( $user ),
			'wordfence_login_security'
		);

		$this->assertFalse( $result->checked );
		$this->assertEmpty( $result->importedFactorSlugs );
		$this->assertFalse( $this->isWordfenceLoginSecurityUnavailableFlagSet() );
	}

	public function test_wordfence_import_succeeds_after_active_source_clears_stale_unavailable_cache() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$this->setWordfenceLoginSecurityUnavailableFlag( true );
		\update_option( 'active_plugins', [ WordfenceLoginSecurityBridge::ACTIVE_PLUGIN_FILE_STANDALONE ] );
		( new ImportController() )->importForUser( $user );
		\update_option( 'active_plugins', [] );

		$this->seedWordfenceLoginSecurityState(
			$user,
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ 'abcdef1234567890' ]
		);

		$result = $this->findImportResultBySlug(
			( new ImportController() )->importForUser( $user ),
			'wordfence_login_security'
		);

		$this->assertTrue( $result->checked );
		$this->assertEqualsCanonicalizing( [ 'ga', 'backupcode' ], $result->importedFactorSlugs );
		$this->assertFalse( $this->isWordfenceLoginSecurityUnavailableFlagSet() );
		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, 'ga' ) );
		$this->assertCount( 1, $this->loadRecordsForSlug( $userId, 'backupcode' ) );
	}

	public function test_wordfence_table_probe_clears_stale_show_tables_cache() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$wpDb = \FernleafSystems\Wordpress\Services\Services::WpDb();

		$this->dropWordfenceLoginSecuritySecretsTable();
		$wpDb->showTables();
		$this->createWordfenceLoginSecuritySecretsTable();
		$this->seedWordfenceLoginSecurityState(
			$user,
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
			[ 'abcdef1234567890' ]
		);

		$result = $this->findImportResultBySlug(
			( new ImportController() )->importForUser( $user ),
			'wordfence_login_security'
		);

		$this->assertTrue( $result->checked );
		$this->assertEqualsCanonicalizing( [ 'ga', 'backupcode' ], $result->importedFactorSlugs );
		$this->assertFalse( $this->isWordfenceLoginSecurityUnavailableFlagSet() );
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

	private function findImportResultBySlug( array $results, string $supplierSlug ) {
		foreach ( $results as $result ) {
			if ( $result->supplierSlug === $supplierSlug ) {
				return $result;
			}
		}
		$this->fail( 'Import result not found for supplier: '.$supplierSlug );
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

	private function loadWordfenceLoginSecurityStateRow( int $userId ) :array {
		global $wpdb;
		$table = $this->getWordfenceLoginSecuritySecretsTable();
		return (array)$wpdb->get_row(
			$wpdb->prepare(
				"SELECT `mode`, HEX(`secret`) AS `secret_hex`, HEX(`recovery`) AS `recovery_hex` FROM `{$table}` WHERE `user_id` = %d LIMIT 1",
				$userId
			),
			ARRAY_A
		);
	}

	private function setWordfenceLoginSecurityUnavailableFlag( bool $unavailable ) :void {
		$this->requireController()->opts->optSet( WordfenceLoginSecurityBridge::OPT_UNAVAILABLE, $unavailable );
	}

	private function isWordfenceLoginSecurityUnavailableFlagSet() :bool {
		return (bool)$this->requireController()->opts->optGet( WordfenceLoginSecurityBridge::OPT_UNAVAILABLE );
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
