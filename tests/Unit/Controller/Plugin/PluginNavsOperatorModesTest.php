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
	ActivityLogging,
	InstantAlerts,
	PluginGeneral,
	Reporting,
	RequestLogging
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

class PluginNavsOperatorModesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
		Functions\when( 'sanitize_key' )->alias(
			fn( string $key ) :string => \strtolower( \trim( $key ) )
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
		$this->assertSame( PluginNavs::MODE_CONFIGURE, PluginNavs::modeForNav( PluginNavs::NAV_TOOLS ) );
		$this->assertSame( PluginNavs::MODE_CONFIGURE, PluginNavs::modeForNav( PluginNavs::NAV_WIZARD ) );
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
		$this->assertSame( 'alerts', PluginNavs::SUBNAV_REPORTS_ALERTS );
		$this->assertSame( 'reporting', PluginNavs::SUBNAV_REPORTS_REPORTING );
		$this->assertSame( 'charts', PluginNavs::SUBNAV_REPORTS_CHARTS );
		$this->assertSame( 'settings', PluginNavs::SUBNAV_REPORTS_SETTINGS );
	}

	public function test_actions_landing_zone_definitions_match_expected_contract() :void {
		$definitions = PluginNavs::actionsLandingZoneDefinitions();
		$this->assertSame(
			[ 'scans', 'maintenance' ],
			\array_keys( $definitions )
		);

		foreach ( $definitions as $slug => $definition ) {
			foreach ( [ 'slug', 'label', 'icon' ] as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $definition, 'Missing key '.$requiredKey.' for '.$slug );
				$this->assertIsString( $definition[ $requiredKey ], 'Expected string '.$requiredKey.' for '.$slug );
				$this->assertNotSame( '', \trim( $definition[ $requiredKey ] ), 'Expected non-empty '.$requiredKey.' for '.$slug );
			}
			$this->assertSame( $slug, $definition[ 'slug' ] );
		}

		$this->assertSame( 'shield-exclamation', $definitions[ 'scans' ][ 'icon' ] );
		$this->assertSame( 'wrench', $definitions[ 'maintenance' ][ 'icon' ] );
	}

	public function test_reports_workspace_definitions_match_expected_contract() :void {
		$definitions = PluginNavs::reportsWorkspaceDefinitions();
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_ALERTS,
				PluginNavs::SUBNAV_REPORTS_REPORTING,
				PluginNavs::SUBNAV_REPORTS_CHARTS,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_keys( $definitions )
		);

		$expectedRenderActions = [
			PluginNavs::SUBNAV_REPORTS_LIST     => Reports\PageReportsView::class,
			PluginNavs::SUBNAV_REPORTS_ALERTS   => OptionsFormFor::class,
			PluginNavs::SUBNAV_REPORTS_REPORTING => OptionsFormFor::class,
			PluginNavs::SUBNAV_REPORTS_CHARTS   => Reports\ChartsSummary::class,
			PluginNavs::SUBNAV_REPORTS_SETTINGS => OptionsFormFor::class,
		];
		$expectedContentKeys = [
			PluginNavs::SUBNAV_REPORTS_LIST     => 'create_report',
			PluginNavs::SUBNAV_REPORTS_ALERTS   => 'alerts_settings',
			PluginNavs::SUBNAV_REPORTS_REPORTING => 'reporting_configuration',
			PluginNavs::SUBNAV_REPORTS_CHARTS   => 'summary_charts',
			PluginNavs::SUBNAV_REPORTS_SETTINGS => 'reporting_alerts_configuration',
		];
		$expectedCreateActions = [
			PluginNavs::SUBNAV_REPORTS_LIST     => true,
			PluginNavs::SUBNAV_REPORTS_ALERTS   => false,
			PluginNavs::SUBNAV_REPORTS_REPORTING => false,
			PluginNavs::SUBNAV_REPORTS_CHARTS   => false,
			PluginNavs::SUBNAV_REPORTS_SETTINGS => false,
		];
		$expectedSidebarVisibility = [
			PluginNavs::SUBNAV_REPORTS_LIST      => true,
			PluginNavs::SUBNAV_REPORTS_ALERTS    => false,
			PluginNavs::SUBNAV_REPORTS_REPORTING => false,
			PluginNavs::SUBNAV_REPORTS_CHARTS    => false,
			PluginNavs::SUBNAV_REPORTS_SETTINGS  => true,
		];
		$expectedLandingVisibility = [
			PluginNavs::SUBNAV_REPORTS_LIST      => true,
			PluginNavs::SUBNAV_REPORTS_ALERTS    => false,
			PluginNavs::SUBNAV_REPORTS_REPORTING => false,
			PluginNavs::SUBNAV_REPORTS_CHARTS    => false,
			PluginNavs::SUBNAV_REPORTS_SETTINGS  => true,
		];
		$expectedConfigZoneComponentSlugs = [
			PluginNavs::SUBNAV_REPORTS_LIST      => [],
			PluginNavs::SUBNAV_REPORTS_ALERTS    => [ InstantAlerts::Slug() ],
			PluginNavs::SUBNAV_REPORTS_REPORTING => [ Reporting::Slug() ],
			PluginNavs::SUBNAV_REPORTS_CHARTS    => [],
			PluginNavs::SUBNAV_REPORTS_SETTINGS  => [ InstantAlerts::Slug(), Reporting::Slug() ],
		];

		foreach ( $definitions as $subNav => $definition ) {
			foreach ( [
				'menu_title',
				'landing_cta',
				'page_title',
				'page_subtitle',
				'content_key',
				'render_action',
				'show_create_action',
				'show_in_sidebar',
				'show_on_landing',
				'config_zone_component_slugs'
			] as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $definition, 'Missing key '.$requiredKey.' for '.$subNav );
			}

			foreach ( [ 'menu_title', 'landing_cta', 'page_title', 'page_subtitle', 'content_key', 'render_action' ] as $stringKey ) {
				$this->assertIsString( $definition[ $stringKey ], 'Expected string '.$stringKey.' for '.$subNav );
				$this->assertNotSame( '', \trim( $definition[ $stringKey ] ), 'Expected non-empty '.$stringKey.' for '.$subNav );
			}

			$this->assertIsBool( $definition[ 'show_create_action' ], 'Expected boolean show_create_action for '.$subNav );
			$this->assertIsBool( $definition[ 'show_in_sidebar' ], 'Expected boolean show_in_sidebar for '.$subNav );
			$this->assertIsBool( $definition[ 'show_on_landing' ], 'Expected boolean show_on_landing for '.$subNav );
			$this->assertIsArray( $definition[ 'config_zone_component_slugs' ], 'Expected list config_zone_component_slugs for '.$subNav );
			$this->assertSame( $expectedRenderActions[ $subNav ], $definition[ 'render_action' ] );
			$this->assertSame( $expectedContentKeys[ $subNav ], $definition[ 'content_key' ] );
			$this->assertSame( $expectedCreateActions[ $subNav ], $definition[ 'show_create_action' ] );
			$this->assertSame( $expectedSidebarVisibility[ $subNav ], $definition[ 'show_in_sidebar' ] );
			$this->assertSame( $expectedLandingVisibility[ $subNav ], $definition[ 'show_on_landing' ] );
			$this->assertSame( $expectedConfigZoneComponentSlugs[ $subNav ], $definition[ 'config_zone_component_slugs' ] );
		}
	}

	public function test_reports_sidebar_workspace_definitions_include_only_visible_entries() :void {
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_keys( PluginNavs::reportsSidebarWorkspaceDefinitions() )
		);
	}

	public function test_reports_landing_workspace_definitions_include_only_visible_entries() :void {
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_keys( PluginNavs::reportsLandingWorkspaceDefinitions() )
		);
	}

	public function test_reports_route_handlers_match_expected_contract() :void {
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_OVERVIEW => PluginAdminPages\PageReportsLanding::class,
				PluginNavs::SUBNAV_REPORTS_LIST     => PluginAdminPages\PageReports::class,
				PluginNavs::SUBNAV_REPORTS_ALERTS   => PluginAdminPages\PageReports::class,
				PluginNavs::SUBNAV_REPORTS_REPORTING => PluginAdminPages\PageReports::class,
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
			],
			PluginNavs::reportsAlertSettingsZoneComponentSlugs()
		);
		$this->assertSame(
			[
				Reporting::Slug(),
			],
			PluginNavs::reportsReportingConfigurationZoneComponentSlugs()
		);
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
			PluginAdminPages\PageUserSessions::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_SESSIONS ][ 'handler' ]
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
		$this->assertSame( 'User', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_USER ) );
		$this->assertSame( 'IP Address', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_IP ) );
		$this->assertSame( 'Plugin', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ) );
		$this->assertSame( 'Theme', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_THEME ) );
		$this->assertSame( 'Core Files', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_BY_CORE ) );
		$this->assertSame( 'User Sessions', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_SESSIONS ) );
		$this->assertSame( 'Activity Log', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_LOGS ) );
		$this->assertSame( '', PluginNavs::investigateSubNavCrumbLabel( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ) );
	}

	public function test_investigate_subnav_definitions_match_expected_contract() :void {
		$this->assertSame(
			[
				PluginNavs::SUBNAV_ACTIVITY_BY_USER   => [ 'label' => 'User' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_IP     => [ 'label' => 'IP Address' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN => [ 'label' => 'Plugin' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_THEME  => [ 'label' => 'Theme' ],
				PluginNavs::SUBNAV_ACTIVITY_BY_CORE   => [ 'label' => 'Core Files' ],
				PluginNavs::SUBNAV_LOGS               => [ 'label' => 'Activity Log' ],
				PluginNavs::SUBNAV_ACTIVITY_SESSIONS  => [ 'label' => 'User Sessions' ],
			],
			PluginNavs::investigateSubNavDefinitions()
		);
		$this->assertSame( [ 'label' => 'User' ], PluginNavs::investigateSubNavDefinition( PluginNavs::SUBNAV_ACTIVITY_BY_USER ) );
		$this->assertSame( [], PluginNavs::investigateSubNavDefinition( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ) );
	}

	public function test_investigate_legacy_subnav_subject_mapping_matches_contract() :void {
		$this->assertSame( 'user', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_BY_USER ) );
		$this->assertSame( 'ip', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_BY_IP ) );
		$this->assertSame( 'plugin', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN ) );
		$this->assertSame( 'theme', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_BY_THEME ) );
		$this->assertSame( 'core', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_BY_CORE ) );
		$this->assertSame( '', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ) );
		$this->assertSame( '', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_SESSIONS ) );
		$this->assertSame( '', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_LOGS ) );
		$this->assertTrue( PluginNavs::isInvestigateLegacyContextSubNav( PluginNavs::SUBNAV_ACTIVITY_BY_IP ) );
		$this->assertFalse( PluginNavs::isInvestigateLegacyContextSubNav( PluginNavs::SUBNAV_LOGS ) );
	}

	public function test_investigate_landing_subject_definitions_match_expected_contract() :void {
		$definitions = PluginNavs::investigateLandingSubjectDefinitions();
		$this->assertSame(
			[ 'user', 'ip', 'plugin', 'theme', 'core', 'live_traffic', 'premium_integrations' ],
			\array_keys( $definitions )
		);

		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_USER, $definitions[ 'user' ][ 'subnav_hint' ] ?? '' );
		$this->assertSame( PluginNavs::SUBNAV_ACTIVITY_BY_CORE, $definitions[ 'core' ][ 'subnav_hint' ] ?? '' );
		$this->assertSame( PluginAdminPages\PageInvestigateByPlugin::class, $definitions[ 'plugin' ][ 'render_action' ] ?? '' );
		$this->assertSame( PluginAdminPages\PageTrafficLogLive::class, $definitions[ 'live_traffic' ][ 'render_action' ] ?? '' );
		$this->assertFalse( (bool)( $definitions[ 'premium_integrations' ][ 'is_enabled' ] ?? true ) );
		$this->assertTrue( (bool)( $definitions[ 'premium_integrations' ][ 'is_pro' ] ?? false ) );

		$requiredKeys = [
			'label',
			'icon_class',
			'status',
			'stat_text',
			'subnav_hint',
			'panel_title',
			'panel_status',
			'render_action',
			'render_nav',
			'render_subnav',
			'lookup_key',
			'is_enabled',
			'is_pro',
		];
		$validStatuses = [ 'good', 'warning', 'critical', 'info', 'neutral' ];

		foreach ( $definitions as $subjectKey => $subject ) {
			foreach ( $requiredKeys as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $subject, 'Missing '.$requiredKey.' for '.$subjectKey );
			}

			$this->assertContains( $subject[ 'status' ], $validStatuses, 'Invalid status for '.$subjectKey );
			$this->assertContains( $subject[ 'panel_status' ], $validStatuses, 'Invalid panel_status for '.$subjectKey );
			$this->assertIsBool( $subject[ 'is_enabled' ], 'Expected boolean is_enabled for '.$subjectKey );
			$this->assertIsBool( $subject[ 'is_pro' ], 'Expected boolean is_pro for '.$subjectKey );

			$this->assertTrue(
				$subject[ 'subnav_hint' ] === null || \is_string( $subject[ 'subnav_hint' ] ),
				'Expected null|string subnav_hint for '.$subjectKey
			);
			$this->assertTrue(
				$subject[ 'lookup_key' ] === null || \is_string( $subject[ 'lookup_key' ] ),
				'Expected null|string lookup_key for '.$subjectKey
			);
			$this->assertIsString( $subject[ 'render_nav' ], 'Expected string render_nav for '.$subjectKey );
			$this->assertIsString( $subject[ 'render_subnav' ], 'Expected string render_subnav for '.$subjectKey );
			$this->assertTrue(
				\is_string( $subject[ 'render_action' ] ),
				'Expected string render_action for '.$subjectKey
			);

			$this->assertNotSame( '', \trim( (string)( $subject[ 'label' ] ?? '' ) ), 'Missing label for '.$subjectKey );
			$this->assertNotSame( '', \trim( (string)( $subject[ 'stat_text' ] ?? '' ) ), 'Missing stat_text for '.$subjectKey );
			$this->assertNotSame( '', \trim( (string)( $subject[ 'icon_class' ] ?? '' ) ), 'Missing icon_class for '.$subjectKey );
			$this->assertNotSame( '', \trim( (string)( $subject[ 'panel_title' ] ?? '' ) ), 'Missing panel_title for '.$subjectKey );

			if ( $subject[ 'is_enabled' ] ) {
				$this->assertNotSame( '', $subject[ 'render_action' ], 'Enabled subject requires render_action for '.$subjectKey );
				$this->assertNotSame( '', $subject[ 'render_nav' ], 'Enabled subject requires render_nav for '.$subjectKey );
				$this->assertNotSame( '', $subject[ 'render_subnav' ], 'Enabled subject requires render_subnav for '.$subjectKey );
			}
		}
	}

	public function test_configure_landing_tile_definitions_match_expected_contract() :void {
		$definitions = PluginNavs::configureLandingTileDefinitions();
		$this->assertSame(
			[ 'secadmin', 'firewall', 'ips', 'scans', 'login', 'users', 'spam', 'headers', 'general' ],
			\array_column( $definitions, 'key' )
		);

		$requiredStringKeys = [ 'key', 'label', 'icon' ];
		foreach ( $definitions as $definition ) {
			foreach ( $requiredStringKeys as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $definition );
				$this->assertIsString( $definition[ $requiredKey ] );
				$this->assertNotSame( '', \trim( $definition[ $requiredKey ] ) );
			}

			$hasZoneSlug = !empty( $definition[ 'zone_slug' ] ?? '' );
			$hasComponentSlug = !empty( $definition[ 'component_slug' ] ?? '' );
			$hasComponentSlugs = !empty( $definition[ 'component_slugs' ] ?? [] );
			$this->assertSame( 1, (int)$hasZoneSlug + (int)$hasComponentSlug + (int)$hasComponentSlugs );
		}

		$this->assertSame( Zone\Secadmin::Slug(), $definitions[ 0 ][ 'zone_slug' ] ?? '' );
		$this->assertSame( Zone\Firewall::Slug(), $definitions[ 1 ][ 'zone_slug' ] ?? '' );
		$this->assertSame( Zone\Users::Slug(), $definitions[ 5 ][ 'zone_slug' ] ?? '' );
		$this->assertSame( Zone\Headers::Slug(), $definitions[ 7 ][ 'zone_slug' ] ?? '' );
		$this->assertSame(
			[ PluginGeneral::Slug(), ActivityLogging::Slug(), RequestLogging::Slug() ],
			$definitions[ 8 ][ 'component_slugs' ] ?? []
		);
		$this->assertFalse( $definitions[ 8 ][ 'include_in_posture' ] ?? true );
		$this->assertTrue( $definitions[ 8 ][ 'force_neutral' ] ?? false );
	}

	public function test_breadcrumb_subnav_definition_matches_expected_contract_for_scope_routes() :void {
		$this->assertSame(
			[ 'label' => 'User' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER )
		);
		$this->assertSame(
			[ 'label' => 'Security Reports' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_LIST )
		);
		$this->assertSame(
			[ 'label' => 'Alert Settings' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_ALERTS )
		);
		$this->assertSame(
			[ 'label' => 'Reporting Configuration' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_REPORTING )
		);
		$this->assertSame(
			[ 'label' => 'Charts & Trends' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_REPORTS, PluginNavs::SUBNAV_REPORTS_CHARTS )
		);
		$this->assertSame(
			[ 'label' => 'Reporting & Alerts Configuration' ],
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
			[ 'label' => 'Debug Info' ],
			PluginNavs::breadcrumbSubNavDefinition( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_DEBUG )
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
		$this->assertSame( PluginAdminPages\PageUserSessions::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_SESSIONS ][ 'handler' ] );
		$this->assertSame( PluginAdminPages\PageActivityLogTable::class, $hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_LOGS ][ 'handler' ] );
	}

	public function test_legacy_tools_sessions_route_remains_available() :void {
		$hierarchy = PluginNavs::GetNavHierarchy();

		$this->assertSame(
			PluginAdminPages\PageUserSessions::class,
			$hierarchy[ PluginNavs::NAV_TOOLS ][ 'sub_navs' ][ PluginNavs::SUBNAV_TOOLS_SESSIONS ][ 'handler' ]
		);
		$this->assertArrayNotHasKey( 'docs', $hierarchy[ PluginNavs::NAV_TOOLS ][ 'sub_navs' ] );
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
		$this->assertArrayHasKey( PluginNavs::SUBNAV_ACTIVITY_SESSIONS, $subNavs );
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
