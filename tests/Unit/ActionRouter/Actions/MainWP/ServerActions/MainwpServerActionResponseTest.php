<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {
	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace MainWP\Dashboard {
	if ( !\class_exists( MainWP_Connect::class ) ) {
		class MainWP_Connect {
			public static array $fetchCalls = [];

			public static array $fetchResponse = [];

			public static function fetch_url_authed( $site, string $action, array $params = [] ) :array {
				self::$fetchCalls[] = [
					'site'   => $site,
					'action' => $action,
					'params' => $params,
				];
				return self::$fetchResponse;
			}
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Actions\MainWP\ServerActions {

	use Brain\Monkey\Functions;
	use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
		LicenseLookup,
		PluginSetOpt
	};
	use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\{
		BaseSiteMwpAction,
		SiteActionUpdate,
		SiteCustomAction
	};
	use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
	use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
	use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MWPSiteVO;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
	use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
		PluginControllerInstaller,
		UnitTestControllerFactory
	};
	use MainWP\Dashboard\MainWP_Connect;

	class MainwpServerActionResponseTest extends BaseUnitTest {

		protected function setUp() :void {
			parent::setUp();
			Functions\when( '__' )->returnArg();
			MainWP_Connect::$fetchCalls = [];
			MainWP_Connect::$fetchResponse = [ 'status' => 'SUCCESS' ];
			UnitTestControllerFactory::install(
				null,
				null,
				(object)[
					'cfg'    => (object)[
						'properties' => [
							'slug_parent' => 'shield',
							'slug_plugin' => 'security',
						],
					],
					'labels' => (object)[
						'Name' => 'Shield',
					],
				]
			);
		}

		protected function tearDown() :void {
			PluginControllerInstaller::reset();
			parent::tearDown();
		}

		public function test_custom_action_success_requires_child_payload_success() :void {
			$action = new SiteCustomAction();
			$this->setClientActionResponse( $action, [
				'shield-security-mwp-action-response' => \json_encode( [ 'success' => true ] ),
			] );

			$this->assertTrue( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'shield-security-mwp-action-response' => \json_encode( [ 'success' => false ] ),
			] );

			$this->assertFalse( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'shield-security-mwp-action-response' => \json_encode( [ 'message' => 'no-success-key' ] ),
			] );

			$this->assertFalse( $this->checkResponse( $action ) );
		}

		public function test_custom_action_rejects_missing_or_invalid_child_payload() :void {
			$action = new SiteCustomAction();

			$this->setClientActionResponse( $action, [] );
			$this->expectException( ActionException::class );
			$this->checkResponse( $action );
		}

		public function test_custom_action_rejects_invalid_json_child_payload() :void {
			$action = new SiteCustomAction();

			$this->setClientActionResponse( $action, [
				'shield-security-mwp-action-response' => 'not-json',
			] );
			$this->expectException( ActionException::class );
			$this->checkResponse( $action );
		}

		public function test_custom_action_rejects_non_string_child_payload() :void {
			$action = new SiteCustomAction();

			$this->setClientActionResponse( $action, [
				'shield-security-mwp-action-response' => [ 'success' => true ],
			] );
			$this->expectException( ActionException::class );
			$this->checkResponse( $action );
		}

		public function test_custom_action_requires_sub_action_slug() :void {
			$method = new \ReflectionMethod( SiteCustomAction::class, 'getRequiredDataKeys' );
			$method->setAccessible( true );

			$this->assertSame(
				[
					'client_site_id',
					'sub_action_slug',
				],
				$method->invoke( new SiteCustomAction() )
			);
		}

		public function test_custom_action_sends_extra_execution_payload_with_canonical_override() :void {
			$action = $this->customAction( [
				'sub_action_slug'   => PluginSetOpt::SLUG,
				'sub_action_params' => [
					'opt_key'          => 'enable_mainwp',
					'opt_value'        => 'Y',
					'action_overrides' => [
						Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => true,
						'untrusted_override'                               => true,
					],
				],
			] );

			$this->assertSame( [ 'status' => 'SUCCESS' ], $this->fireCustomAction( $action ) );
			$this->assertCount( 1, MainWP_Connect::$fetchCalls );

			$call = MainWP_Connect::$fetchCalls[ 0 ];
			$this->assertSame( 'extra_execution', $call[ 'action' ] );
			$this->assertSame( 42, $call[ 'site' ]->id );
			$this->assertSame( PluginSetOpt::SLUG, $call[ 'params' ][ 'shield-security-mwp-action' ] );
			$this->assertSame( 'enable_mainwp', $call[ 'params' ][ 'shield-security-mwp-params' ][ 'opt_key' ] );
			$this->assertSame( 'Y', $call[ 'params' ][ 'shield-security-mwp-params' ][ 'opt_value' ] );
			$this->assertSame(
				[ Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false ],
				$call[ 'params' ][ 'shield-security-mwp-params' ][ 'action_overrides' ]
			);
		}

		public function test_custom_action_treats_malformed_sub_action_params_as_empty() :void {
			$action = $this->customAction( [
				'sub_action_slug'   => PluginSetOpt::SLUG,
				'sub_action_params' => 'not-array',
			] );

			$this->assertSame( [ 'status' => 'SUCCESS' ], $this->fireCustomAction( $action ) );
			$call = MainWP_Connect::$fetchCalls[ 0 ];
			$this->assertSame(
				[ Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false ],
				$call[ 'params' ][ 'shield-security-mwp-params' ][ 'action_overrides' ]
			);
			$this->assertArrayNotHasKey( 'opt_key', $call[ 'params' ][ 'shield-security-mwp-params' ] );
		}

		public function test_custom_action_request_shape_supports_license_lookup() :void {
			$action = $this->customAction( [
				'sub_action_slug' => LicenseLookup::SLUG,
			] );

			$this->fireCustomAction( $action );
			$call = MainWP_Connect::$fetchCalls[ 0 ];

			$this->assertSame( 'extra_execution', $call[ 'action' ] );
			$this->assertSame( LicenseLookup::SLUG, $call[ 'params' ][ 'shield-security-mwp-action' ] );
			$this->assertSame(
				[ Constants::ACTION_OVERRIDE_IS_NONCE_VERIFY_REQUIRED => false ],
				$call[ 'params' ][ 'shield-security-mwp-params' ][ 'action_overrides' ]
			);
		}

		public function test_update_action_success_requires_matching_plugin_upgrade() :void {
			$action = $this->updateAction( 'shield/shield.php' );

			$this->setClientActionResponse( $action, [
				'upgrades' => [
					'shield/shield.php' => true,
				],
			] );
			$this->assertTrue( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'upgrades' => [
					'shield/shield.php' => 1,
				],
			] );
			$this->assertTrue( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'upgrades' => [
					'shield/shield.php' => false,
				],
			] );
			$this->assertFalse( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'upgrades' => [
					'shield/shield.php' => 0,
				],
			] );
			$this->assertFalse( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'upgrades' => [
					'shield/shield.php' => '1',
				],
			] );
			$this->assertFalse( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'upgrades' => 'not-array',
			] );
			$this->assertFalse( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, [
				'upgrades' => [
					'other/plugin.php' => true,
				],
			] );
			$this->assertFalse( $this->checkResponse( $action ) );

			$this->setClientActionResponse( $action, false );
			$this->assertFalse( $this->checkResponse( $action ) );

			$emptySlugAction = $this->updateAction( '' );
			$this->setClientActionResponse( $emptySlugAction, [
				'upgrades' => [
					'shield/shield.php' => true,
				],
			] );
			$this->assertFalse( $this->checkResponse( $emptySlugAction ) );
		}

		public function test_update_action_params_use_installed_plugin_slug() :void {
			$this->assertSame(
				[
					'type' => 'plugin',
					'list' => 'shield/shield.php',
				],
				$this->updateAction( 'shield/shield.php' )->mainwpActionParamsForTest()
			);
		}

		private function setClientActionResponse( BaseSiteMwpAction $action, $response ) :void {
			$property = new \ReflectionProperty( BaseSiteMwpAction::class, 'clientActionResponse' );
			$property->setAccessible( true );
			$property->setValue( $action, $response );
		}

		private function checkResponse( BaseSiteMwpAction $action ) :bool {
			$method = new \ReflectionMethod( $action, 'checkResponse' );
			$method->setAccessible( true );
			return (bool)$method->invoke( $action );
		}

		private function fireCustomAction( SiteCustomAction $action ) :array {
			$method = new \ReflectionMethod( $action, 'fireClientSiteAction' );
			$method->setAccessible( true );
			return $method->invoke( $action );
		}

		private function customAction( array $actionData ) :SiteCustomAction {
			return new class( $actionData ) extends SiteCustomAction {
				protected function getMwpSite() :MWPSiteVO {
					return new class extends MWPSiteVO {
						public function __get( string $key ) {
							return $key === 'siteobj' ? (object)[ 'id' => 42 ] : parent::__get( $key );
						}
					};
				}
			};
		}

		private function updateAction( string $pluginSlug ) :SiteActionUpdate {
			return new class( $pluginSlug ) extends SiteActionUpdate {
				private string $pluginSlug;

				public function __construct( string $pluginSlug ) {
					parent::__construct();
					$this->pluginSlug = $pluginSlug;
				}

				public function mainwpActionParamsForTest() :array {
					return $this->getMainwpActionParams();
				}

				protected function getInstalledPluginSlug() :string {
					return $this->pluginSlug;
				}
			};
		}
	}
}
