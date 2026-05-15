<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers\WordPress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Registration\EmailValidate;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class UserRegistrationSpamIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const EMAIL_VALIDATE_HOOK = 'wp_pre_insert_user_data';
	private const REGISTRATION_HOOK = 'registration_errors';

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'reg_email_validate',
			'email_checks',
			'wphashes_api_token',
			'user_form_providers',
			'bot_protection_locations',
			'antibot_minimum',
			'login_limit_interval',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->resetUserFormBotCache();
	}

	public function tear_down() {
		$this->resetUserFormBotCache();
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_email_validation_checks_require_enabled_option_and_capability() :void {
		$con = $this->requireController();
		$this->enablePremiumCapabilities( [] );
		$con->opts
			->optSet( 'reg_email_validate', 'block' )
			->optSet( 'email_checks', [ 'syntax', 'domain_registered' ] );
		$this->assertSame( [], $con->comps->opts_lookup->getEmailValidateChecks() );

		$this->enablePremiumCapabilities( [ 'user_block_spam_registration' ] );
		$con->opts
			->optSet( 'reg_email_validate', 'block' )
			->optSet( 'email_checks', [ 'syntax', 'domain_registered' ] );
		$this->assertSame( [ 'syntax', 'domain_registered' ], $con->comps->opts_lookup->getEmailValidateChecks() );

		$con->opts->optSet( 'reg_email_validate', 'disabled' );
		$this->assertSame( [], $con->comps->opts_lookup->getEmailValidateChecks() );
	}

	public function test_invalid_email_syntax_blocks_registration_and_fires_stable_event() :void {
		$this->configureEmailValidation( 'block', [ 'syntax' ] );
		$this->captureShieldEvents();

		$emailValidator = new EmailValidate();
		$emailValidator->execute();

		try {
			$this->withWpDieTrap( function () {
				apply_filters( self::EMAIL_VALIDATE_HOOK, [
					'user_email' => 'blocked@example',
				] );
				$this->fail( 'Expected invalid registration email to trigger wp_die().' );
			} );
		}
		catch ( RegistrationSpamWpDieException $e ) {
		}
		finally {
			remove_filter( self::EMAIL_VALIDATE_HOOK, [ $emailValidator, 'validateNewUserEmail' ] );
		}

		$events = $this->getCapturedEventsByKey( 'reg_email_invalid' );
		$this->assertCount( 1, $events );
		$this->assertSame( '', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'email' ] ?? null );
		$this->assertSame( 'syntax', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'reason' ] ?? null );
		$this->assertSame( 1, (int)( $events[ 0 ][ 'meta' ][ 'offense_count' ] ?? -1 ) );
		$this->assertTrue( (bool)( $events[ 0 ][ 'meta' ][ 'block' ] ?? false ) );
	}

	public function test_remote_email_validation_uses_local_double_and_log_mode_does_not_block() :void {
		$this->configureEmailValidation( 'log', [ 'domain_registered', 'syntax' ] );
		$this->primeWpHashesToken();
		$this->captureShieldEvents();

		$emailValidator = new EmailValidate();
		$emailValidator->execute();

		$remoteWasCalled = false;
		try {
			$userData = $this->withEmailVerificationResponse(
				[
					'domain_registered' => false,
					'disposable'        => true,
					'mx'                => true,
				],
				$remoteWasCalled,
				fn() => apply_filters( self::EMAIL_VALIDATE_HOOK, [
					'user_email' => 'blocked-domain@example.test',
				] )
			);
		}
		finally {
			remove_filter( self::EMAIL_VALIDATE_HOOK, [ $emailValidator, 'validateNewUserEmail' ] );
		}

		$this->assertTrue( $remoteWasCalled );
		$this->assertSame( 'blocked-domain@example.test', $userData[ 'user_email' ] ?? null );

		$events = $this->getCapturedEventsByKey( 'reg_email_invalid' );
		$this->assertCount( 1, $events );
		$this->assertSame( 'blocked-domain@example.test', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'email' ] ?? null );
		$this->assertSame( 'domain_registered', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'reason' ] ?? null );
		$this->assertSame( 0, (int)( $events[ 0 ][ 'meta' ][ 'offense_count' ] ?? -1 ) );
		$this->assertFalse( (bool)( $events[ 0 ][ 'meta' ][ 'block' ] ?? true ) );
	}

	public function test_remote_email_validation_failure_does_not_block_registration() :void {
		$this->configureEmailValidation( 'block', [ 'domain_registered', 'syntax' ] );
		$this->primeWpHashesToken();
		$this->captureShieldEvents();

		$emailValidator = new EmailValidate();
		$emailValidator->execute();

		$remoteWasCalled = false;
		try {
			$userData = $this->withEmailVerificationFailure(
				$remoteWasCalled,
				fn() => apply_filters( self::EMAIL_VALIDATE_HOOK, [
					'user_email' => 'remote-failure@example.test',
				] )
			);
		}
		finally {
			remove_filter( self::EMAIL_VALIDATE_HOOK, [ $emailValidator, 'validateNewUserEmail' ] );
		}

		$this->assertTrue( $remoteWasCalled );
		$this->assertSame( 'remote-failure@example.test', $userData[ 'user_email' ] ?? null );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'reg_email_invalid' ) );
	}

	public function test_disabled_email_validation_does_not_register_blocking_filter() :void {
		$this->configureEmailValidation( 'disabled', [ 'syntax' ] );
		$this->captureShieldEvents();

		$emailValidator = new EmailValidate();
		$emailValidator->execute();

		$this->assertSame( [], $this->getCapturedEventsByKey( 'reg_email_invalid' ) );
		$this->assertFalse( has_filter( self::EMAIL_VALIDATE_HOOK, [ $emailValidator, 'validateNewUserEmail' ] ) );
	}

	public function test_wordpress_registration_bot_check_blocks_and_fires_event() :void {
		$this->configureWordPressRegistrationBotProtection( true );
		$this->captureShieldEvents();

		$handler = new WordPress();
		$handler->execute();
		$forceBot = fn() => 101;
		add_filter( 'shield/antibot_score_minimum', $forceBot );

		try {
			$errors = apply_filters( self::REGISTRATION_HOOK, new \WP_Error(), 'bot-register' );
		}
		finally {
			remove_filter( 'shield/antibot_score_minimum', $forceBot );
			remove_filter( self::REGISTRATION_HOOK, [ $handler, 'checkRegister_WP' ], 10 );
		}

		$this->assertInstanceOf( \WP_Error::class, $errors );
		$this->assertContains( 'shield-fail-login', $errors->get_error_codes() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'block_register' ) );
	}

	public function test_wordpress_registration_bot_check_requires_capability() :void {
		$this->configureWordPressRegistrationBotProtection( false );
		$this->captureShieldEvents();

		$handler = new WordPress();
		$handler->execute();
		$forceBot = fn() => 101;
		add_filter( 'shield/antibot_score_minimum', $forceBot );

		try {
			$errors = apply_filters( self::REGISTRATION_HOOK, new \WP_Error(), 'bot-register' );
		}
		finally {
			remove_filter( 'shield/antibot_score_minimum', $forceBot );
			remove_filter( self::REGISTRATION_HOOK, [ $handler, 'checkRegister_WP' ], 10 );
		}

		$this->assertInstanceOf( \WP_Error::class, $errors );
		$this->assertSame( [], $errors->get_error_codes() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'block_register' ) );
	}

	private function configureEmailValidation( string $mode, array $checks ) :void {
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php?action=register',
		], [
			'action' => 'register',
		], [] );

		$this->enablePremiumCapabilities( [ 'user_block_spam_registration' ] );
		$this->requireController()->opts
			->optSet( 'reg_email_validate', $mode )
			->optSet( 'email_checks', $checks );
	}

	private function configureWordPressRegistrationBotProtection( bool $withCapability ) :void {
		wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-login.php?action=register',
				'REMOTE_ADDR'    => '198.51.100.179',
			],
			[
				'action' => 'register',
			],
			[
				'user_login' => 'bot-register',
				'user_email' => 'bot-register@example.test',
			]
		);

		$this->enablePremiumCapabilities( $withCapability ? [ 'thirdparty_scan_users' ] : [] );
		$this->requireController()->opts
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->optSet( 'bot_protection_locations', [ 'register' ] )
			->optSet( 'login_limit_interval', 0 );
		$this->resetUserFormBotCache();
	}

	private function primeWpHashesToken() :void {
		$this->requireController()->opts->optSet( 'wphashes_api_token', [
			'token'             => \str_repeat( 'a', 40 ),
			'expires_at'        => \time() + \DAY_IN_SECONDS,
			'attempt_at'        => 0,
			'next_attempt_from' => \time() + \DAY_IN_SECONDS,
			'valid_license'     => true,
		] );
	}

	private function withEmailVerificationResponse( array $verification, bool &$remoteWasCalled, callable $callback ) {
		$filter = function ( $preempt, $args, $url ) use ( $verification, &$remoteWasCalled ) {
			if ( \is_string( $url ) && \strpos( $url, '/verify/email/' ) !== false ) {
				$remoteWasCalled = true;
				return [
					'headers'  => [],
					'body'     => \wp_json_encode( [ 'verification' => $verification ] ),
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
				];
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			return $callback();
		}
		finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}
	}

	private function withEmailVerificationFailure( bool &$remoteWasCalled, callable $callback ) {
		$filter = function ( $preempt, $args, $url ) use ( &$remoteWasCalled ) {
			if ( \is_string( $url ) && \strpos( $url, '/verify/email/' ) !== false ) {
				$remoteWasCalled = true;
				return new \WP_Error( 'shi_279_email_verify_failure' );
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			return $callback();
		}
		finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}
	}

	private function withWpDieTrap( callable $callback ) :void {
		$filter = fn() => function () {
			throw new RegistrationSpamWpDieException();
		};
		add_filter( 'wp_die_handler', $filter );

		try {
			$callback();
		}
		finally {
			remove_filter( 'wp_die_handler', $filter );
		}
	}

	private function resetUserFormBotCache() :void {
		$reflection = new \ReflectionClass( BaseHandler::class );
		$property = $reflection->getProperty( 'isBot' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}
}

class RegistrationSpamWpDieException extends \RuntimeException {
}
