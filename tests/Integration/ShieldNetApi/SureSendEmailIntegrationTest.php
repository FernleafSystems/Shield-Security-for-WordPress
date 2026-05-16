<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldNetApi;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SureSendController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\SureSend\SendEmail;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class SureSendEmailIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	private string $nextOtp = 'AA11BB';

	public function set_up() :void {
		parent::set_up();

		$this->requireDb( 'mfa' );
		$this->enablePremiumCapabilities( [ '2fa_sure_send' ] );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_email_authentication',
			'enable_email_auto_login',
			'email_can_send_verified_at',
			'email_any_user_set',
			'allow_backupcodes',
			'enable_google_authenticator',
			'two_factor_auth_user_roles',
			'suresend_emails',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		RuntimeTestState::restoreOptions( [
			'enable_email_authentication' => 'Y',
			'enable_email_auto_login'     => 'Y',
			'email_can_send_verified_at'  => \time(),
			'email_any_user_set'          => 'Y',
			'allow_backupcodes'           => 'N',
			'enable_google_authenticator' => 'N',
			'two_factor_auth_user_roles'  => [ 'administrator' ],
			'suresend_emails'             => [ '2fa' ],
		], true );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
			'REMOTE_ADDR'    => '198.51.100.91',
		] );
		\add_filter( 'shield/2fa_email_otp', [ $this, 'nextEmailOtp' ] );
		$this->startLocalEmailCapture();
	}

	public function tear_down() :void {
		$this->stopLocalEmailCapture();
		\remove_filter( 'shield/2fa_email_otp', [ $this, 'nextEmailOtp' ] );
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
			$this->resetMfaProviderCache();
		}

		parent::tear_down();
	}

	public function test_suresend_email_option_shape_exposes_2fa_contract() :void {
		$definition = $this->requireController()->opts->optDef( 'suresend_emails' );

		$this->assertSame( [], $definition[ 'default' ] ?? null );
		$this->assertSame( 'multiple_select', $definition[ 'type' ] ?? null );
		$this->assertTrue( (bool)( $definition[ 'premium' ] ?? false ) );
		$this->assertSame( '2fa_sure_send', $definition[ 'cap' ] ?? null );
		$this->assertSame(
			[ '2fa' ],
			\array_values( \array_map(
				static fn( array $valueOption ) :string => (string)( $valueOption[ 'value_key' ] ?? '' ),
				$definition[ 'value_options' ] ?? []
			) )
		);
	}

	public function test_suresend_2fa_enablement_requires_option_and_admin_user() :void {
		$admin = \get_user_by( 'id', $this->createUser( 'administrator', 'suresend-admin@example.test' ) );
		$subscriber = \get_user_by( 'id', $this->createUser( 'subscriber', 'suresend-subscriber@example.test' ) );
		$controller = new SureSendController();

		$this->assertTrue( $controller->can_2FA( $admin ) );
		$this->assertFalse( $controller->can_2FA( $subscriber ) );

		$this->requireController()->opts->optSet( 'suresend_emails', [] );

		$this->assertFalse( $controller->can_2FA( $admin ) );
	}

	public function test_send2fa_posts_stable_payload_keys_and_success_event() :void {
		$user = \get_user_by( 'id', $this->createUser( 'administrator', 'suresend-payload@example.test' ) );
		$this->captureShieldEvents();
		$requests = [];

		$sent = $this->withSureSendResponse(
			[ 'success' => true ],
			$requests,
			fn() :bool => ( new SendEmail() )->send2FA( $user, 'AA11BB' )
		);

		$this->assertTrue( $sent );
		$this->assertCount( 1, $requests );
		$this->assertSame(
			'/wp-json/apto-snapi/v1/sure-send/email/2fa',
			(string)\wp_parse_url( $requests[ 0 ][ 'url' ], \PHP_URL_PATH )
		);

		$body = $requests[ 0 ][ 'args' ][ 'body' ] ?? [];
		$this->assertSame( '2fa', $body[ 'slug' ] ?? null );
		$this->assertSame( 'suresend-payload@example.test', $body[ 'email_to' ] ?? null );
		$this->assertSame( 'AA11BB', $body[ 'email_data' ][ 'code' ] ?? null );
		$this->assertSame( '198.51.100.91', $body[ 'email_data' ][ 'ip' ] ?? null );
		$this->assertArrayHasKey( 'ts', $body[ 'email_data' ] ?? [] );
		$this->assertArrayHasKey( 'tz', $body[ 'email_data' ] ?? [] );
		$this->assertArrayHasKey( 'url', $body );
		$this->assertArrayHasKey( 'install_id', $body );
		$this->assertArrayHasKey( 'nonce', $body );

		$events = $this->getCapturedEventsByKey( 'suresend_success' );
		$this->assertCount( 1, $events );
		$this->assertSame( 'suresend-payload@example.test', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'email' ] ?? null );
		$this->assertSame( '2fa', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'slug' ] ?? null );
	}

	public function test_email_mfa_suresend_success_suppresses_local_mail_fallback() :void {
		$user = \get_user_by( 'id', $this->createUser( 'administrator', 'suresend-success@example.test' ) );
		$this->seedLoginIntent( $user, 'suresend-success' );
		$this->nextOtp = 'CC22DD';
		$this->captureShieldEvents();
		$requests = [];

		$sent = $this->withSureSendResponse(
			[ 'success' => true ],
			$requests,
			fn() :bool => ( new Email( $user ) )->sendEmailTwoFactorVerify( 'suresend-success', '/wp-admin/' )
		);

		$this->assertTrue( $sent );
		$this->assertCount( 1, $requests );
		$this->assertCount( 0, $this->capturedMails() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'suresend_success' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'suresend_fail' ) );
	}

	public function test_email_mfa_suresend_failure_falls_back_to_local_mail() :void {
		$user = \get_user_by( 'id', $this->createUser( 'administrator', 'suresend-fail@example.test' ) );
		$this->seedLoginIntent( $user, 'suresend-fail' );
		$this->nextOtp = 'DD33EE';
		$this->captureShieldEvents();
		$requests = [];

		$sent = $this->withSureSendResponse(
			[ 'error' => 'fixture-failure' ],
			$requests,
			fn() :bool => ( new Email( $user ) )->sendEmailTwoFactorVerify( 'suresend-fail', '/wp-admin/' )
		);

		$this->assertTrue( $sent );
		$this->assertCount( 1, $requests );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertSame( [ 'suresend-fail@example.test' ], (array)( $this->lastCapturedMail()[ 'to' ] ?? [] ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'suresend_fail' ) );
	}

	public function test_email_mfa_disabled_suresend_uses_local_mail_without_http_request() :void {
		$user = \get_user_by( 'id', $this->createUser( 'administrator', 'suresend-disabled@example.test' ) );
		$this->requireController()->opts->optSet( 'suresend_emails', [] );
		$this->seedLoginIntent( $user, 'suresend-disabled' );
		$this->nextOtp = 'EE44FF';
		$this->captureShieldEvents();
		$requests = [];

		$sent = $this->withSureSendResponse(
			[ 'success' => true ],
			$requests,
			fn() :bool => ( new Email( $user ) )->sendEmailTwoFactorVerify( 'suresend-disabled', '/wp-admin/' )
		);

		$this->assertTrue( $sent );
		$this->assertCount( 0, $requests );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertSame( [ 'suresend-disabled@example.test' ], (array)( $this->lastCapturedMail()[ 'to' ] ?? [] ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'suresend_success' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'suresend_fail' ) );
	}

	public function test_email_mfa_non_admin_user_uses_local_mail_without_http_request() :void {
		$user = \get_user_by( 'id', $this->createUser( 'subscriber', 'suresend-subscriber-mail@example.test' ) );
		$this->seedLoginIntent( $user, 'suresend-subscriber' );
		$this->nextOtp = 'FF55GG';
		$this->captureShieldEvents();
		$requests = [];

		$sent = $this->withSureSendResponse(
			[ 'success' => true ],
			$requests,
			fn() :bool => ( new Email( $user ) )->sendEmailTwoFactorVerify( 'suresend-subscriber', '/wp-admin/' )
		);

		$this->assertTrue( $sent );
		$this->assertCount( 0, $requests );
		$this->assertCount( 1, $this->capturedMails() );
		$this->assertSame( [ 'suresend-subscriber-mail@example.test' ], (array)( $this->lastCapturedMail()[ 'to' ] ?? [] ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'suresend_success' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'suresend_fail' ) );
	}

	public function nextEmailOtp() :string {
		return $this->nextOtp;
	}

	private function createUser( string $role, string $email ) :int {
		return self::factory()->user->create( [
			'role'       => $role,
			'user_email' => $email,
		] );
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

	private function withSureSendResponse( array $body, array &$requests, callable $callback ) {
		$filter = function ( $preempt, $args, $url ) use ( $body, &$requests ) {
			if ( \is_string( $url ) && \strpos( $url, '/sure-send/email/2fa' ) !== false ) {
				$requests[] = [
					'url'  => $url,
					'args' => \is_array( $args ) ? $args : [],
				];
				return [
					'headers'  => [],
					'body'     => \wp_json_encode( $body ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
				];
			}

			return $preempt;
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			return $callback();
		}
		finally {
			\remove_filter( 'pre_http_request', $filter, 10 );
		}
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
