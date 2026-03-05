<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;

class NavSidebarTemplateTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'apply_filters' )->alias( static fn( string $tag, $value ) => $value );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_home_sidebar_contract_includes_mode_tools_license_and_connect_sections() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [] );

		$sidebar = ( new NavMenuBuilder() )->build();

		$this->assertNull( $sidebar[ 'back_item' ] );
		$this->assertCount( 4, $sidebar[ 'mode_items' ] );
		$this->assertCount( 3, $sidebar[ 'tool_items' ] );
		$this->assertNotNull( $sidebar[ 'home_license_item' ] );
		$this->assertSame( 'Connect', $sidebar[ 'home_connect_title' ] );
		$this->assertCount( 4, $sidebar[ 'home_connect_items' ] );
	}

	public function test_mode_sidebar_contract_replaces_home_sections_with_back_link_and_mode_tools() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_ACTIVITY,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_LOGS,
		] );

		$sidebar = ( new NavMenuBuilder() )->build();

		$this->assertSame( 'mode-selector-back', $sidebar[ 'back_item' ][ 'slug' ] ?? '' );
		$this->assertSame( '/admin/home', $sidebar[ 'back_item' ][ 'href' ] ?? '' );
		$this->assertSame( 'Dashboard', $sidebar[ 'back_item' ][ 'title' ] ?? '' );
		$this->assertSame(
			[ 'Bots & IP Rules', 'WP Activity Log', 'HTTP Request Log' ],
			\array_column( $sidebar[ 'tool_items' ], 'title' )
		);
		$this->assertNull( $sidebar[ 'home_license_item' ] );
		$this->assertSame( '', $sidebar[ 'home_connect_title' ] );
		$this->assertSame( [], $sidebar[ 'home_connect_items' ] );
	}

	public function test_each_mode_item_is_a_flat_link_contract_for_sidebar_rendering() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [] );

		$modeItems = ( new NavMenuBuilder() )->build()[ 'mode_items' ];
		foreach ( $modeItems as $modeItem ) {
			$this->assertNotSame( '', $modeItem[ 'slug' ] ?? '' );
			$this->assertNotSame( '', $modeItem[ 'mode' ] ?? '' );
			$this->assertNotSame( '', $modeItem[ 'title' ] ?? '' );
			$this->assertNotSame( '', $modeItem[ 'href' ] ?? '' );
			$this->assertIsArray( $modeItem[ 'classes' ] ?? null );
			$this->assertContains( 'mode-item-link', $modeItem[ 'classes' ] ?? [] );
		}
	}

	private function installControllerStubs() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();

		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->user_can_base_permissions = true;
		$controller->plugin_urls = new class {
			public function adminHome() :string {
				return '/admin/home';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function adminIpRules() :string {
				return '/admin/ips/rules';
			}

			public function wizard( string $step ) :string {
				return '/admin/wizard/'.$step;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $slug ) :string {
				return 'icon-'.$slug;
			}
		};
		$controller->labels = (object)[
			'url_helpdesk' => 'https://help.example.com',
			'Name'         => 'Shield',
		];
		$controller->comps = (object)[
			'license'    => new class {
				public function hasValidWorkingLicense() :bool {
					return false;
				}
			},
			'whitelabel' => new class {
				public function isEnabled() :bool {
					return false;
				}
			},
		];
		$controller->action_router = new class {
			public function action( string $class ) :object {
				return new class {
					public function payload() :array {
						return [
							'render_data' => [
								'vars' => [
									'summary' => [
										'has_items'   => false,
										'total_items' => 0,
										'severity'    => 'good',
										'icon_class'  => 'bi bi-shield-check',
										'subtext'     => '',
									],
								],
							],
						];
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function installRequestServiceStub( array $query ) :void {
		$_GET = $query;
		ServicesState::installItems( [
			'service_request'          => new Request(),
			'service_datamanipulation' => new DataManipulation(),
		] );
	}
}
