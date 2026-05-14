<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaEmailAutoLogin,
	MfaEmailSendIntent
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\OtpVerificationFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	ActionRouter\PluginAdminRouteRuntime,
	RuntimeTestState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class EmailAuthenticationIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $optionsSnapshot = [];

	private array $requestSnapshot = [];

	/** @var list<string> */
	private array $otpSequence = [];

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->enablePremiumCapabilities( [ '2fa_webauthn' ] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_email_authentication',
			'enable_email_auto_login',
			'email_can_send_verified_at',
			'email_any_user_set',
			'two_factor_auth_user_roles',
			'suresend_emails',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'enable_email_auto_login'     => 'Y',
			'email_can_send_verified_at'  => \time(),
			'email_any_user_set'          => 'Y',
			'two_factor_auth_user_roles'  => [ 'administrator' ],
			'suresend_emails'             => [],
		], true );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
		\add_filter( 'shield/2fa_email_otp', [ $this, 'nextEmailOtp' ] );
		$this->startLocalEmailCapture();
	}

	public function tear_down() :void {
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
		$this->assertArrayHasKey( ActionData::FIELD_NONCE, $autoLoginQuery );
		$this->assertSame( 'email-login', (string)( $autoLoginQuery[ 'login_nonce' ] ?? '' ) );
		$this->assertSame( (string)$user->ID, (string)( $autoLoginQuery[ 'user_id' ] ?? '' ) );
		$this->assertSame( 'CC22DD', (string)( $autoLoginQuery[ ( new Email( $user ) )->getLoginIntentFormParameter() ] ?? '' ) );
		$this->assertSame( '/wp-admin/profile.php', (string)( $autoLoginQuery[ 'redirect_to' ] ?? '' ) );

		$this->mergeCurrentRequestTransport( [
			( new Email( $user ) )->getLoginIntentFormParameter() => 'AA11BB',
		] );

		try {
			( new Email( $user ) )->validateLoginIntent( $hashedNonce );
			$this->fail( 'Expected the previous email OTP to be invalid after resend.' );
		}
		catch ( OtpVerificationFailedException $e ) {
			$this->assertCount( 1, $this->loadEmailRecords( $user->ID ) );
		}
	}

	public function test_email_auto_login_accepts_latest_otp_and_returns_redirect_payload() :void {
		$this->captureShieldEvents();

		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$this->seedLoginIntent( $user, 'email-auto-login' );
		$this->otpSequence = [ 'EE33FF' ];
		$this->processEmailSendAction( $user, 'email-auto-login', '/wp-admin/' );

		$payload = ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			MfaEmailAutoLogin::SLUG,
			ActionData::Build( MfaEmailAutoLogin::class, false, [
				'login_nonce'                        => 'email-auto-login',
				'user_id'                            => $user->ID,
				( new Email( $user ) )->getLoginIntentFormParameter() => 'EE33FF',
				'redirect_to'                        => '/wp-admin/',
			] )
		);

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( 'redirect', (string)( $payload[ 'next_step' ][ 'type' ] ?? '' ) );
		$this->assertSame( '/wp-admin/', (string)( $payload[ 'next_step' ][ 'url' ] ?? '' ) );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_success' ) );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_success' ) );
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
		return \array_values( \array_filter(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult(),
			static fn( $record ) => $record->slug === Email::ProviderSlug()
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

	private function resetMfaProviderCache() :void {
		$ref = new \ReflectionClass( $this->requireController()->comps->mfa );
		if ( $ref->hasProperty( 'providers' ) ) {
			$prop = $ref->getProperty( 'providers' );
			$prop->setAccessible( true );
			$prop->setValue( $this->requireController()->comps->mfa, [] );
		}
	}
}
