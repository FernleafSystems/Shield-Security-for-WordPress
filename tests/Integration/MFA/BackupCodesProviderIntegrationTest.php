<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaBackupCodeAdd,
	MfaBackupCodeDelete
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\CouldNotValidate2FA;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	LoginIntentRequestValidate
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\{
	BackupCodes,
	GoogleAuth
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	ActionRouter\PluginAdminRouteRuntime,
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class BackupCodesProviderIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $optionsSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->enablePremiumCapabilities( [ '2fa_login_backup_codes' ] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'allow_backupcodes',
			'enable_google_authenticator',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		RuntimeTestState::restoreOptions( [
			'allow_backupcodes'           => 'Y',
			'enable_google_authenticator' => 'Y',
		], true );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
		$this->startLocalEmailCapture();
	}

	public function tear_down() :void {
		$this->stopLocalEmailCapture();
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
			$this->resetMfaProviderCache();
		}
		parent::tear_down();
	}

	public function test_allow_backupcodes_gates_provider_without_making_backup_only_users_subject_to_mfa() :void {
		$user = $this->createBackupCodeOnlyUser();

		RuntimeTestState::restoreOptions( [
			'allow_backupcodes' => 'N',
		], true );
		$this->resetMfaProviderCache();
		$this->assertArrayNotHasKey(
			BackupCodes::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersAvailableToUser( $user )
		);

		RuntimeTestState::restoreOptions( [
			'allow_backupcodes' => 'Y',
		], true );
		$this->resetMfaProviderCache();

		$this->assertArrayHasKey(
			BackupCodes::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersAvailableToUser( $user )
		);
		$this->assertSame( [], $this->requireController()->comps->mfa->getProvidersActiveForUser( $user ) );
		$this->assertFalse( $this->requireController()->comps->mfa->isSubjectToLoginIntent( $user ) );

		$this->seedGoogleAuthRecord( $user );
		$this->resetMfaProviderCache();

		$this->assertArrayHasKey(
			BackupCodes::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);
		$this->assertTrue( $this->requireController()->comps->mfa->isSubjectToLoginIntent( $user ) );
	}

	public function test_backup_code_add_and_delete_actions_return_stable_payloads_and_mutate_records() :void {
		$userId = $this->loginAsAdministrator();
		$user = \get_user_by( 'id', $userId );

		$addPayload = ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			MfaBackupCodeAdd::SLUG,
			ActionData::Build( MfaBackupCodeAdd::class, false )
		);

		$this->assertTrue( (bool)( $addPayload[ 'success' ] ?? false ) );
		$this->assertArrayHasKey( 'code', $addPayload );
		$this->assertIsString( $addPayload[ 'code' ] );
		$this->assertCount( 1, $this->loadRecordsForSlug( $user->ID, BackupCodes::ProviderSlug() ) );

		$deletePayload = ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			MfaBackupCodeDelete::SLUG,
			ActionData::Build( MfaBackupCodeDelete::class, false )
		);

		$this->assertTrue( (bool)( $deletePayload[ 'success' ] ?? false ) );
		$this->assertArrayHasKey( 'message', $deletePayload );
		$this->assertCount( 0, $this->loadRecordsForSlug( $user->ID, BackupCodes::ProviderSlug() ) );
	}

	public function test_normalized_backup_code_validates_once_clears_intent_and_sends_notification() :void {
		$this->captureShieldEvents();

		$userId = $this->createAdministratorUser( [
			'user_email' => 'backup-code-user@example.test',
		] );
		$user = \get_user_by( 'id', $userId );
		$this->seedGoogleAuthRecord( $user );
		$this->seedBackupCodeHash( $user, 'abc123def456' );
		$this->seedLoginIntent( $user, 'backup-code-login' );
		$this->resetMfaProviderCache();

		$this->mergeCurrentRequestTransport( [
			( new BackupCodes( $user ) )->getLoginIntentFormParameter() => 'ABC123- DEF456',
		] );

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'backup-code-login' );

		$this->assertSame( BackupCodes::ProviderSlug(), $validatedSlug );
		$this->assertCount( 0, $this->loadRecordsForSlug( $user->ID, BackupCodes::ProviderSlug() ) );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_success' ) );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertSame( [ 'backup-code-user@example.test' ], (array)( $this->lastCapturedMail()[ 'to' ] ?? [] ) );

		$this->seedLoginIntent( $user, 'backup-code-login-reuse' );
		$this->clearMfaRecordsCache( $user );
		$this->resetMfaProviderCache();

		$this->expectException( CouldNotValidate2FA::class );
		( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'backup-code-login-reuse' );
	}

	private function createBackupCodeOnlyUser() :\WP_User {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedBackupCodeHash( $user, 'abc123def456' );
		$this->resetMfaProviderCache();
		return $user;
	}

	private function seedGoogleAuthRecord( \WP_User $user ) :void {
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => 'JBSWY3DPEHPK3PXP',
			'label'     => 'Fixture GA',
		] );
		$this->clearMfaRecordsCache( $user );
	}

	private function seedBackupCodeHash( \WP_User $user, string $code ) :void {
		TestDataFactory::insertMfaRecord( $user->ID, BackupCodes::ProviderSlug(), [], [
			'unique_id' => \wp_hash_password( $code ),
			'label'     => 'Fixture Backup Code',
		] );
		$this->clearMfaRecordsCache( $user );
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

	private function resetMfaProviderCache() :void {
		$ref = new \ReflectionClass( $this->requireController()->comps->mfa );
		if ( $ref->hasProperty( 'providers' ) ) {
			$prop = $ref->getProperty( 'providers' );
			$prop->setAccessible( true );
			$prop->setValue( $this->requireController()->comps->mfa, [] );
		}
	}

	private function clearMfaRecordsCache( \WP_User $user ) :void {
		( new MfaRecordsHandler() )->clearForUser( $user );
	}
}
