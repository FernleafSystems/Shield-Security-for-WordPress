<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\CoolDownHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\ProviderPluginFixture;
use FernleafSystems\Wordpress\Services\Services;

class ThirdPartyFormBotHandlersIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use ProviderPluginFixture;

	private const HOOKS = [
		'wpcf7_spam',
		'wpcf7_display_message',
		'woocommerce_process_login_errors',
		'woocommerce_process_registration_errors',
		'woocommerce_after_checkout_validation',
		'woocommerce_store_api_cart_errors',
	];

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	private array $hookSnapshot = [];

	private array $cooldownFlagPaths = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'form_spam_providers',
			'user_form_providers',
			'bot_protection_locations',
			'antibot_minimum',
			'login_limit_interval',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->hookSnapshot = $this->snapshotHooks( self::HOOKS );
		$this->snapshotProviderPluginFixtureState();
		$this->resetBotAndCooldownState();
		\wp_set_current_user( 0 );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->deleteCooldownFlags();
			$this->restoreHooks( $this->hookSnapshot );
			$this->restoreProviderPluginFixtureState();
			$this->restoreCurrentRequestState( $this->requestSnapshot );
			$this->restoreSelectedOptions( $this->optionSnapshot );
			$this->resetBotAndCooldownState();
			\wp_set_current_user( 0 );
		}

		parent::tear_down();
	}

	public function test_contact_form_7_selected_capable_provider_registers_hook_and_preserves_existing_spam() :void {
		$this->configureContactForm7( true, true );

		$con = $this->requireController();
		$this->assertArrayHasKey( 'contactform7', $con->comps->forms_spam->getInstalled() );
		$con->comps->forms_spam->resetExecution()->execute();

		$this->assertNotFalse( \has_filter( 'wpcf7_spam' ) );
		$this->assertTrue( \apply_filters( 'wpcf7_spam', true, (object)[] ) );
	}

	public function test_contact_form_7_non_bot_submission_passes_and_records_provider_audit() :void {
		$this->configureContactForm7( true, true );
		$this->requireController()->opts->optSet( 'antibot_minimum', 0 );
		$this->resetBotAndCooldownState();
		$this->requireController()->comps->forms_spam->resetExecution()->execute();
		$this->captureShieldEvents();

		$isSpam = \apply_filters( 'wpcf7_spam', false, (object)[] );

		$this->assertFalse( $isSpam );
		$events = $this->getCapturedEventsByKey( 'spam_form_pass' );
		$this->assertCount( 1, $events );
		$this->assertSame( 'Contact Form 7', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'form_provider' ] ?? null );
	}

	public function test_contact_form_7_bot_submission_becomes_spam_and_records_provider_audit() :void {
		$this->configureContactForm7( true, true );
		$this->requireController()->comps->forms_spam->resetExecution()->execute();
		$this->captureShieldEvents();
		$forceBot = static fn() :int => 101;
		\add_filter( 'shield/antibot_score_minimum', $forceBot );

		try {
			$this->resetBotAndCooldownState();
			$isSpam = \apply_filters( 'wpcf7_spam', false, (object)[] );
		}
		finally {
			\remove_filter( 'shield/antibot_score_minimum', $forceBot );
		}

		$this->assertTrue( $isSpam );
		$events = $this->getCapturedEventsByKey( 'spam_form_fail' );
		$this->assertCount( 1, $events );
		$this->assertSame( 'Contact Form 7', $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'form_provider' ] ?? null );
	}

	public function test_contact_form_7_unselected_or_uncapable_provider_does_not_register_hook() :void {
		$this->configureContactForm7( false, true );
		$this->requireController()->comps->forms_spam->resetExecution()->execute();
		$this->assertFalse( \has_filter( 'wpcf7_spam' ) );

		$this->restoreHooks( $this->hookSnapshot );
		$this->configureContactForm7( true, false );
		$this->requireController()->comps->forms_spam->resetExecution()->execute();
		$this->assertFalse( \has_filter( 'wpcf7_spam' ) );
	}

	public function test_woocommerce_selected_capable_logged_out_post_registers_hooks() :void {
		$this->configureWooCommerce( true, true );

		$con = $this->requireController();
		$this->assertArrayHasKey( 'woocommerce', $con->comps->forms_users->getInstalled() );
		$con->comps->forms_users->resetExecution()->execute();

		$this->assertNotFalse( \has_filter( 'woocommerce_process_login_errors' ) );
		$this->assertNotFalse( \has_filter( 'woocommerce_process_registration_errors' ) );
		$this->assertNotFalse( \has_action( 'woocommerce_after_checkout_validation' ) );
		$this->assertNotFalse( \has_action( 'woocommerce_store_api_cart_errors' ) );
	}

	public function test_woocommerce_bot_blocks_login_registration_and_checkout_with_context_events() :void {
		$this->configureWooCommerce( true, true );
		$this->requireController()->comps->forms_users->resetExecution()->execute();
		$this->captureShieldEvents();
		$forceBot = static fn() :int => 101;
		\add_filter( 'shield/antibot_score_minimum', $forceBot );

		try {
			$this->resetBotAndCooldownState();
			$loginErrors = \apply_filters( 'woocommerce_process_login_errors', new \WP_Error(), 'woo-login' );

			$this->resetBotAndCooldownState();
			$registrationErrors = \apply_filters(
				'woocommerce_process_registration_errors',
				new \WP_Error(),
				'woo-register'
			);

			$this->resetBotAndCooldownState();
			$checkoutErrors = new \WP_Error();
			\do_action( 'woocommerce_after_checkout_validation', [], $checkoutErrors );
		}
		finally {
			\remove_filter( 'shield/antibot_score_minimum', $forceBot );
		}

		$this->assertContains( 'shield-user-login', $loginErrors->get_error_codes() );
		$this->assertContains( 'shield-user-register', $registrationErrors->get_error_codes() );
		$this->assertContains( 'shield-user-checkout', $checkoutErrors->get_error_codes() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'login_block' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'block_register' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'block_checkout' ) );

		$failEvents = $this->getCapturedEventsByKey( 'user_form_bot_fail' );
		$this->assertCount( 3, $failEvents );
		$this->assertAuditEvent( $failEvents[ 0 ], 'woocommerce-login', 'woo-login' );
		$this->assertAuditEvent( $failEvents[ 1 ], 'woocommerce-register', 'woo-register' );
		$this->assertAuditEvent( $failEvents[ 2 ], 'woocommerce-checkout', 'unknown' );
	}

	public function test_woocommerce_preserves_existing_errors_without_bot_evaluation() :void {
		$this->configureWooCommerce( true, true );
		$this->requireController()->comps->forms_users->resetExecution()->execute();
		$this->captureShieldEvents();
		$forceBot = static fn() :int => 101;
		\add_filter( 'shield/antibot_score_minimum', $forceBot );

		try {
			$errors = new \WP_Error( 'existing_failure', 'Existing failure.' );
			$loginErrors = \apply_filters( 'woocommerce_process_login_errors', $errors, 'woo-login' );
		}
		finally {
			\remove_filter( 'shield/antibot_score_minimum', $forceBot );
		}

		$this->assertSame( $errors, $loginErrors );
		$this->assertSame( [ 'existing_failure' ], $loginErrors->get_error_codes() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'user_form_bot_fail' ) );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'login_block' ) );
	}

	public function test_woocommerce_store_api_cart_errors_suppress_only_cooldown_checks_for_that_hook() :void {
		$this->configureWooCommerce( true, true );
		$this->requireController()->opts
			->optSet( 'antibot_minimum', 0 )
			->optSet( 'login_limit_interval', 30 );
		$this->requireController()->comps->forms_users->resetExecution()->execute();
		$this->captureShieldEvents();

		$this->primeAuthCooldownFlag();
		$storeApiErrors = new \WP_Error();
		\do_action( 'woocommerce_store_api_cart_errors', $storeApiErrors );

		$this->assertNotContains( 'shield-user-checkout', $storeApiErrors->get_error_codes() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'cooldown_fail' ) );

		$this->resetBotAndCooldownState();
		$this->primeAuthCooldownFlag();
		$checkoutErrors = new \WP_Error();
		\do_action( 'woocommerce_after_checkout_validation', [], $checkoutErrors );

		$this->assertContains( 'shield-user-checkout', $checkoutErrors->get_error_codes() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'cooldown_fail' ) );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'block_checkout' ) );
	}

	public function test_woocommerce_gates_prevent_handler_registration() :void {
		$this->configureWooCommerce( false, true );
		$this->requireController()->comps->forms_users->resetExecution()->execute();
		$this->assertNoWooCommerceHooks();

		$this->restoreHooks( $this->hookSnapshot );
		$this->configureWooCommerce( true, false );
		$this->requireController()->comps->forms_users->resetExecution()->execute();
		$this->assertNoWooCommerceHooks();

		$this->restoreHooks( $this->hookSnapshot );
		$this->configureWooCommerce( true, true, 'GET' );
		$this->requireController()->comps->forms_users->resetExecution()->execute();
		$this->assertNoWooCommerceHooks();

		$this->restoreHooks( $this->hookSnapshot );
		$this->configureWooCommerce( true, true );
		\wp_set_current_user( self::factory()->user->create() );
		$this->requireController()->comps->forms_users->resetExecution()->execute();
		$this->assertNoWooCommerceHooks();
	}

	private function configureContactForm7( bool $selected, bool $capable ) :void {
		$this->installProviderFixture( 'contactform7', 'contact-form-7.php', 'WPCF7', 'Contact Form 7' );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/contact/',
				'REMOTE_ADDR'    => '198.51.100.82',
			],
			[],
			[],
			[
				'path'                             => '/contact/',
				'wp_is_admin'                      => false,
				'wp_is_ajax'                       => false,
				'wp_is_cron'                       => false,
				'request_bypasses_all_restrictions' => false,
			]
		);
		if ( $capable ) {
			$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam' ] );
		}
		else {
			$this->disablePremiumCapabilities();
		}
		$this->requireController()->opts
			->optSet( 'form_spam_providers', $selected ? [ 'contactform7' ] : [] )
			->optSet( 'antibot_minimum', 1 )
			->optSet( 'login_limit_interval', 0 );
		$this->resetBotAndCooldownState();
	}

	private function configureWooCommerce( bool $selected, bool $capable, string $method = 'POST' ) :void {
		\wp_set_current_user( 0 );
		$this->installProviderFixture( 'woocommerce', 'woocommerce.php', 'WooCommerce', 'WooCommerce' );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => $method,
				'REQUEST_URI'    => '/wp-login.php',
				'REMOTE_ADDR'    => '198.51.100.81',
			],
			[],
			$method === 'POST' ? [
				'user_login' => 'woo-login',
			] : [],
			[
				'path'        => '/wp-login.php',
				'script_name' => 'wp-login.php',
				'wp_is_admin' => false,
				'wp_is_ajax'  => false,
				'wp_is_cron'  => false,
				'request_bypasses_all_restrictions' => false,
			]
		);
		if ( $capable ) {
			$this->enablePremiumCapabilities( [ 'thirdparty_scan_users' ] );
		}
		else {
			$this->disablePremiumCapabilities();
		}
		$this->requireController()->opts
			->optSet( 'user_form_providers', $selected ? [ 'woocommerce' ] : [] )
			->optSet( 'bot_protection_locations', [ 'login', 'register' ] )
			->optSet( 'antibot_minimum', 1 )
			->optSet( 'login_limit_interval', 0 );
		$this->resetBotAndCooldownState();
	}

	private function assertNoWooCommerceHooks() :void {
		$this->assertFalse( \has_filter( 'woocommerce_process_login_errors' ) );
		$this->assertFalse( \has_filter( 'woocommerce_process_registration_errors' ) );
		$this->assertFalse( \has_action( 'woocommerce_after_checkout_validation' ) );
		$this->assertFalse( \has_action( 'woocommerce_store_api_cart_errors' ) );
	}

	private function assertAuditEvent( array $event, string $action, string $username ) :void {
		$auditParams = $event[ 'meta' ][ 'audit_params' ] ?? [];
		$this->assertSame( 'WooCommerce', $auditParams[ 'form_provider' ] ?? null );
		$this->assertSame( $action, $auditParams[ 'action' ] ?? null );
		$this->assertSame( $username, $auditParams[ 'username' ] ?? null );
	}

	private function primeAuthCooldownFlag() :void {
		$con = $this->requireController();
		if ( !$con->cache_dir_handler->exists() ) {
			$this->markTestSkipped( 'Shield cache directory is not available for cooldown fixture.' );
		}

		$file = $con->cache_dir_handler->cacheItemPath( 'mode.throttled_'.CoolDownHandler::CONTEXT_AUTH );
		Services::WpFs()->touch( $file, Services::Request()->ts() );
		$this->cooldownFlagPaths[] = $file;
		$this->resetBotAndCooldownState();
	}

	private function deleteCooldownFlags() :void {
		foreach ( \array_unique( $this->cooldownFlagPaths ) as $file ) {
			Services::WpFs()->deleteFile( $file );
		}
		$this->cooldownFlagPaths = [];
	}

	private function resetBotAndCooldownState() :void {
		$handlerReflection = new \ReflectionClass( BaseHandler::class );
		$handlerProperty = $handlerReflection->getProperty( 'isBot' );
		$handlerProperty->setAccessible( true );
		$handlerProperty->setValue( null, null );

		$signalsReflection = new \ReflectionClass( BotSignalsController::class );
		$signalsProperty = $signalsReflection->getProperty( 'isBots' );
		$signalsProperty->setAccessible( true );
		$signalsProperty->setValue( $this->requireController()->comps->bot_signals, [] );

		$cooldownReflection = new \ReflectionClass( CoolDownHandler::class );
		$cooldownProperty = $cooldownReflection->getProperty( 'secondsSinceLastReq' );
		$cooldownProperty->setAccessible( true );
		$cooldownProperty->setValue( $this->requireController()->comps->cool_down, [] );
	}

	private function snapshotHooks( array $hookNames ) :array {
		global $wp_filter;

		$snapshot = [];
		foreach ( $hookNames as $hookName ) {
			$snapshot[ $hookName ] = isset( $wp_filter[ $hookName ] ) && $wp_filter[ $hookName ] instanceof \WP_Hook ?
				clone $wp_filter[ $hookName ] :
				null;
		}

		return $snapshot;
	}

	private function restoreHooks( array $snapshot ) :void {
		global $wp_filter;

		foreach ( $snapshot as $hookName => $hook ) {
			if ( $hook instanceof \WP_Hook ) {
				$wp_filter[ $hookName ] = $hook;
			}
			else {
				unset( $wp_filter[ $hookName ] );
			}
		}
	}
}
