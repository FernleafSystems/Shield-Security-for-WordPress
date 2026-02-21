<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Plugin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore;

class PluginNavsOperatorModesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		$this->installControllerStubs();
	}

	protected function tearDown() :void {
		PluginStore::$plugin = null;
		parent::tearDown();
	}

	public function test_mode_for_nav_returns_expected_modes() :void {
		$this->assertSame( PluginNavs::MODE_ACTIONS, PluginNavs::modeForNav( PluginNavs::NAV_SCANS ) );
		$this->assertSame( PluginNavs::MODE_INVESTIGATE, PluginNavs::modeForNav( PluginNavs::NAV_ACTIVITY ) );
		$this->assertSame( PluginNavs::MODE_CONFIGURE, PluginNavs::modeForNav( PluginNavs::NAV_ZONES ) );
		$this->assertSame( PluginNavs::MODE_REPORTS, PluginNavs::modeForNav( PluginNavs::NAV_REPORTS ) );
	}

	public function test_default_entry_for_mode_returns_expected_nav_subnav_pairs() :void {
		$actions = PluginNavs::defaultEntryForMode( PluginNavs::MODE_ACTIONS );
		$this->assertSame( PluginNavs::NAV_SCANS, $actions[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_SCANS_OVERVIEW, $actions[ 'subnav' ] );

		$investigate = PluginNavs::defaultEntryForMode( PluginNavs::MODE_INVESTIGATE );
		$this->assertSame( PluginNavs::NAV_ACTIVITY, $investigate[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW, $investigate[ 'subnav' ] );

		$configure = PluginNavs::defaultEntryForMode( PluginNavs::MODE_CONFIGURE );
		$this->assertSame( PluginNavs::NAV_ZONES, $configure[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_ZONES_OVERVIEW, $configure[ 'subnav' ] );

		$reports = PluginNavs::defaultEntryForMode( PluginNavs::MODE_REPORTS );
		$this->assertSame( PluginNavs::NAV_REPORTS, $reports[ 'nav' ] );
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_OVERVIEW, $reports[ 'subnav' ] );
	}

	public function test_nav_hierarchy_contains_mode_landing_handlers() :void {
		$hierarchy = PluginNavs::GetNavHierarchy();

		$this->assertSame(
			PluginAdminPages\PageActionsQueueLanding::class,
			$hierarchy[ PluginNavs::NAV_SCANS ][ 'sub_navs' ][ PluginNavs::SUBNAV_SCANS_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateLanding::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateByUser::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_USER ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateLanding::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_IP ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageConfigureLanding::class,
			$hierarchy[ PluginNavs::NAV_ZONES ][ 'sub_navs' ][ PluginNavs::SUBNAV_ZONES_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageReportsLanding::class,
			$hierarchy[ PluginNavs::NAV_REPORTS ][ 'sub_navs' ][ PluginNavs::SUBNAV_REPORTS_OVERVIEW ][ 'handler' ]
		);
	}

	public function test_exact_route_map_contract() :void {
		$hierarchy = PluginNavs::GetNavHierarchy();

		$this->assertSame(
			PluginAdminPages\PageActionsQueueLanding::class,
			$hierarchy[ PluginNavs::NAV_SCANS ][ 'sub_navs' ][ PluginNavs::SUBNAV_SCANS_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageScansResults::class,
			$hierarchy[ PluginNavs::NAV_SCANS ][ 'sub_navs' ][ PluginNavs::SUBNAV_SCANS_RESULTS ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageScansRun::class,
			$hierarchy[ PluginNavs::NAV_SCANS ][ 'sub_navs' ][ PluginNavs::SUBNAV_SCANS_RUN ][ 'handler' ]
		);

		$this->assertSame(
			PluginAdminPages\PageInvestigateLanding::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateByUser::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_USER ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateLanding::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_IP ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageActivityLogTable::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_LOGS ][ 'handler' ]
		);

		$this->assertSame(
			PluginAdminPages\PageConfigureLanding::class,
			$hierarchy[ PluginNavs::NAV_ZONES ][ 'sub_navs' ][ PluginNavs::SUBNAV_ZONES_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageDynamicLoad::class,
			$hierarchy[ PluginNavs::NAV_ZONES ][ 'sub_navs' ][ 'secadmin' ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageDynamicLoad::class,
			$hierarchy[ PluginNavs::NAV_ZONES ][ 'sub_navs' ][ 'firewall' ][ 'handler' ]
		);

		$this->assertSame(
			PluginAdminPages\PageReportsLanding::class,
			$hierarchy[ PluginNavs::NAV_REPORTS ][ 'sub_navs' ][ PluginNavs::SUBNAV_REPORTS_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageReports::class,
			$hierarchy[ PluginNavs::NAV_REPORTS ][ 'sub_navs' ][ PluginNavs::SUBNAV_REPORTS_LIST ][ 'handler' ]
		);
	}

	public function test_default_subnav_for_zones_is_overview() :void {
		$this->assertSame(
			PluginNavs::SUBNAV_ZONES_OVERVIEW,
			PluginNavs::GetDefaultSubNavForNav( PluginNavs::NAV_ZONES )
		);
	}

	private function installControllerStubs() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'zones' => new class {
				public function enumZones() :array {
					return [
						'secadmin' => true,
						'firewall' => true,
					];
				}

				public function enumZoneComponents() :array {
					return [
						'secadmin' => true,
					];
				}
			},
		];

		PluginStore::$plugin = new class( $controller ) {
			private Controller $controller;

			public function __construct( Controller $controller ) {
				$this->controller = $controller;
			}

			public function getController() :Controller {
				return $this->controller;
			}
		};
	}
}
