<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealthController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Rest
};

class SiteHealthControllerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private bool $isAdmin = false;

	private bool $isNetworkAdmin = false;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();

		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'is_admin' )->alias( fn() :bool => $this->isAdmin );
		Functions\when( 'is_network_admin' )->alias( fn() :bool => $this->isNetworkAdmin );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	/**
	 * @dataProvider providerSiteHealthContexts
	 */
	public function test_execute_registers_only_the_capability_filter( bool $isAdmin, bool $isAjax ) :void {
		$this->isAdmin = $isAdmin;
		$this->installSiteHealthRuntime( $isAjax );

		$registered = [];
		$this->trackRegisteredHooks( $registered );

		( new SiteHealthController() )->execute();

		$this->assertSame( [ $this->hookRecord( 'filter', 'user_has_cap', 20, 4 ) ], $registered );
	}

	public static function providerSiteHealthContexts() :array {
		return [
			'admin page' => [ true, false ],
			'ajax'       => [ false, true ],
		];
	}

	public function test_execute_registers_only_capability_filter_for_site_health_rest_context() :void {
		$this->installSiteHealthRuntime(
			false,
			true,
			'wp-site-health/v1/tests/loopback-requests'
		);

		$registered = [];
		$this->trackRegisteredHooks( $registered );

		( new SiteHealthController() )->execute();

		$this->assertSame( [ $this->hookRecord( 'filter', 'user_has_cap', 20, 4 ) ], $registered );
	}

	public function test_can_run_for_site_health_rest_async_test_route() :void {
		$this->installSiteHealthRuntime(
			false,
			true,
			'wp-site-health/v1/tests/loopback-requests'
		);

		$this->assertTrue( ( new ExposedSiteHealthController() )->exposeCanRun() );
	}

	public function test_can_run_for_site_health_directory_sizes_rest_route() :void {
		$this->installSiteHealthRuntime(
			false,
			true,
			'wp-site-health/v1/directory-sizes'
		);

		$this->assertTrue( ( new ExposedSiteHealthController() )->exposeCanRun() );
	}

	public function test_can_run_rejects_non_site_health_rest_route() :void {
		$this->installSiteHealthRuntime(
			false,
			true,
			'wp/v2/users/me'
		);

		$this->assertFalse( ( new ExposedSiteHealthController() )->exposeCanRun() );
	}

	public function test_can_run_rejects_site_health_namespace_prefix_collision() :void {
		$this->installSiteHealthRuntime(
			false,
			true,
			'wp-site-health/v10/tests/loopback-requests'
		);

		$this->assertFalse( ( new ExposedSiteHealthController() )->exposeCanRun() );
	}

	public function test_can_run_requires_wordpress_58() :void {
		$this->isAdmin = true;
		$this->installSiteHealthRuntime( false, false, '', true, false );

		$this->assertFalse( ( new ExposedSiteHealthController() )->exposeCanRun() );
	}

	public function test_security_admin_access_grants_site_health_capability() :void {
		$this->installController( true, true );

		$result = ( new SiteHealthController() )->filterSiteHealthCapability(
			[ 'install_plugins' => false ],
			[],
			[ SiteHealthController::SITE_HEALTH_CAP, 1 ],
			null
		);

		$this->assertTrue( $result[ SiteHealthController::SITE_HEALTH_CAP ] );
	}

	public function test_non_security_admin_access_denies_site_health_capability() :void {
		$this->installController( true, false );

		$result = ( new SiteHealthController() )->filterSiteHealthCapability(
			[ 'install_plugins' => true ],
			[],
			[ SiteHealthController::SITE_HEALTH_CAP, 1 ],
			null
		);

		$this->assertFalse( $result[ SiteHealthController::SITE_HEALTH_CAP ] );
	}

	public function test_disabled_security_admin_preserves_wordpress_default_capabilities() :void {
		$this->installController( false, false );

		$allCaps = [ 'install_plugins' => true ];
		$result = ( new SiteHealthController() )->filterSiteHealthCapability(
			$allCaps,
			[ SiteHealthController::SITE_HEALTH_CAP ],
			[ SiteHealthController::SITE_HEALTH_CAP, 1 ],
			null
		);

		$this->assertSame( $allCaps, $result );
	}

	public function test_other_capability_checks_are_unchanged() :void {
		$this->installController( true, true );

		$allCaps = [ 'manage_options' => true ];
		$result = ( new SiteHealthController() )->filterSiteHealthCapability(
			$allCaps,
			[ 'manage_options' ],
			[ 'manage_options', 1 ],
			null
		);

		$this->assertSame( $allCaps, $result );
	}

	private function trackRegisteredHooks( array &$registered ) :void {
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$registered ) :bool {
				$registered[] = [
					'type'          => 'filter',
					'hook'          => $hook,
					'priority'      => $priority,
					'accepted_args' => $acceptedArgs,
				];
				return true;
			}
		);
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1 ) use ( &$registered ) :bool {
				$registered[] = [
					'type'          => 'action',
					'hook'          => $hook,
					'priority'      => $priority,
					'accepted_args' => $acceptedArgs,
				];
				return true;
			}
		);
	}

	private function hookRecord( string $type, string $hook, int $priority = 10, int $acceptedArgs = 1 ) :array {
		return [
			'type'          => $type,
			'hook'          => $hook,
			'priority'      => $priority,
			'accepted_args' => $acceptedArgs,
		];
	}

	private function installSiteHealthRuntime(
		bool $isAjax = false,
		bool $isRest = false,
		string $restRoute = '',
		bool $pluginEnabled = true,
		bool $wpAtLeastVersion = true
	) :void {
		$this->installServices( $isAjax, $isRest, $wpAtLeastVersion );
		$this->installController( true, true, $pluginEnabled, $restRoute );
	}

	private function installServices( bool $isAjax, bool $isRest, bool $wpAtLeastVersion ) :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new class( $isAjax, $wpAtLeastVersion ) extends General {
				private bool $isAjax;

				private bool $wpAtLeastVersion;

				public function __construct( bool $isAjax, bool $wpAtLeastVersion ) {
					$this->isAjax = $isAjax;
					$this->wpAtLeastVersion = $wpAtLeastVersion;
				}

				public function getWordpressIsAtLeastVersion( string $version, bool $ignoreCP = true ) :bool {
					return $version === '5.8' && $this->wpAtLeastVersion;
				}

				public function isAjax() :bool {
					return $this->isAjax;
				}
			},
			'service_rest'      => new class( $isRest ) extends Rest {
				private bool $isRest;

				public function __construct( bool $isRest ) {
					$this->isRest = $isRest;
				}

				public function isRest() :bool {
					return $this->isRest;
				}
			},
		] );
	}

	private function installController(
		bool $securityAdminEnabled,
		bool $isSecurityAdmin,
		bool $pluginEnabled = true,
		string $restRoute = ''
	) :void {
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps'    => (object)[
					'sec_admin'   => new class( $securityAdminEnabled ) {
						private bool $enabled;

						public function __construct( bool $enabled ) {
							$this->enabled = $enabled;
						}

						public function isEnabledSecAdmin() :bool {
							return $this->enabled;
						}
					},
					'opts_lookup' => new class( $pluginEnabled ) {
						private bool $pluginEnabled;

						public function __construct( bool $pluginEnabled ) {
							$this->pluginEnabled = $pluginEnabled;
						}

						public function isPluginEnabled() :bool {
							return $this->pluginEnabled;
						}
					},
				],
				'this_req' => new class( $isSecurityAdmin, $restRoute ) {
					public bool $is_security_admin;

					private string $restRoute;

					public function __construct( bool $isSecurityAdmin, string $restRoute ) {
						$this->is_security_admin = $isSecurityAdmin;
						$this->restRoute = $restRoute;
					}

					public function getRestRoute() :string {
						return $this->restRoute;
					}
				},
			]
		);
	}
}

final class ExposedSiteHealthController extends SiteHealthController {

	public function exposeCanRun() :bool {
		return $this->canRun();
	}
}
