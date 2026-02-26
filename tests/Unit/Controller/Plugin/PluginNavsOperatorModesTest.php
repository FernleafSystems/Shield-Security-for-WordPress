<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Controller\Plugin;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	InstantAlerts,
	Reporting
};

class PluginNavsOperatorModesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		$this->installControllerStubs();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_mode_for_nav_returns_expected_modes() :void {
		$this->assertSame( PluginNavs::MODE_ACTIONS, PluginNavs::modeForNav( PluginNavs::NAV_SCANS ) );
		$this->assertSame( PluginNavs::MODE_INVESTIGATE, PluginNavs::modeForNav( PluginNavs::NAV_ACTIVITY ) );
		$this->assertSame( PluginNavs::MODE_CONFIGURE, PluginNavs::modeForNav( PluginNavs::NAV_ZONES ) );
		$this->assertSame( PluginNavs::MODE_REPORTS, PluginNavs::modeForNav( PluginNavs::NAV_REPORTS ) );
	}

	public function test_reports_subnav_constants_match_expected_contract() :void {
		$this->assertSame( 'charts', PluginNavs::SUBNAV_REPORTS_CHARTS );
		$this->assertSame( 'settings', PluginNavs::SUBNAV_REPORTS_SETTINGS );
	}

	public function test_reports_workspace_definitions_match_expected_contract() :void {
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST     => [
					'menu_title'         => 'Security Reports',
					'landing_cta'        => 'Open Reports List',
					'page_title'         => 'View & Create',
					'page_subtitle'      => 'View and create new security reports.',
					'content_key'        => 'create_report',
					'render_action'      => Reports\PageReportsView::class,
					'show_create_action' => true,
				],
				PluginNavs::SUBNAV_REPORTS_CHARTS   => [
					'menu_title'         => 'Charts & Trends',
					'landing_cta'        => 'Open Charts & Trends',
					'page_title'         => 'Charts & Trends',
					'page_subtitle'      => 'Review recent security trend metrics.',
					'content_key'        => 'summary_charts',
					'render_action'      => Reports\ChartsSummary::class,
					'show_create_action' => false,
				],
				PluginNavs::SUBNAV_REPORTS_SETTINGS => [
					'menu_title'         => 'Alert Settings',
					'landing_cta'        => 'Open Alert Settings',
					'page_title'         => 'Alert Settings',
					'page_subtitle'      => 'Manage instant alerts and report delivery settings.',
					'content_key'        => 'alerts_settings',
					'render_action'      => OptionsFormFor::class,
					'show_create_action' => false,
				],
			],
			PluginNavs::reportsWorkspaceDefinitions()
		);
	}

	public function test_reports_route_handlers_match_expected_contract() :void {
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_OVERVIEW => PluginAdminPages\PageReportsLanding::class,
				PluginNavs::SUBNAV_REPORTS_LIST     => PluginAdminPages\PageReports::class,
				PluginNavs::SUBNAV_REPORTS_CHARTS   => PluginAdminPages\PageReports::class,
				PluginNavs::SUBNAV_REPORTS_SETTINGS => PluginAdminPages\PageReports::class,
			],
			PluginNavs::reportsRouteHandlers()
		);
	}

	public function test_reports_default_workspace_subnav_is_list() :void {
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_LIST, PluginNavs::reportsDefaultWorkspaceSubNav() );
	}

	public function test_reports_settings_zone_component_slugs_match_expected_contract() :void {
		$this->assertSame(
			[
				InstantAlerts::Slug(),
				Reporting::Slug(),
			],
			PluginNavs::reportsSettingsZoneComponentSlugs()
		);
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
			PluginAdminPages\PageInvestigateByIp::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_IP ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateByPlugin::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateByTheme::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_THEME ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageInvestigateByCore::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_CORE ][ 'handler' ]
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

	public function test_mode_landing_helpers_expose_expected_route_contract() :void {
		$this->assertSame(
			[
				PluginNavs::NAV_SCANS    => [
					PluginNavs::SUBNAV_SCANS_OVERVIEW,
				],
				PluginNavs::NAV_ACTIVITY => [
					PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
				],
				PluginNavs::NAV_ZONES    => [
					PluginNavs::SUBNAV_ZONES_OVERVIEW,
				],
				PluginNavs::NAV_REPORTS  => [
					PluginNavs::SUBNAV_REPORTS_OVERVIEW,
				],
			],
			PluginNavs::modeLandingSubNavsByNav()
		);

		$this->assertFalse(
			PluginNavs::isModeLandingRoute( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER )
		);
		$this->assertFalse(
			PluginNavs::isModeLandingRoute( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN )
		);
		$this->assertFalse(
			PluginNavs::isModeLandingRoute( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS )
		);
	}

	public function test_investigate_subnav_crumb_labels_match_expected_map() :void {
		$this->assertSame( 'Users', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_USER ) );
		$this->assertSame( 'IP Addresses', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_IP ) );
		$this->assertSame( 'Plugins', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ) );
		$this->assertSame( 'Themes', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_THEME ) );
		$this->assertSame( 'WordPress Core', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_CORE ) );
		$this->assertSame( 'Activity Log', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_LOGS ) );
		$this->assertSame( '', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ) );
	}

	public function test_investigate_subnav_definitions_match_expected_contract() :void {
		$this->assertSame(
			[
				PluginNavs::SUBNAV_ACTIVITY_BY_USER   => [ 'label' => 'Users' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_IP     => [ 'label' => 'IP Addresses' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN => [ 'label' => 'Plugins' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_THEME  => [ 'label' => 'Themes' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_CORE   => [ 'label' => 'WordPress Core' ],
				PluginNavs::SUBNAV_LOGS               => [ 'label' => 'Activity Log' ],
			],
			PluginNavs::investigateSubNavDefinitions()
		);
		$this->assertSame( [ 'label' => 'Users' ], PluginNavs::investigateSubNavDefinition( PluginNavs::SUBNAV_ACTIVITY_BY_USER ) );
		$this->assertSame( [], PluginNavs::investigateSubNavDefinition( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ) );
	}

	public function test_activity_route_handlers_match_expected_contract_for_touched_scope() :void {
		$hierarchy = PluginNavs::GetNavHierarchy();
		$this->assertSame( PluginAdminPages\PageInvestigateLanding::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ][ 'handler' ] );
		$this->assertSame( PluginAdminPages\PageInvestigateByUser::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_USER ][ 'handler' ] );
		$this->assertSame( PluginAdminPages\PageInvestigateByIp::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_IP ][ 'handler' ] );
		$this->assertSame( PluginAdminPages\PageInvestigateByPlugin::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ][ 'handler' ] );
		$this->assertSame( PluginAdminPages\PageInvestigateByTheme::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_THEME ][ 'handler' ] );
		$this->assertSame( PluginAdminPages\PageInvestigateByCore::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_BY_CORE ][ 'handler' ] );
		$this->assertSame( PluginAdminPages\PageActivityLogTable::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_LOGS ][ 'handler' ] );
	}

	public function test_default_subnav_for_zones_is_overview() :void {
		$this->assertSame(
			PluginNavs::SUBNAV_ZONES_OVERVIEW,
			PluginNavs::GetDefaultSubNavForNav( PluginNavs::NAV_ZONES )
		);
	}

	public function test_activity_nav_contains_transitional_subject_subnav_keys() :void {
		$subNavs = PluginNavs::GetNavHierarchy()[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ];

		$this->assertArrayHasKey( PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN, $subNavs );
		$this->assertArrayHasKey( PluginNavs::SUBNAV_ACTIVITY_BY_THEME, $subNavs );
		$this->assertArrayHasKey( PluginNavs::SUBNAV_ACTIVITY_BY_CORE, $subNavs );
	}

	public function test_default_subnav_for_activity_is_overview() :void {
		$this->assertSame(
			PluginNavs::SUBNAV_ACTIVITY_OVERVIEW,
			PluginNavs::GetDefaultSubNavForNav( PluginNavs::NAV_ACTIVITY )
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

		PluginControllerInstaller::install( $controller );
	}
}
