<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {
	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace MainWP\Child {
	if ( !\class_exists( MainWP_Child::class ) ) {
		class MainWP_Child {
			public static string $version = '4.1';
		}
	}

	if ( !\class_exists( MainWP_Connect::class ) ) {
		class MainWP_Connect {
			public static bool $throwAuth = false;

			public static $authResponse = false;

			public static array $authCalls = [];

			public static function instance() :self {
				return new self();
			}

			public function auth( $signature, $func, $nonce, $nossl = null ) {
				self::$authCalls[] = [
					'signature' => $signature,
					'func'      => $func,
					'nonce'     => $nonce,
					'nossl'     => $nossl,
				];

				if ( self::$throwAuth ) {
					throw new \RuntimeException( 'MainWP auth failed' );
				}

				return self::$authResponse;
			}
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Integrations\Lib\MainWP\Client\Actions {

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
		Actions\PluginBadgeClose,
		Constants
	};
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions\Init;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
		PluginControllerInstaller,
		ServicesState,
		UnitTestControllerFactory,
		UnitTestUsers
	};
	use FernleafSystems\Wordpress\Services\Core\Request;
	use MainWP\Child\MainWP_Connect;

	class InitTest extends BaseUnitTest {

		private array $servicesSnapshot = [];

		private $badge;

		protected function setUp() :void {
			parent::setUp();
			if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
				\define( 'HOUR_IN_SECONDS', 3600 );
			}

			$this->servicesSnapshot = ServicesState::snapshot();
			Functions\when( '__' )->returnArg();
			Functions\when( 'esc_html' )->returnArg();
			Functions\when( 'wp_json_encode' )->alias( static fn( $value ) => \json_encode( $value ) );
			Functions\when( 'wp_hash' )->alias(
				static fn( string $data, string $scheme = 'auth' ) :string => \hash( 'sha256', $scheme.'|'.$data )
			);
			Functions\when( 'sanitize_text_field' )->alias( static fn( $value ) => \is_string( $value ) ? \trim( $value ) : '' );

			MainWP_Connect::$authResponse = false;
			MainWP_Connect::$throwAuth = false;
			MainWP_Connect::$authCalls = [];
			$this->installController();
			$this->installRequest();
		}

		protected function tearDown() :void {
			PluginControllerInstaller::reset();
			ServicesState::restore( $this->servicesSnapshot );
			parent::tearDown();
		}

		public function test_run_registers_current_mfa_skip_hook_only() :void {
			$filters = [];
			$actions = [];

			Functions\when( 'add_filter' )->alias(
				static function ( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$filters ) :bool {
					$filters[] = [
						'hook'          => $hook,
						'callback'      => $callback,
						'priority'      => $priority,
						'accepted_args' => $acceptedArgs,
					];
					return true;
				}
			);
			Functions\when( 'add_action' )->alias(
				static function ( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$actions ) :bool {
					$actions[] = [
						'hook'          => $hook,
						'callback'      => $callback,
						'priority'      => $priority,
						'accepted_args' => $acceptedArgs,
					];
					return true;
				}
			);

			( new Init() )->run();

			$filterHooks = \array_column( $filters, 'hook' );
			$this->assertContains( 'shield/2fa_skip', $filterHooks );
			$this->assertContains( 'mainwp_child_extra_execution', $filterHooks );
			$this->assertNotContains( 'icwp_shield_2fa_skip', $filterHooks );
			$this->assertNotContains( 'odp-shield-2fa_skip', $filterHooks );
			$this->assertContains( 'mainwp_child_site_stats', \array_column( $actions, 'hook' ) );
		}

		public function test_mfa_skip_callback_reuses_mainwp_auth_replay() :void {
			$filters = [];
			Functions\when( 'add_action' )->justReturn( true );
			Functions\when( 'add_filter' )->alias(
				static function ( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$filters ) :bool {
					$filters[ $hook ] = $callback;
					return true;
				}
			);

			( new Init() )->run();
			$callback = $filters[ 'shield/2fa_skip' ];

			$this->assertTrue( $callback( true ) );
			$this->assertSame( [], MainWP_Connect::$authCalls );

			MainWP_Connect::$authResponse = true;
			$this->assertTrue( $callback( false ) );

			MainWP_Connect::$authResponse = false;
			$this->assertFalse( $callback( false ) );
		}

		public function test_extra_execution_ignores_non_shield_payload_without_auth_replay() :void {
			$callback = $this->extraExecutionCallback();
			$information = [ 'existing' => 'kept' ];

			$this->assertSame( $information, $callback( $information, [ 'function' => 'extra_execution' ] ) );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 0, $this->badge->closedCount );
		}

		public function test_extra_execution_applies_overrides_inside_mainwp_context_without_auth_replay() :void {
			$callback = $this->extraExecutionCallback();
			$result = $callback( [], [
				'shield-security-mwp-action' => PluginBadgeClose::SLUG,
				'shield-security-mwp-params' => [
					'action_overrides' => [
						Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false,
					],
				],
			] );

			$response = $this->decodeActionResponse( $result );
			$this->assertSame( true, $response[ 'success' ] );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 1, $this->badge->closedCount );
		}

		public function test_site_sync_normalizes_non_array_callback_arguments() :void {
			$filters = $this->registeredFilterCallbacks();
			$callback = $filters[ 'mainwp_site_sync_others_data' ];

			$this->assertSame( [], $callback( new \stdClass(), new \stdClass() ) );
			$this->assertSame( [], MainWP_Connect::$authCalls );
		}

		public function test_extra_execution_normalizes_non_array_callback_arguments() :void {
			$callback = $this->extraExecutionCallback();

			$this->assertSame( [], $callback( new \stdClass(), new \stdClass() ) );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 0, $this->badge->closedCount );
		}

		public function test_extra_execution_ignores_numeric_override_keys_and_keeps_valid_sibling() :void {
			$callback = $this->extraExecutionCallback();
			$result = $callback( [], [
				'shield-security-mwp-action' => PluginBadgeClose::SLUG,
				'shield-security-mwp-params' => [
					'action_overrides' => [
						0 => true,
						'123' => false,
						Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false,
					],
				],
			] );

			$response = $this->decodeActionResponse( $result );
			$this->assertTrue( (bool)$response[ 'success' ] );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 1, $this->badge->closedCount );
		}

		public function test_extra_execution_without_override_fails_nonce_and_does_not_auth_replay() :void {
			$callback = $this->extraExecutionCallback();
			$result = $callback( [], [
				'shield-security-mwp-action' => PluginBadgeClose::SLUG,
				'shield-security-mwp-params' => [],
			] );

			$response = $this->decodeActionResponse( $result );
			$this->assertSame( false, $response[ 'success' ] );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 0, $this->badge->closedCount );
		}

		public function test_extra_execution_malformed_params_are_treated_as_empty_without_auth_replay() :void {
			$callback = $this->extraExecutionCallback();
			$result = $callback( [], [
				'shield-security-mwp-action' => PluginBadgeClose::SLUG,
				'shield-security-mwp-params' => 'not-array',
			] );

			$response = $this->decodeActionResponse( $result );
			$this->assertSame( false, $response[ 'success' ] );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 0, $this->badge->closedCount );
		}

		public function test_extra_execution_ignores_malformed_action_payload_without_auth_replay() :void {
			$callback = $this->extraExecutionCallback();
			$information = [ 'existing' => 'kept' ];

			$this->assertSame( $information, $callback( $information, [
				'shield-security-mwp-action' => [ PluginBadgeClose::SLUG ],
				'shield-security-mwp-params' => [
					'action_overrides' => [
						Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false,
					],
				],
			] ) );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 0, $this->badge->closedCount );
		}

		public function test_extra_execution_unknown_action_returns_failure_without_auth_replay() :void {
			$callback = $this->extraExecutionCallback();
			$result = $callback( [], [
				'shield-security-mwp-action' => 'missing_mainwp_action',
				'shield-security-mwp-params' => [
					'action_overrides' => [
						Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false,
					],
				],
			] );

			$response = $this->decodeActionResponse( $result );
			$this->assertSame( false, $response[ 'success' ] );
			$this->assertSame( [], MainWP_Connect::$authCalls );
			$this->assertSame( 0, $this->badge->closedCount );
		}

		private function extraExecutionCallback() :callable {
			return $this->registeredFilterCallbacks()[ 'mainwp_child_extra_execution' ];
		}

		private function registeredFilterCallbacks() :array {
			$filters = [];
			Functions\when( 'add_action' )->justReturn( true );
			Functions\when( 'add_filter' )->alias(
				static function ( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$filters ) :bool {
					unset( $priority, $acceptedArgs );
					$filters[ $hook ] = $callback;
					return true;
				}
			);

			( new Init() )->run();
			return $filters;
		}

		private function decodeActionResponse( array $result ) :array {
			$this->assertArrayHasKey( 'shield-security-mwp-action-response', $result );
			$this->assertIsString( $result[ 'shield-security-mwp-action-response' ] );
			$decoded = \json_decode( $result[ 'shield-security-mwp-action-response' ], true );
			$this->assertIsArray( $decoded );
			$this->assertArrayHasKey( 'success', $decoded );
			return $decoded;
		}

		private function installController() :void {
			$this->badge = new class {
				public int $closedCount = 0;

				public function setBadgeStateClosed() :bool {
					$this->closedCount++;
					return true;
				}
			};

			UnitTestControllerFactory::install(
				null,
				null,
				(object)[
					'cfg'      => (object)[
						'properties' => [
							'slug_parent'      => 'shield',
							'slug_plugin'      => 'security',
							'base_permissions' => 'manage_options',
						],
					],
					'comps'    => (object)[
						'badge' => $this->badge,
					],
					'this_req' => (object)[
						'ip'                                => '127.0.0.1',
						'request_bypasses_all_restrictions' => false,
						'is_ip_blocked'                     => false,
						'wp_is_ajax'                        => true,
						'is_security_admin'                 => false,
					],
				]
			);
		}

		private function installRequest() :void {
			ServicesState::installItems( [
				'service_request' => new class extends Request {
					public function request( $key, $includeCookies = false, $default = null ) {
						unset( $includeCookies );
						$values = [
							'mainwpsignature' => 'signature',
							'function'        => 'extra_execution',
							'nonce'           => '1234',
							'nossl'           => '0',
						];
						return $values[ $key ] ?? $default;
					}

					public function ip() :string {
						return '127.0.0.1';
					}

					public function ts( bool $update = true ) :int {
						unset( $update );
						return 1700000000;
					}
				},
				'service_wpusers' => new UnitTestUsers( 1 ),
			] );
		}
	}
}
