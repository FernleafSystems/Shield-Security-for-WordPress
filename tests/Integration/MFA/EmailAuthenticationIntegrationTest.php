<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use Dolondro\GoogleAuthenticator\GoogleAuthenticator as OtpGenerator;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaEmailAutoLogin,
	MfaCanEmailSendVerify,
	MfaEmailConfirmDelivery,
	MfaEmailDisable,
	MfaEmailSendIntent,
	ModuleOptionsSave
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	EmailDeliveryVerification,
	EmailDeliveryVerificationDialog,
	Exceptions\CouldNotValidate2FA,
	Exceptions\OtpVerificationFailedException,
	LoginIntentRequestValidate,
	Provider\BackupCodes,
	Provider\Email,
	Provider\GoogleAuth,
	Utilties\MfaRecordsHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	ActionRouter\PluginAdminRouteRuntime,
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\LoginSuccessFixture;

class EmailAuthenticationIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;
	use LoginSuccessFixture;

	private array $optionsSnapshot = [];

	private array $requestSnapshot = [];

	/** @var list<string> */
	private array $otpSequence = [];

	private \Closure $pluginAdminGrant;

	public function set_up() :void {
		parent::set_up();
		$this->pluginAdminGrant = static fn() :bool => true;
		$this->requireDb( 'mfa' );
		$this->enablePremiumCapabilities( [ '2fa_webauthn' ] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_email_authentication',
			'enable_email_auto_login',
			'email_can_send_verified_at',
			'email_can_send_verification_sent_at',
			'email_any_user_set',
			'allow_backupcodes',
			'enable_google_authenticator',
			'two_factor_auth_user_roles',
			'suresend_emails',
			'block_send_email_address',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'enable_email_auto_login'     => 'Y',
			'email_can_send_verified_at'  => \time(),
			'email_can_send_verification_sent_at' => 0,
			'email_any_user_set'          => 'Y',
			'allow_backupcodes'           => 'N',
			'enable_google_authenticator' => 'N',
			'two_factor_auth_user_roles'  => [ 'administrator' ],
			'suresend_emails'             => [],
		], true );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
		\add_filter( 'shield/2fa_email_otp', [ $this, 'nextEmailOtp' ] );
		$this->startLocalEmailCapture();
		$this->startLoginSuccessFixture();
	}

	public function tear_down() :void {
		\remove_filter( $this->requireController()->prefix( 'bypass_is_plugin_admin' ), $this->pluginAdminGrant, 1000 );
		$this->stopLoginSuccessFixture();
		$this->stopLocalEmailCapture();
		\remove_filter( 'shield/2fa_email_otp', [ $this, 'nextEmailOtp' ] );
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
			$this->resetMfaProviderCache();
		}
		parent::tear_down();
	}

	public function test_email_provider_activation_requires_enabled_and_verified_delivery() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );

		$this->assertArrayHasKey(
			Email::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);

		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'N',
		], true );
		$this->resetMfaProviderCache();
		$this->assertArrayNotHasKey(
			Email::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);

		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 0,
		], true );
		$this->resetMfaProviderCache();
		$this->assertArrayNotHasKey(
			Email::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);
	}

	public function test_email_delivery_verify_action_marks_verified_and_clears_pending_send() :void {
		$userId = $this->loginAsSecurityAdmin( [
			'user_email' => 'verify-email-delivery@example.test',
		] );
		$user = \get_user_by( 'id', $userId );
		$this->assertInstanceOf( \WP_User::class, $user );
		$sentAt = \time() - 60;
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 0,
			'email_can_send_verification_sent_at' => $sentAt,
			'two_factor_auth_user_roles'  => [ 'administrator' ],
		], true );
		$this->resetMfaProviderCache();
		$this->assertArrayNotHasKey(
			Email::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);

		$payload = $this->requireController()->action_router->action( MfaCanEmailSendVerify::class, [
			MfaCanEmailSendVerify::FIELD_TOKEN => ( new EmailDeliveryVerification() )->linkToken( $sentAt ),
		] )->payload();

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertGreaterThan( 0, $this->requireController()->opts->optGet( 'email_can_send_verified_at' ) );
		$this->assertSame( 0, $this->requireController()->opts->optGet( 'email_can_send_verification_sent_at' ) );
		$this->resetMfaProviderCache();
		$this->assertArrayHasKey(
			Email::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);
	}

	public function test_email_disable_action_stores_disabled_state_and_clears_pending_send() :void {
		$this->loginAsSecurityAdmin();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => \time(),
			'email_can_send_verification_sent_at' => \time() - 60,
		], true );

		$payload = $this->requireController()->action_router->action( MfaEmailDisable::class )->payload();

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertTrue( (bool)$payload[ 'page_reload' ] );
		$this->assertSame( 'N', $this->requireController()->opts->optGet( 'enable_email_authentication' ) );
		$this->assertSame( 0, $this->requireController()->opts->optGet( 'email_can_send_verification_sent_at' ) );
	}

	public function test_email_delivery_verify_action_rejects_an_expired_link() :void {
		$this->loginAsSecurityAdmin();
		$sentAt = \time() - \DAY_IN_SECONDS - 60;
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 0,
			'email_can_send_verification_sent_at' => $sentAt,
		], true );

		$payload = $this->requireController()->action_router->action( MfaCanEmailSendVerify::class, [
			MfaCanEmailSendVerify::FIELD_TOKEN => ( new EmailDeliveryVerification() )->linkToken( $sentAt ),
		] )->payload();

		$this->assertFalse( (bool)$payload[ 'success' ] );
		$this->assertSame( 0, $this->requireController()->opts->optGet( 'email_can_send_verified_at' ) );
	}

	public function test_email_confirm_delivery_action_turns_on_email_provider_without_the_link() :void {
		$user = \get_user_by( 'id', $this->loginAsSecurityAdmin() );
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 0,
			'email_can_send_verification_sent_at' => \time() - 60,
			'two_factor_auth_user_roles'  => [ 'administrator' ],
		], true );

		$payload = $this->requireController()->action_router->action( MfaEmailConfirmDelivery::class )->payload();

		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertTrue( $payload[ 'page_reload' ] );
		$this->assertSame( EmailDeliveryVerification::STATUS_VERIFIED, ( new EmailDeliveryVerification() )->status() );
		$this->assertSame( 0, $this->requireController()->opts->optGet( 'email_can_send_verification_sent_at' ) );
		$this->resetMfaProviderCache();
		$this->assertArrayHasKey(
			Email::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);
	}

	public function test_email_confirm_delivery_action_changes_nothing_when_email_2fa_is_off() :void {
		$this->loginAsSecurityAdmin();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'N',
			'email_can_send_verified_at'  => 0,
		], true );

		$payload = $this->requireController()->action_router->action( MfaEmailConfirmDelivery::class )->payload();

		$this->assertFalse( (bool)$payload[ 'success' ] );
		$this->assertSame( 0, $this->requireController()->opts->optGet( 'email_can_send_verified_at' ) );
	}

	/**
	 * @dataProvider providerVerificationDialogDelivery
	 */
	public function test_verification_dialog_is_required_until_resolved_and_shows_where_delivery_stands(
		string $enabled, int $verifiedAt, int $sentAgo, bool $expectRequired, string $expectTone
	) :void {
		$this->loginAsSecurityAdmin();
		$this->grantPluginAdmin();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => $enabled,
			'email_can_send_verified_at'  => $verifiedAt,
			'email_can_send_verification_sent_at' => $sentAgo < 0 ? 0 : \time() - $sentAgo,
		], true );

		$dialog = new EmailDeliveryVerificationDialog();

		$this->assertSame( $expectRequired, $dialog->isRequired() );
		if ( $expectRequired ) {
			$this->assertSame( $expectTone, $dialog->build()[ 'delivery' ][ 'tone' ] );
		}
	}

	public static function providerVerificationDialogDelivery() :array {
		return [
			'not sent'       => [ 'Y', 0, -1, true, 'unsent' ],
			'sent'           => [ 'Y', 0, 60, true, 'sent' ],
			'link expired'   => [ 'Y', 0, \DAY_IN_SECONDS + 60, true, 'expired' ],
			'verified'       => [ 'Y', 1700000000, -1, false, '' ],
			'email 2FA off'  => [ 'N', 0, 60, false, '' ],
		];
	}

	/**
	 * @dataProvider providerUnavailableVerificationAddress
	 */
	public function test_verification_dialog_builds_when_there_is_no_email_address_to_show( string $adminEmail ) :void {
		$this->loginAsSecurityAdmin();
		$this->grantPluginAdmin();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 0,
			'block_send_email_address'    => '',
		], true );
		$fallback = static fn() :string => $adminEmail;
		\add_filter( 'pre_option_admin_email', $fallback );

		try {
			$dialog = ( new EmailDeliveryVerificationDialog() )->build();
		}
		finally {
			\remove_filter( 'pre_option_admin_email', $fallback );
		}

		$this->assertStringNotContainsString( '@', $dialog[ 'address' ] );
		$this->assertSame( 'unsent', $dialog[ 'delivery' ][ 'tone' ] );
	}

	public static function providerUnavailableVerificationAddress() :array {
		return [
			'blank fallback' => [ '' ],
			'no domain suffix' => [ 'admin@localhost' ],
		];
	}

	public function test_verification_dialog_is_not_required_while_security_admin_is_locked() :void {
		$this->loginAsAdministrator();
		// What a locked Security Admin does to an administrator who hasn't entered the PIN.
		$locked = static fn() :bool => false;
		\add_filter( $this->requireController()->prefix( 'is_plugin_admin' ), $locked, 1000 );
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'email_can_send_verified_at'  => 0,
		], true );

		try {
			$this->assertFalse( ( new EmailDeliveryVerificationDialog() )->isRequired() );
		}
		finally {
			\remove_filter( $this->requireController()->prefix( 'is_plugin_admin' ), $locked, 1000 );
		}
	}

	/**
	 * @dataProvider providerOptionsSaveReload
	 */
	public function test_options_save_reloads_the_page_only_when_it_turns_on_unverified_email_2fa(
		string $before, string $submitted, int $verifiedAt, bool $expectReload
	) :void {
		$this->loginAsSecurityAdmin();
		$this->grantPluginAdmin();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => $before,
			'email_can_send_verified_at'  => $verifiedAt,
			'email_can_send_verification_sent_at' => 0,
		], true );

		$payload = $this->requireController()->action_router->action( ModuleOptionsSave::class, [
			ActionData::FIELD_FORM => [
				'all_opts_keys'               => 'enable_email_authentication',
				'enable_email_authentication' => $submitted,
			],
		] )->payload();

		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertSame( $expectReload, $payload[ 'page_reload' ] );
	}

	public static function providerOptionsSaveReload() :array {
		return [
			'turned on, unverified'      => [ 'N', 'Y', 0, true ],
			'turned on, already verified' => [ 'N', 'Y', 1700000000, false ],
			'already on, unverified'     => [ 'Y', 'Y', 0, false ],
			'turned off'                 => [ 'Y', 'N', 0, false ],
		];
	}

	public function test_email_login_form_field_does_not_rehydrate_submitted_otp() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$provider = new Email( $user );

		$this->mergeCurrentRequestTransport( [
			$provider->getLoginIntentFormParameter() => 'AA11BB',
		] );

		$field = $provider->getFormField();

		$this->assertArrayHasKey( 'name', $field );
		$this->assertArrayHasKey( 'value', $field );
		$this->assertSame( $provider->getLoginIntentFormParameter(), $field[ 'name' ] );
		$this->assertSame( '', $field[ 'value' ] );
	}

	public function test_valid_ga_login_succeeds_when_stale_invalid_email_otp_is_also_submitted() :void {
		$this->captureShieldEvents();
		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
		], true );

		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$secret = 'JBSWY3DPEHPK3PXP';
		$this->seedLoginIntent( $user, 'email-ga-login' );
		$hashedNonce = $this->requireController()->comps->mfa->findHashedNonce( $user, 'email-ga-login' );
		TestDataFactory::insertMfaRecord( $user->ID, Email::ProviderSlug(), [
			'hashed_login_nonce' => $hashedNonce,
		], [
			'unique_id' => \wp_hash_password( 'AA11BB' ),
			'label'     => 'Fixture Email',
		] );
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => $secret,
			'label'     => 'Fixture GA',
		] );
		$this->clearMfaRecordsCache( $user );
		$this->resetMfaProviderCache();

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'email-ga-login', [
				( new Email( $user ) )->getLoginIntentFormParameter()      => 'ZZ99YY',
				( new GoogleAuth( $user ) )->getLoginIntentFormParameter() => $this->calculateOtp( $secret ),
			] );

		$this->assertSame( GoogleAuth::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_success' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( '2fa_verify_fail' ) );
	}

	public function test_valid_backup_code_login_succeeds_when_stale_invalid_email_otp_is_also_submitted() :void {
		$this->captureShieldEvents();
		RuntimeTestState::restoreOptions( [
			'allow_backupcodes' => 'Y',
		], true );

		$user = \get_user_by( 'id', $this->createAdministratorUser( [
			'user_email' => 'email-backup-user@example.test',
		] ) );
		$this->seedLoginIntent( $user, 'email-backup-login' );
		$hashedNonce = $this->requireController()->comps->mfa->findHashedNonce( $user, 'email-backup-login' );
		TestDataFactory::insertMfaRecord( $user->ID, Email::ProviderSlug(), [
			'hashed_login_nonce' => $hashedNonce,
		], [
			'unique_id' => \wp_hash_password( 'AA11BB' ),
			'label'     => 'Fixture Email',
		] );
		TestDataFactory::insertMfaRecord( $user->ID, BackupCodes::ProviderSlug(), [], [
			'unique_id' => \wp_hash_password( 'abc123def456' ),
			'label'     => 'Fixture Backup Code',
		] );
		$this->clearMfaRecordsCache( $user );
		$this->resetMfaProviderCache();

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'email-backup-login', [
				( new Email( $user ) )->getLoginIntentFormParameter()       => 'ZZ99YY',
				( new BackupCodes( $user ) )->getLoginIntentFormParameter() => 'ABC123- DEF456',
			] );

		$this->assertSame( BackupCodes::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->loadRecordsForSlug( $user->ID, BackupCodes::ProviderSlug() ) );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_success' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( '2fa_verify_fail' ) );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertSame( [ 'email-backup-user@example.test' ], (array)( $this->lastCapturedMail()[ 'to' ] ?? [] ) );
	}

	public function test_send_intent_creates_latest_email_record_marks_intent_and_exposes_auto_login_query() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser( [
			'user_email' => 'email-auth-user@example.test',
		] ) );
		$this->seedLoginIntent( $user, 'email-login' );
		$this->otpSequence = [ 'AA11BB', 'CC22DD' ];

		$firstPayload = $this->processEmailSendAction( $user, 'email-login', '/wp-admin/profile.php' );
		$firstRecords = $this->loadEmailRecords( $user->ID );

		$this->assertTrue( (bool)( $firstPayload[ 'success' ] ?? false ) );
		$this->assertArrayHasKey( 'page_reload', $firstPayload );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertSame( [ 'email-auth-user@example.test' ], (array)( $this->lastCapturedMail()[ 'to' ] ?? [] ) );
		$this->assertCount( 1, $firstRecords );
		$this->assertSame(
			$this->requireController()->comps->mfa->findHashedNonce( $user, 'email-login' ),
			(string)( $firstRecords[ 0 ]->data[ 'hashed_login_nonce' ] ?? '' )
		);

		$loginIntents = $this->requireController()->user_metas->for( $user )->login_intents;
		$hashedNonce = $this->requireController()->comps->mfa->findHashedNonce( $user, 'email-login' );
		$this->assertTrue( (bool)( $loginIntents[ $hashedNonce ][ 'auto_email_sent' ] ?? false ) );

		$secondPayload = $this->processEmailSendAction( $user, 'email-login', '/wp-admin/profile.php' );
		$secondRecords = $this->loadEmailRecords( $user->ID );
		$autoLoginQuery = $this->autoLoginQueryFromLastMail();

		$this->assertTrue( (bool)( $secondPayload[ 'success' ] ?? false ) );
		$this->assertCount( 2, $this->capturedMails() );
		$this->assertCount( 1, $secondRecords );
		$this->assertFalse( \wp_check_password( 'AA11BB', $secondRecords[ 0 ]->unique_id ) );
		$this->assertTrue( \wp_check_password( 'CC22DD', $secondRecords[ 0 ]->unique_id ) );
		$this->assertSame( ActionData::FIELD_SHIELD, (string)( $autoLoginQuery[ ActionData::FIELD_ACTION ] ?? '' ) );
		$this->assertSame( MfaEmailAutoLogin::SLUG, (string)( $autoLoginQuery[ ActionData::FIELD_EXECUTE ] ?? '' ) );
		// The link is declared without nonce proof; its one-time code authorises it.
		$this->assertArrayNotHasKey( ActionData::FIELD_NONCE, $autoLoginQuery );
		$this->assertSame( 'email-login', (string)( $autoLoginQuery[ 'login_nonce' ] ?? '' ) );
		$this->assertSame( (string)$user->ID, (string)( $autoLoginQuery[ 'user_id' ] ?? '' ) );
		$this->assertSame( 'CC22DD', (string)( $autoLoginQuery[ ( new Email( $user ) )->getLoginIntentFormParameter() ] ?? '' ) );
		$this->assertSame( '/wp-admin/profile.php', (string)( $autoLoginQuery[ 'redirect_to' ] ?? '' ) );

		try {
			( new Email( $user ) )->validateLoginIntent( $hashedNonce, [
				( new Email( $user ) )->getLoginIntentFormParameter() => 'AA11BB',
			] );
			$this->fail( 'Expected the previous email OTP to be invalid after resend.' );
		}
		catch ( OtpVerificationFailedException $e ) {
			$this->assertCount( 1, $this->loadEmailRecords( $user->ID ) );
		}
	}

	public function test_send_intent_falls_back_from_invalid_otp_filter_with_consistent_record_and_mail() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser( [
			'user_email' => 'invalid-otp-filter@example.test',
		] ) );
		$this->seedLoginIntent( $user, 'invalid-otp-filter-login' );
		$invalid = static fn() => new \stdClass();
		\add_filter( 'shield/2fa_email_otp', $invalid, \PHP_INT_MAX );

		try {
			$payload = $this->processEmailSendAction( $user, 'invalid-otp-filter-login' );
			$records = $this->loadEmailRecords( $user->ID );
			$query = $this->autoLoginQueryFromLastMail();
			$otp = $query[ ( new Email( $user ) )->getLoginIntentFormParameter() ] ?? null;

			$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
			$this->assertIsString( $otp );
			$this->assertNotSame( '', $otp );
			$this->assertCount( 1, $records );
			$this->assertTrue( \wp_check_password( $otp, $records[ 0 ]->unique_id ) );
		}
		finally {
			\remove_filter( 'shield/2fa_email_otp', $invalid, \PHP_INT_MAX );
		}
	}

	public function test_email_auto_login_accepts_latest_otp_and_returns_redirect_payload() :void {
		$this->captureShieldEvents();

		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'email-auto-login' );
		$this->otpSequence = [ 'EE33FF' ];
		$this->processEmailSendAction( $user, 'email-auto-login', '/wp-admin/' );
		\add_action( 'set_logged_in_cookie', function ( $cookie, $expire, $expiration, $userID, $scheme, $token ) {
			$this->assertLoginSuccessCount( 0 );
			$this->assertTrue( \WP_Session_Tokens::get_instance( $userID )->verify( $token ) );
		}, 7, 6 );

		$link = ActionData::Build( MfaEmailAutoLogin::class, false, [
			'login_nonce'                        => 'email-auto-login',
			'user_id'                            => $user->ID,
			( new Email( $user ) )->getLoginIntentFormParameter() => 'EE33FF',
			'redirect_to'                        => '/wp-admin/',
		] );
		$payload =( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass( MfaEmailAutoLogin::SLUG, $link );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( 'redirect', (string)( $payload[ 'next_step' ][ 'type' ] ?? '' ) );
		$this->assertSame( '/wp-admin/', (string)( $payload[ 'next_step' ][ 'url' ] ?? '' ) );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_success' ) );
		$this->assertMfaLoginCompleted();

		// The link goes through the same 2FA check as the login form: the audit names the user, and the pending
		// login is closed so it can't be finished a second time.
		$verified = $this->getCapturedEventsByKey( '2fa_verify_success' );
		$this->assertCount( 1, $verified );
		$this->assertSame( $user->user_login, $verified[ 0 ][ 'meta' ][ 'audit_params' ][ 'user_login' ] ?? null );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
	}

	public function test_send_intent_rejects_invalid_nonce_or_missing_provider_without_mail() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'valid-email-login' );
		$this->otpSequence = [ 'GG44HH' ];

		$invalidNoncePayload = $this->processEmailSendAction( $user, 'invalid-email-login' );
		$this->assertFalse( (bool)( $invalidNoncePayload[ 'success' ] ?? true ) );
		$this->assertCount( 0, $this->capturedMails() );

		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'N',
		], true );
		$this->resetMfaProviderCache();

		$missingProviderPayload = $this->processEmailSendAction( $user, 'valid-email-login' );
		$this->assertFalse( (bool)( $missingProviderPayload[ 'success' ] ?? true ) );
		$this->assertCount( 0, $this->capturedMails() );
	}

	/** @dataProvider rejectedAutoLoginProvider */
	public function test_rejected_email_auto_login_does_not_record_success( string $failure ) :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'rejected-auto-login' );
		$this->otpSequence = [ 'EE33FF' ];
		$this->processEmailSendAction( $user, 'rejected-auto-login' );
		if ( $failure === 'expired' ) {
			$meta = $this->requireController()->user_metas->for( $user );
			$intents = $meta->login_intents;
			foreach ( $intents as &$intent ) {
				$intent[ 'start' ] = \time() - DAY_IN_SECONDS;
			}
			unset( $intent );
			$meta->login_intents = $intents;
		}
		if ( $failure === 'provider' ) {
			RuntimeTestState::restoreOptions( [ 'enable_email_authentication' => 'N' ], true );
			$this->resetMfaProviderCache();
		}
		try {
			$payload = ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
				MfaEmailAutoLogin::SLUG,
				ActionData::Build( MfaEmailAutoLogin::class, false, [
					'login_nonce' => $failure === 'nonce' ? 'wrong-nonce' : 'rejected-auto-login',
					'user_id' => $user->ID,
					( new Email( $user ) )->getLoginIntentFormParameter() => $failure === 'otp' ? 'WRONG1' : 'EE33FF',
				] )
			);
			$this->assertSame( 'otp', $failure, 'Invalid identity/provider must reject before OTP handling.' );
			$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
		}
		catch ( \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException $e ) {
			$this->assertNotSame( 'otp', $failure );
		}
		$this->assertLoginSuccessCount( 0 );
	}

	public static function rejectedAutoLoginProvider() :array {
		return [ 'nonce' => [ 'nonce' ], 'expired' => [ 'expired' ], 'OTP' => [ 'otp' ], 'provider' => [ 'provider' ] ];
	}

	public function test_login_intent_validation_still_rejects_invalid_email_otp_without_valid_fallback() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'invalid-email-login' );
		$hashedNonce = $this->requireController()->comps->mfa->findHashedNonce( $user, 'invalid-email-login' );
		TestDataFactory::insertMfaRecord( $user->ID, Email::ProviderSlug(), [
			'hashed_login_nonce' => $hashedNonce,
		], [
			'unique_id' => \wp_hash_password( 'AA11BB' ),
			'label'     => 'Fixture Email',
		] );
		$this->clearMfaRecordsCache( $user );
		$this->resetMfaProviderCache();

		$this->expectException( OtpVerificationFailedException::class );

		( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'invalid-email-login', [
				( new Email( $user ) )->getLoginIntentFormParameter() => 'ZZ99YY',
			] );
	}

	public function test_login_intent_validation_treats_a_malformed_otp_as_no_otp() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'malformed-email-login' );
		$hashedNonce = $this->requireController()->comps->mfa->findHashedNonce( $user, 'malformed-email-login' );
		TestDataFactory::insertMfaRecord( $user->ID, Email::ProviderSlug(), [
			'hashed_login_nonce' => $hashedNonce,
		], [
			'unique_id' => \wp_hash_password( 'AA11BB' ),
			'label'     => 'Fixture Email',
		] );
		$this->clearMfaRecordsCache( $user );
		$this->resetMfaProviderCache();

		$this->expectException( CouldNotValidate2FA::class );

		( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'malformed-email-login', [
				( new Email( $user ) )->getLoginIntentFormParameter() => [ 'AA11BB' ],
			] );
	}

	public function nextEmailOtp() :string {
		return \array_shift( $this->otpSequence ) ?? 'ZZ99YY';
	}

	private function processEmailSendAction( \WP_User $user, string $plainNonce, string $redirectTo = '' ) :array {
		$this->resetMfaProviderCache();
		return ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			MfaEmailSendIntent::SLUG,
			ActionData::Build( MfaEmailSendIntent::class, false, [
				'login_nonce' => $plainNonce,
				'wp_user_id'  => $user->ID,
				'redirect_to' => $redirectTo,
			] )
		);
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

	private function loadEmailRecords( int $userId ) :array {
		return $this->loadRecordsForSlug( $userId, Email::ProviderSlug() );
	}

	private function loadRecordsForSlug( int $userId, string $slug ) :array {
		return \array_values( \array_filter(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult(),
			static fn( $record ) => $record->slug === $slug
		) );
	}

	private function autoLoginQueryFromLastMail() :array {
		$body = \html_entity_decode( (string)( $this->lastCapturedMail()[ 'html_body' ] ?? '' ), \ENT_QUOTES | \ENT_HTML5, 'UTF-8' );
		\preg_match_all( '#https?://[^"\'<>\s]+#', $body, $matches );

		foreach ( $matches[ 0 ] ?? [] as $url ) {
			$query = [];
			\parse_str( (string)\wp_parse_url( $url, \PHP_URL_QUERY ), $query );
			if ( (string)( $query[ ActionData::FIELD_EXECUTE ] ?? '' ) === MfaEmailAutoLogin::SLUG ) {
				return $query;
			}
		}

		$this->fail( 'Expected captured email to expose the email MFA auto-login action query.' );
	}

	/**
	 * The test request never caches base permissions for the logged-in user, so plugin admin is granted directly.
	 * The callback is this test's own: saving options adds and removes a '__return_true' bypass of its own.
	 */
	private function grantPluginAdmin() :void {
		\add_filter( $this->requireController()->prefix( 'bypass_is_plugin_admin' ), $this->pluginAdminGrant, 1000 );
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

	private function calculateOtp( string $secret ) :string {
		return ( new OtpGenerator() )->calculateCode( $secret );
	}
}
