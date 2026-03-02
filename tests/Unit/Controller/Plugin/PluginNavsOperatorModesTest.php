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

	public function test_mode_for_route_applies_dashboard_grades_override_and_nav_fallback() :void {
		$this->assertSame(
			PluginNavs::MODE_CONFIGURE,
			PluginNavs::modeForRoute( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES )
		);
		$this->assertSame(
			PluginNavs::MODE_ACTIONS,
			PluginNavs::modeForRoute( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS )
		);
		$this->assertSame(
			PluginNavs::MODE_INVESTIGATE,
			PluginNavs::modeForRoute( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS )
		);
	}

	public function test_reports_subnav_constants_match_expected_contract() :void {
		$this->assertSame( 'charts', PluginNavs::SUBNAV_REPORTS_CHARTS );
		$this->assertSame( 'settings', PluginNavs::SUBNAV_REPORTS_SETTINGS );
	}

	public function test_reports_workspace_definitions_match_expected_contract() :void {
		$definitions = PluginNavs::reportsWorkspaceDefinitions();
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_CHARTS,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_keys( $definitions )
		);

		$expectedRenderActions = [
			PluginNavs::SUBNAV_REPORTS_LIST     => Reports\PageReportsView::class,
			PluginNavs::SUBNAV_REPORTS_CHARTS   => Reports\ChartsSummary::class,
			PluginNavs::SUBNAV_REPORTS_SETTINGS => OptionsFormFor::class,
		];
		$expectedContentKeys = [
			PluginNavs::SUBNAV_REPORTS_LIST     => 'create_report',
			PluginNavs::SUBNAV_REPORTS_CHARTS   => 'summary_charts',
			PluginNavs::SUBNAV_REPORTS_SETTINGS => 'alerts_settings',
		];
		$expectedCreateActions = [
			PluginNavs::SUBNAV_REPORTS_LIST     => true,
			PluginNavs::SUBNAV_REPORTS_CHARTS   => false,
			PluginNavs::SUBNAV_REPORTS_SETTINGS => false,
		];

		foreach ( $definitions as $subNav => $definition ) {
			foreach ( [ 'menu_title', 'landing_cta', 'page_title', 'page_subtitle', 'content_key', 'render_action', 'show_create_action' ] as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $definition, 'Missing key '.$requiredKey.' for '.$subNav );
			}

			foreach ( [ 'menu_title', 'landing_cta', 'page_title', 'page_subtitle', 'content_key', 'render_action' ] as $stringKey ) {
				$this->assertIsString( $definition[ $stringKey ], 'Expected string '.$stringKey.' for '.$subNav );
				$this->assertNotSame( '', \trim( $definition[ $stringKey ] ), 'Expected non-empty '.$stringKey.' for '.$subNav );
			}

			$this->assertIsBool( $definition[ 'show_create_action' ], 'Expected boolean show_create_action for '.$subNav );
			$this->assertSame( $expectedRenderActions[ $subNav ], $definition[ 'render_action' ] );
			$this->assertSame( $expectedContentKeys[ $subNav ], $definition[ 'content_key' ] );
			$this->assertSame( $expectedCreateActions[ $subNav ], $definition[ 'show_create_action' ] );
		}
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

	public function test_investigate_landing_subject_definitions_match_expected_contract() :void {
		$definitions = PluginNavs::investigateLandingSubjectDefinitions();
		$this->assertSame(
			[ 'users', 'ips', 'plugins', 'themes', 'wordpress', 'requests', 'activity', 'woocommerce' ],
			\array_keys( $definitions )
		);

		$this->assertSame( 'lookup_text', $definitions[ 'users' ][ 'panel_type' ] ?? '' );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_USER, $definitions[ 'users' ][ 'subnav_hint' ] ?? '' );
		$this->assertSame( 'lookup_select', $definitions[ 'plugins' ][ 'panel_type' ] ?? '' );
		$this->assertSame( 'plugin_options', $definitions[ 'plugins' ][ 'options_key' ] ?? '' );
		$this->assertSame( 'direct_link', $definitions[ 'wordpress' ][ 'panel_type' ] ?? '' );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_CORE, $definitions[ 'wordpress' ][ 'subnav_hint' ] ?? '' );
		$this->assertFalse( (bool)( $definitions[ 'woocommerce' ][ 'is_enabled' ] ?? true ) );
		$this->assertTrue( (bool)( $definitions[ 'woocommerce' ][ 'is_pro' ] ?? false ) );

		$requiredKeys = [
			'label',
			'description',
			'icon_class',
			'panel_type',
			'subnav_hint',
			'href_key',
			'input_key',
			'options_key',
			'panel_title',
			'lookup_placeholder',
			'go_label',
			'is_enabled',
			'is_pro',
		];
		$validPanelTypes = [ 'lookup_text', 'lookup_select', 'direct_link', 'disabled' ];

		foreach ( $definitions as $subjectKey => $subject ) {
			foreach ( $requiredKeys as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $subject, 'Missing '.$requiredKey.' for '.$subjectKey );
			}

			$this->assertContains( $subject[ 'panel_type' ], $validPanelTypes, 'Invalid panel_type for '.$subjectKey );
			$this->assertIsBool( $subject[ 'is_enabled' ], 'Expected boolean is_enabled for '.$subjectKey );
			$this->assertIsBool( $subject[ 'is_pro' ], 'Expected boolean is_pro for '.$subjectKey );

			$this->assertIsString( $subject[ 'href_key' ], 'Expected string href_key for '.$subjectKey );
			$this->assertTrue(
				$subject[ 'subnav_hint' ] === null || \is_string( $subject[ 'subnav_hint' ] ),
				'Expected null|string subnav_hint for '.$subjectKey
			);

			$this->assertNotSame( '', \trim( (string)( $subject[ 'label' ] ?? '' ) ), 'Missing label for '.$subjectKey );
			$this->assertNotSame( '', \trim( (string)( $subject[ 'description' ] ?? '' ) ), 'Missing description for '.$subjectKey );
			$this->assertNotSame( '', \trim( (string)( $subject[ 'icon_class' ] ?? '' ) ), 'Missing icon_class for '.$subjectKey );

			if ( $subject[ 'is_enabled' ] && $subject[ 'panel_type' ] !== 'disabled' ) {
				$this->assertNotSame( '', $subject[ 'href_key' ], 'Enabled subject requires href_key for '.$subjectKey );
			}
		}
	}

	public function test_breadcrumb_subnav_definition_matches_expected_contract_for_scope_routes() :void {
		$this->assertSame(
			[ 'label' => 'Users' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER )
		);
		$this->assertSame(
			[ 'label' => 'Security Reports' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST )
		);
		$this->assertSame(
			[ 'label' => 'Charts & Trends' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_CHARTS )
		);
		$this->assertSame(
			[ 'label' => 'Alert Settings' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_SETTINGS )
		);
		$this->assertSame(
			[ 'label' => 'Security Grades' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_DASHBOARD, PluginNavs::SUBNAV_DASHBOARD_GRADES )
		);
		$this->assertSame(
			[ 'label' => 'Bots & IP Rules' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES )
		);
		$this->assertSame(
			[ 'label' => 'Scan Results' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS )
		);
		$this->assertSame(
			[ 'label' => 'Run Scan' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RUN )
		);
		$this->assertSame(
			[ 'label' => 'HTTP Request Log' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS )
		);
		$this->assertSame(
			[ 'label' => 'Live HTTP Log' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE )
		);
		$this->assertSame(
			[],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_OVERVIEW )
		);
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
