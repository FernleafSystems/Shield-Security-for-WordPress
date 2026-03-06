<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;

class NavMenuBuilderOperatorModesTest extends BaseUnitTest {

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

	public function test_home_mode_builds_structured_sidebar_contract() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [] );

		$sidebar = ( new NavMenuBuilder() )->build();

		$this->assertArrayHasKey( 'back_item', $sidebar );
		$this->assertArrayHasKey( 'mode_items', $sidebar );
		$this->assertArrayHasKey( 'tool_items', $sidebar );
		$this->assertArrayHasKey( 'home_license_item', $sidebar );
		$this->assertArrayHasKey( 'home_connect_title', $sidebar );
		$this->assertArrayHasKey( 'home_connect_items', $sidebar );

		$this->assertNull( $sidebar[ 'back_item' ] );
		$this->assertSame(
			[ 'mode-actions', 'mode-investigate', 'mode-configure', 'mode-reports' ],
			\array_column( $sidebar[ 'mode_items' ], 'slug' )
		);
		$this->assertSame(
			[],
			\array_column( $sidebar[ 'tool_items' ], 'slug' )
		);
		$this->assertSame( 'Connect', $sidebar[ 'home_connect_title' ] );
		$this->assertSame(
			[ 'connect-home', 'connect-facebook', 'connect-helpdesk', 'connect-newsletter' ],
			\array_column( $sidebar[ 'home_connect_items' ], 'slug' )
		);
		$this->assertSame(
			[ '_blank', '_blank', '_blank', '_blank' ],
			\array_column( $sidebar[ 'home_connect_items' ], 'target' )
		);
		$this->assertSame(
			[ 'icon-house-door', 'icon-facebook', 'icon-life-preserver', 'icon-envelope-paper' ],
			\array_column( $sidebar[ 'home_connect_items' ], 'img' )
		);
		$this->assertSame( PluginNavs::NAV_LICENSE, $sidebar[ 'home_license_item' ][ 'slug' ] ?? '' );
		$this->assertSame( 'Go PRO!', $sidebar[ 'home_license_item' ][ 'badge' ][ 'text' ] ?? '' );
		$this->assertSame( 'warning', $sidebar[ 'home_license_item' ][ 'badge' ][ 'status' ] ?? '' );
	}

	public function test_actions_mode_includes_back_item_and_actions_badge() :void {
		$this->installControllerStubs( false, false, [
			'has_items'   => true,
			'total_items' => 7,
			'severity'    => 'critical',
			'icon_class'  => 'bi bi-exclamation-triangle-fill',
			'subtext'     => 'Last scan: 4 minutes ago',
		] );
		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_SCANS,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_SCANS_RUN,
		] );

		$sidebar = ( new NavMenuBuilder() )->build();

		$this->assertSame( 'mode-selector-back', $sidebar[ 'back_item' ][ 'slug' ] ?? '' );
		$this->assertSame( '/admin/home', $sidebar[ 'back_item' ][ 'href' ] ?? '' );
		$this->assertContains( 'sidebar-back-link', $sidebar[ 'back_item' ][ 'classes' ] ?? [] );

		$actionsMode = \array_values( \array_filter(
			$sidebar[ 'mode_items' ],
			static fn( array $item ) :bool => ( $item[ 'slug' ] ?? '' ) === 'mode-actions'
		) )[ 0 ] ?? [];
		$this->assertTrue( (bool)( $actionsMode[ 'active' ] ?? false ) );
		$this->assertSame( '7', $actionsMode[ 'badge' ][ 'text' ] ?? '' );
		$this->assertSame( 'critical', $actionsMode[ 'badge' ][ 'status' ] ?? '' );

		$this->assertSame(
			[ PluginNavs::NAV_SCANS.'-'.PluginNavs::SUBNAV_SCANS_RUN ],
			\array_column( $sidebar[ 'tool_items' ], 'slug' )
		);
		$this->assertTrue( (bool)( $sidebar[ 'tool_items' ][ 0 ][ 'active' ] ?? false ) );
		$this->assertNull( $sidebar[ 'home_license_item' ] );
		$this->assertSame( '', $sidebar[ 'home_connect_title' ] );
		$this->assertSame( [], $sidebar[ 'home_connect_items' ] );
	}

	public function test_investigate_mode_tools_match_required_peers_without_parent_activity_item() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_ACTIVITY,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_LOGS,
		] );

		$sidebar = ( new NavMenuBuilder() )->build();
		$toolItems = $sidebar[ 'tool_items' ];

		$this->assertSame(
			[
				PluginNavs::NAV_IPS.'-'.PluginNavs::SUBNAV_IPS_RULES,
				PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_LOGS,
				PluginNavs::NAV_TRAFFIC.'-'.PluginNavs::SUBNAV_LOGS,
				PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_ACTIVITY_SESSIONS,
			],
			\array_column( $toolItems, 'slug' )
		);
		$this->assertSame(
			[ 'Bots & IP Rules', 'WP Activity Log', 'HTTP Request Log', 'User Sessions' ],
			\array_column( $toolItems, 'title' )
		);
		$this->assertNotContains( 'Activity Logs', \array_column( $toolItems, 'title' ) );
		$this->assertSame(
			[
				'/admin/ips/rules',
				'/admin/activity/logs',
				'/admin/traffic/logs',
				'/admin/activity/sessions',
			],
			\array_column( $toolItems, 'href' )
		);
		$this->assertFalse( (bool)( $toolItems[ 0 ][ 'active' ] ?? true ) );
		$this->assertTrue( (bool)( $toolItems[ 1 ][ 'active' ] ?? false ) );
		$this->assertFalse( (bool)( $toolItems[ 2 ][ 'active' ] ?? true ) );
		$this->assertFalse( (bool)( $toolItems[ 3 ][ 'active' ] ?? true ) );
	}

	public function test_investigate_sessions_route_marks_user_sessions_tool_active() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_ACTIVITY,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_ACTIVITY_SESSIONS,
		] );

		$sidebar = ( new NavMenuBuilder() )->build();
		$toolItems = $sidebar[ 'tool_items' ];

		$this->assertSame(
			[
				false,
				false,
				false,
				true,
			],
			\array_map(
				static fn( array $item ) :bool => (bool)( $item[ 'active' ] ?? false ),
				$toolItems
			)
		);
	}

	public function test_configure_mode_includes_guided_setup_and_debug_info_tools() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_TOOLS,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_TOOLS_DEBUG,
		] );

		$sidebar = ( new NavMenuBuilder() )->build();
		$toolItems = $sidebar[ 'tool_items' ];

		$this->assertSame(
			[
				PluginNavs::NAV_RULES.'-'.PluginNavs::SUBNAV_RULES_MANAGE,
				PluginNavs::NAV_RULES.'-'.PluginNavs::SUBNAV_RULES_BUILD,
				PluginNavs::NAV_TOOLS.'-'.PluginNavs::SUBNAV_TOOLS_BLOCKDOWN,
				PluginNavs::NAV_TOOLS.'-'.PluginNavs::SUBNAV_TOOLS_IMPORT,
				PluginNavs::NAV_WIZARD.'-'.PluginNavs::SUBNAV_WIZARD_WELCOME,
				PluginNavs::NAV_TOOLS.'-'.PluginNavs::SUBNAV_TOOLS_DEBUG,
				PluginNavs::NAV_TOOLS.'-whitelabel',
				PluginNavs::NAV_TOOLS.'-loginhide',
				PluginNavs::NAV_TOOLS.'-integrations',
			],
			\array_column( $toolItems, 'slug' )
		);
		$this->assertSame(
			[
				'Custom Rules Manager',
				'New Custom Rule',
				'Site Lockdown',
				'Import / Export',
				'Guided Setup',
				'Debug Info',
				'White Label',
				'Hide Login',
				'Integrations',
			],
			\array_column( $toolItems, 'title' )
		);
		$this->assertSame(
			[
				'/admin/rules/manage',
				'/admin/rules/build',
				'/admin/tools/blockdown',
				'/admin/tools/importexport',
				'/admin/merlin/welcome',
				'/admin/tools/debug',
			],
			\array_slice( \array_column( $toolItems, 'href' ), 0, 6 )
		);
		$this->assertTrue( (bool)( $toolItems[ 5 ][ 'active' ] ?? false ) );
		$this->assertFalse( (bool)( $toolItems[ 4 ][ 'active' ] ?? true ) );
	}

	public function test_configure_wizard_route_marks_guided_setup_tool_active() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub( [
			PluginNavs::FIELD_NAV    => PluginNavs::NAV_WIZARD,
			PluginNavs::FIELD_SUBNAV => PluginNavs::SUBNAV_WIZARD_WELCOME,
		] );

		$toolItems = ( new NavMenuBuilder() )->build()[ 'tool_items' ];

		$this->assertTrue( (bool)( $toolItems[ 4 ][ 'active' ] ?? false ) );
		$this->assertFalse( (bool)( $toolItems[ 5 ][ 'active' ] ?? true ) );
	}

	public function test_home_connect_items_are_omitted_for_whitelabel() :void {
		$this->installControllerStubs( true );
		$this->installRequestServiceStub( [] );

		$sidebar = ( new NavMenuBuilder() )->build();

		$this->assertSame( '', $sidebar[ 'home_connect_title' ] );
		$this->assertSame( [], $sidebar[ 'home_connect_items' ] );
	}

	private function installControllerStubs(
		bool $isWhitelabelled = false,
		bool $isPremium = false,
		array $queueSummary = [
			'has_items'   => false,
			'total_items' => 0,
			'severity'    => 'good',
			'icon_class'  => 'bi bi-shield-check',
			'subtext'     => '',
		]
	) :void {
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

			public function investigateUserSessions() :string {
				return '/admin/activity/sessions';
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
			'license'    => new class( $isPremium ) {
				private bool $isPremium;

				public function __construct( bool $isPremium ) {
					$this->isPremium = $isPremium;
				}

				public function hasValidWorkingLicense() :bool {
					return $this->isPremium;
				}
			},
			'whitelabel' => new class( $isWhitelabelled ) {
				private bool $enabled;

				public function __construct( bool $enabled ) {
					$this->enabled = $enabled;
				}

				public function isEnabled() :bool {
					return $this->enabled;
				}
			},
			'zones'      => new class {
				public function getZoneComponent( string $slug ) :object {
					return new class( $slug ) {
						private string $slug;

						public function __construct( string $slug ) {
							$this->slug = $slug;
						}

						public function getActions() :array {
							return [
								'config' => [
									'href'    => '/admin/zone/'.$this->slug,
									'active'  => false,
									'classes' => [],
								],
							];
						}
					};
				}
			},
		];
		$controller->action_router = new class( $queueSummary ) {
			private array $queueSummary;

			public function __construct( array $queueSummary ) {
				$this->queueSummary = $queueSummary;
			}

			public function action( string $class ) :object {
				return new class( $this->queueSummary ) {
					private array $queueSummary;

					public function __construct( array $queueSummary ) {
						$this->queueSummary = $queueSummary;
					}

					public function payload() :array {
						return [
							'render_data' => [
								'vars' => [
									'summary' => $this->queueSummary,
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
