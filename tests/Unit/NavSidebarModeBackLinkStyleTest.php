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

class NavSidebarModeBackLinkStyleTest extends BaseUnitTest {

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

	public function test_back_item_is_exposed_only_for_non_home_modes() :void {
		$this->installControllerStubs();

		$this->installRequestServiceStub( [] );
		$homeSidebar = ( new NavMenuBuilder() )->build();
		$this->assertNull( $homeSidebar[ 'back_item' ] );

		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_SCANS,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_SCANS_OVERVIEW,
		] );
		$modeSidebar = ( new NavMenuBuilder() )->build();
		$this->assertSame( 'mode-selector-back', $modeSidebar[ 'back_item' ][ 'slug' ] ?? '' );
		$this->assertContains( 'sidebar-back-link', $modeSidebar[ 'back_item' ][ 'classes' ] ?? [] );
		$this->assertSame( 'icon-arrow-left', $modeSidebar[ 'back_item' ][ 'img' ] ?? '' );
	}

	public function test_non_security_admin_users_receive_disabled_classes_on_sidebar_links() :void {
		$this->installControllerStubs( false );
		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_ACTIVITY,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_LOGS,
		] );

		$sidebar = ( new NavMenuBuilder() )->build();
		foreach ( $sidebar[ 'mode_items' ] as $modeItem ) {
			$this->assertContains( 'disabled', $modeItem[ 'classes' ] ?? [] );
		}
		foreach ( $sidebar[ 'tool_items' ] as $toolItem ) {
			$this->assertContains( 'disabled', $toolItem[ 'classes' ] ?? [] );
		}
		$this->assertContains( 'disabled', $sidebar[ 'back_item' ][ 'classes' ] ?? [] );
	}

	private function installControllerStubs( bool $isPluginAdmin = true ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();

		$controller->cfg = (object)[
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			],
		];
		$controller->user_can_base_permissions = $isPluginAdmin;
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
