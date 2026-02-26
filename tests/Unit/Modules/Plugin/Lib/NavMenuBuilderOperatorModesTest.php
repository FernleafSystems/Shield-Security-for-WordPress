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

class NavMenuBuilderOperatorModesTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testConfigureModePrependsBackLinkAndSecurityGradesThenFilteredItems() :void {
		$this->installControllerStubs();

		$menu = $this->invokeBuildModeNav( $this->baseMenuFixture(), PluginNavs::MODE_CONFIGURE );

		$this->assertSame(
			[
				'mode-selector-back',
				'mode-configure-grades',
				PluginNavs::NAV_ZONES,
				PluginNavs::NAV_RULES,
				PluginNavs::NAV_TOOLS,
			],
			\array_column( $menu, 'slug' )
		);

		$backLink = $menu[ 0 ];
		$this->assertSame( 'Back to Dashboard', $backLink[ 'title' ] );
		$this->assertSame( 'icon-speedometer', $backLink[ 'img' ] );
		$this->assertSame( 'backlink', $backLink[ 'group' ] );
		$this->assertSame( [ 'mode-back-link' ], $backLink[ 'classes' ] );

		$grades = $menu[ 1 ];
		$this->assertSame( 'Security Grades', $grades[ 'title' ] );
		$this->assertSame( '/admin/dashboard/grades', $grades[ 'href' ] );
		$this->assertFalse( $grades[ 'active' ] );
		$this->assertSame( [], $grades[ 'classes' ] );
		$this->assertSame( [], $grades[ 'sub_items' ] );
		$this->assertSame( 'icon-speedometer', $grades[ 'img' ] );
		$this->assertSame( 'Security At A Glance', $grades[ 'subtitle' ] );
	}

	public function testNonConfigureModeDoesNotAddSyntheticSecurityGradesItem() :void {
		$this->installControllerStubs();

		$menu = $this->invokeBuildModeNav( $this->baseMenuFixture(), PluginNavs::MODE_ACTIONS );
		$slugs = \array_column( $menu, 'slug' );

		$this->assertSame(
			[
				'mode-selector-back',
				PluginNavs::NAV_SCANS,
			],
			$slugs
		);
		$this->assertNotContains( 'mode-configure-grades', $slugs );
		$this->assertSame( 'backlink', $menu[ 0 ][ 'group' ] );
		$this->assertSame( [ 'mode-back-link' ], $menu[ 0 ][ 'classes' ] );
		$this->assertSame( 'primary', $menu[ 1 ][ 'group' ] );
	}

	public function testConfigureModeGradesItemUsesEmptyMetadataWhenDashboardSourceMissing() :void {
		$this->installControllerStubs();

		$baseMenu = \array_values( \array_filter(
			$this->baseMenuFixture(),
			fn( array $item ) :bool => ( $item[ 'slug' ] ?? '' ) !== PluginNavs::NAV_DASHBOARD
		) );

		$menu = $this->invokeBuildModeNav( $baseMenu, PluginNavs::MODE_CONFIGURE );
		$grades = $menu[ 1 ];

		$this->assertSame( 'mode-configure-grades', $grades[ 'slug' ] );
		$this->assertSame( '', $grades[ 'img' ] );
		$this->assertSame( '', $grades[ 'subtitle' ] );
		$this->assertSame( 'primary', $grades[ 'group' ] );
	}

	public function testActivityIncludesByUserAndByIpInvestigateEntries() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub();

		$activity = $this->invokeActivity();
		$subItems = $activity[ 'sub_items' ] ?? [];

		$this->assertSame(
			[
				PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_ACTIVITY_BY_USER,
				PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_ACTIVITY_BY_IP,
				PluginNavs::NAV_ACTIVITY.'-'.PluginNavs::SUBNAV_LOGS,
				PluginNavs::NAV_TRAFFIC.'-'.PluginNavs::SUBNAV_LOGS,
				PluginNavs::NAV_TRAFFIC.'-'.PluginNavs::SUBNAV_LIVE,
			],
			\array_column( $subItems, 'slug' )
		);
		$this->assertSame( '/admin/activity/by_user', $subItems[ 0 ][ 'href' ] ?? '' );
		$this->assertSame( '/admin/activity/by_ip', $subItems[ 1 ][ 'href' ] ?? '' );
	}

	public function testReportsIncludesListChartsAndSettingsSubItems() :void {
		$this->installControllerStubs();
		$this->installRequestServiceStub();

		$reports = $this->invokeReports();
		$subItems = $reports[ 'sub_items' ] ?? [];

		$this->assertSame(
			[
				PluginNavs::NAV_REPORTS.'-'.PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::NAV_REPORTS.'-'.PluginNavs::SUBNAV_REPORTS_CHARTS,
				PluginNavs::NAV_REPORTS.'-'.PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_column( $subItems, 'slug' )
		);
		$this->assertSame(
			[
				'/admin/reports/list',
				'/admin/reports/charts',
				'/admin/reports/settings',
			],
			\array_column( $subItems, 'href' )
		);
		$this->assertSame( '/admin/reports/list', $reports[ 'href' ] ?? '' );
		$this->assertSame(
			\implode( ',', PluginNavs::reportsSettingsZoneComponentSlugs() ),
			$reports[ 'config' ][ 'data' ][ 'zone_component_slug' ] ?? ''
		);
	}

	public function testModeSelectorAssignsPrimaryAndMetaGroups() :void {
		$this->installControllerStubs();

		$menu = $this->invokeBuildModeSelector( $this->baseMenuFixture() );

		$this->assertSame(
			[
				'mode-actions',
				'mode-investigate',
				'mode-configure',
				'mode-reports',
				PluginNavs::NAV_LICENSE,
				'meta-connect',
			],
			\array_column( $menu, 'slug' )
		);

		$this->assertSame(
			[ 'primary', 'primary', 'primary', 'primary', 'meta', 'meta' ],
			\array_column( $menu, 'group' )
		);
	}

	public function testModeSelectorAddsConnectMetaItemWithExternalSubItems() :void {
		$this->installControllerStubs();

		$menu = $this->invokeBuildModeSelector( $this->baseMenuFixture() );
		$connect = \array_values( \array_filter(
			$menu,
			fn( array $item ) :bool => ( $item[ 'slug' ] ?? '' ) === 'meta-connect'
		) )[ 0 ] ?? [];

		$this->assertSame( 'Connect', $connect[ 'title' ] ?? '' );
		$this->assertSame( 'icon-box-arrow-up-right', $connect[ 'img' ] ?? '' );
		$this->assertSame( 'meta', $connect[ 'group' ] ?? '' );

		$subItems = $connect[ 'sub_items' ] ?? [];
		$this->assertCount( 4, $subItems );
		$this->assertSame( [ 'connect-home', 'connect-facebook', 'connect-helpdesk', 'connect-newsletter' ], \array_column( $subItems, 'slug' ) );
		$this->assertSame(
			[
				'https://clk.shldscrty.com/shieldsecurityhome',
				'https://clk.shldscrty.com/pluginshieldsecuritygroupfb',
				'https://help.example.com',
				'https://clk.shldscrty.com/emailsubscribe',
			],
			\array_column( $subItems, 'href' )
		);
		$this->assertSame( [ '_blank', '_blank', '_blank', '_blank' ], \array_column( $subItems, 'target' ) );
	}

	public function testModeSelectorOmitsConnectWhenWhitelabelEnabled() :void {
		$this->installControllerStubs( true );

		$menu = $this->invokeBuildModeSelector( $this->baseMenuFixture() );
		$this->assertNotContains( 'meta-connect', \array_column( $menu, 'slug' ) );
	}

	public function testNormalizeGroupNormalizesKnownValuesAndFallsBackToPrimary() :void {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'normalizeGroup' );
		$method->setAccessible( true );

		$this->assertSame( 'meta', $method->invoke( $builder, ' META ' ) );
		$this->assertSame( 'primary', $method->invoke( $builder, 'unexpected' ) );
	}

	public function testMarkGroupBoundaryAddsMarkerOnlyOnGroupTransition() :void {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'markGroupBoundary' );
		$method->setAccessible( true );

		$first = $method->invoke( $builder, [ 'group' => 'primary', 'classes' => [] ], '' );
		$this->assertNotContains( 'menu-group-break-before', $first[ 'classes' ] );

		$transition = $method->invoke( $builder, [ 'group' => 'meta', 'classes' => [] ], 'primary' );
		$this->assertContains( 'menu-group-break-before', $transition[ 'classes' ] );

		$sameGroup = $method->invoke( $builder, [ 'group' => 'meta', 'classes' => [] ], 'meta' );
		$this->assertNotContains( 'menu-group-break-before', $sameGroup[ 'classes' ] );
	}

	private function invokeBuildModeNav( array $baseMenu, string $mode ) :array {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'buildModeNav' );
		$method->setAccessible( true );
		return $method->invoke( $builder, $baseMenu, $mode );
	}

	private function invokeBuildModeSelector( array $baseMenu ) :array {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'buildModeSelector' );
		$method->setAccessible( true );
		return $method->invoke( $builder, $baseMenu );
	}

	private function invokeActivity() :array {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'activity' );
		$method->setAccessible( true );
		return $method->invoke( $builder );
	}

	private function invokeReports() :array {
		$builder = $this->newBuilderInstance();
		$method = new \ReflectionMethod( $builder, 'reports' );
		$method->setAccessible( true );
		return $method->invoke( $builder );
	}

	private function newBuilderInstance() :NavMenuBuilder {
		/** @var NavMenuBuilder $builder */
		$builder = ( new \ReflectionClass( NavMenuBuilder::class ) )->newInstanceWithoutConstructor();
		return $builder;
	}

	private function installControllerStubs( bool $isWhitelabelled = false ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminHome() :string {
				return '/admin/home';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $slug ) :string {
				return 'icon-'.$slug;
			}
		};
		$controller->labels = (object)[
			'url_helpdesk' => 'https://help.example.com',
		];
		$controller->comps = (object)[
			'whitelabel' => new class( $isWhitelabelled ) {
				private bool $enabled;

				public function __construct( bool $enabled ) {
					$this->enabled = $enabled;
				}

				public function isEnabled() :bool {
					return $this->enabled;
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function baseMenuFixture() :array {
		return [
			[
				'slug'     => PluginNavs::NAV_DASHBOARD,
				'title'    => 'Dashboard',
				'subtitle' => 'Security At A Glance',
				'img'      => 'icon-speedometer',
			],
			[
				'slug'  => PluginNavs::NAV_ZONES,
				'title' => 'Security Zones',
			],
			[
				'slug'  => PluginNavs::NAV_RULES,
				'title' => 'Rules',
			],
			[
				'slug'  => PluginNavs::NAV_TOOLS,
				'title' => 'Tools',
			],
			[
				'slug'  => PluginNavs::NAV_SCANS,
				'title' => 'Scans',
			],
			[
				'slug' => PluginNavs::NAV_LICENSE,
				'title' => 'Go PRO!',
			],
		];
	}

	private function installRequestServiceStub() :void {
		ServicesState::installItems( [
			'service_request' => new Request(),
		] );
	}
}
