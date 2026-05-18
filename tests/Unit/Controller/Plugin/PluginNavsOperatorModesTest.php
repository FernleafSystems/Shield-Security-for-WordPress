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
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestZonesComponent
};

class PluginNavsOperatorModesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $key ) :string => \strtolower( \trim( $key ) )
		);

		$this->installZonesFixture();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_default_entries_point_to_real_mode_landing_routes() :void {
		foreach ( PluginNavs::allOperatorModes() as $mode ) {
			$entry = PluginNavs::defaultEntryForMode( $mode );

			$this->assertTrue( PluginNavs::NavExists( $entry[ 'nav' ], $entry[ 'subnav' ] ) );
			$this->assertSame( $mode, PluginNavs::modeForRoute( $entry[ 'nav' ], $entry[ 'subnav' ] ) );
			$this->assertTrue( PluginNavs::isModeLandingRoute( $entry[ 'nav' ], $entry[ 'subnav' ] ) );
		}
	}

	public function test_reports_workspace_definitions_only_expose_live_routes() :void {
		$workspace = PluginNavs::reportsWorkspaceDefinitions();
		$routeHandlers = PluginNavs::reportsRouteHandlers();

		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
				PluginNavs::SUBNAV_REPORTS_CHARTS,
			],
			\array_keys( $workspace )
		);
		$this->assertSame( \key( $workspace ), PluginNavs::reportsDefaultWorkspaceSubNav() );
		$this->assertSame( PluginAdminPages\PageReportsLanding::class, $routeHandlers[ PluginNavs::SUBNAV_REPORTS_OVERVIEW ] );

		foreach ( $workspace as $subNav => $definition ) {
			$this->assertSame( PluginAdminPages\PageReports::class, $routeHandlers[ $subNav ] );
			$this->assertContains( $definition[ 'render_action' ], [
				OptionsFormFor::class,
				Reports\PageReportsView::class,
				Reports\ChartsTrends::class,
			] );
		}
	}

	public function test_investigate_subject_definitions_drive_activity_routes() :void {
		$hierarchy = PluginNavs::GetNavHierarchy()[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ];

		foreach ( PluginNavs::investigateLandingSubjectDefinitions() as $subjectKey => $definition ) {
			$subNav = $definition[ 'subnav_hint' ];
			$this->assertIsString( $definition[ 'lookup_key' ] );
			$this->assertArrayNotHasKey( 'panel_title', $definition );
			$this->assertArrayNotHasKey( 'panel_status', $definition );
			$this->assertNotSame( '', \trim( $definition[ 'context_summary' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'context_focus' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'context_next_step' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'context_badge' ] ) );
			if ( !\is_string( $subNav ) || $subNav === '' ) {
				$this->assertSame( '', PluginNavs::investigateSubjectKeyForSubNav( PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ) );
				$this->assertContains( $definition[ 'render_nav' ], [
					PluginNavs::NAV_TRAFFIC,
					'',
				] );
				continue;
			}

			$this->assertSame( PluginNavs::NAV_ACTIVITY, $definition[ 'render_nav' ] );
			$this->assertArrayHasKey( $subNav, $hierarchy );
			$this->assertSame( $definition[ 'label' ], $hierarchy[ $subNav ][ 'label' ] ?? '' );
			$this->assertSame( $subjectKey, PluginNavs::investigateSubjectKeyForSubNav( $subNav ) );
			$this->assertSame( PluginAdminPages\PageInvestigateLanding::class, $hierarchy[ $subNav ][ 'handler' ] ?? '' );
			$this->assertSame( $subNav, $definition[ 'render_subnav' ] );
		}
	}

	public function test_activity_and_reports_hierarchy_keep_canonical_handlers() :void {
		$hierarchy = PluginNavs::GetNavHierarchy();

		$this->assertSame(
			PluginAdminPages\PageInvestigateLanding::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageUserSessions::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_ACTIVITY_SESSIONS ][ 'handler' ]
		);
		$this->assertSame(
			PluginAdminPages\PageActivityLogTable::class,
			$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ PluginNavs::SUBNAV_LOGS ][ 'handler' ]
		);
		foreach ( [
			PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			PluginNavs::SUBNAV_ACTIVITY_BY_THEME,
			PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
		] as $subNav ) {
			$this->assertSame(
				PluginAdminPages\PageInvestigateLanding::class,
				$hierarchy[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ][ $subNav ][ 'handler' ]
			);
		}
		$this->assertSame(
			PluginAdminPages\PageReportsLanding::class,
			$hierarchy[ PluginNavs::NAV_REPORTS ][ 'sub_navs' ][ PluginNavs::SUBNAV_REPORTS_OVERVIEW ][ 'handler' ]
		);
		foreach ( \array_keys( PluginNavs::reportsWorkspaceDefinitions() ) as $subNav ) {
			$this->assertSame(
				PluginAdminPages\PageReports::class,
				$hierarchy[ PluginNavs::NAV_REPORTS ][ 'sub_navs' ][ $subNav ][ 'handler' ]
			);
		}
	}

	public function test_tools_hierarchy_keeps_canonical_tool_handlers() :void {
		$toolsSubNavs = PluginNavs::GetNavHierarchy()[ PluginNavs::NAV_TOOLS ][ 'sub_navs' ];

		$this->assertSame(
			[
				PluginNavs::SUBNAV_TOOLS_BLOCKDOWN,
				PluginNavs::SUBNAV_TOOLS_SESSIONS,
				PluginNavs::SUBNAV_TOOLS_DEBUG,
				PluginNavs::SUBNAV_TOOLS_IMPORT,
			],
			\array_keys( $toolsSubNavs )
		);
		$this->assertSame(
			PluginAdminPages\PageToolLockdown::class,
			$toolsSubNavs[ PluginNavs::SUBNAV_TOOLS_BLOCKDOWN ][ 'handler' ] ?? ''
		);
		$this->assertSame(
			PluginAdminPages\PageUserSessions::class,
			$toolsSubNavs[ PluginNavs::SUBNAV_TOOLS_SESSIONS ][ 'handler' ] ?? ''
		);
		$this->assertSame(
			PluginAdminPages\PageDebug::class,
			$toolsSubNavs[ PluginNavs::SUBNAV_TOOLS_DEBUG ][ 'handler' ] ?? ''
		);
		$this->assertSame(
			PluginAdminPages\PageImportExport::class,
			$toolsSubNavs[ PluginNavs::SUBNAV_TOOLS_IMPORT ][ 'handler' ] ?? ''
		);
	}

	public function test_configure_hierarchy_keeps_overview_only_zones_and_live_zone_component_routes() :void {
		$zoneComponentSlugs = [
			'module_plugin' => true,
			'reporting'     => true,
		];
		$this->installZonesFixture(
			[
				'secadmin' => true,
				'firewall' => true,
			],
			$zoneComponentSlugs
		);

		$hierarchy = PluginNavs::GetNavHierarchy();
		$zonesSubNavs = $hierarchy[ PluginNavs::NAV_ZONES ][ 'sub_navs' ];
		$zoneComponentSubNavs = $hierarchy[ PluginNavs::NAV_ZONE_COMPONENTS ][ 'sub_navs' ];

		$this->assertSame( [ PluginNavs::SUBNAV_ZONES_OVERVIEW ], \array_keys( $zonesSubNavs ) );
		$this->assertSame(
			PluginAdminPages\PageConfigureLanding::class,
			$zonesSubNavs[ PluginNavs::SUBNAV_ZONES_OVERVIEW ][ 'handler' ]
		);
		$this->assertSame( \array_keys( $zoneComponentSlugs ), \array_keys( $zoneComponentSubNavs ) );
		foreach ( $zoneComponentSubNavs as $route ) {
			$this->assertSame( PluginAdminPages\PageZoneComponentConfig::class, $route[ 'handler' ] ?? '' );
		}
	}

	public function test_actions_assessment_definitions_keep_runtime_component_invariants() :void {
		$definitions = PluginNavs::actionsLandingAssessmentDefinitions();
		$maintenance = \array_filter( $definitions, static fn( array $definition ) :bool => $definition[ 'zone' ] === 'maintenance' );
		$maintenanceKeys = \array_column( $maintenance, 'key' );

		foreach ( $definitions as $definition ) {
			$this->assertTrue( \is_subclass_of( $definition[ 'component_class' ], Component\Base::class ) );
			$this->assertContains( $definition[ 'zone' ], [ 'scans', 'maintenance' ] );
			$this->assertNotSame( '', \trim( $definition[ 'availability_strategy' ] ) );
			$this->assertContains( $definition[ 'drill_bucket' ], [ 'critical', 'review' ] );
		}

		foreach ( $maintenance as $definition ) {
			$this->assertSame( $definition[ 'key' ], $definition[ 'component_class' ]::SLUG );
		}

		$this->assertContains( 'default_admin_user', $maintenanceKeys );
	}

	public function test_configure_tile_definitions_keep_exclusive_target_contract() :void {
		foreach ( PluginNavs::configureLandingTileDefinitions() as $definition ) {
			$targetCount = (int)!empty( $definition[ 'zone_slug' ] ?? '' )
						   + (int)!empty( $definition[ 'component_slug' ] ?? '' )
						   + (int)!empty( $definition[ 'component_slugs' ] ?? [] );

			$this->assertSame( 1, $targetCount );
			$this->assertNotSame( '', \trim( $definition[ 'key' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'label' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'icon' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'summary' ] ?? '' ) );
			if ( !empty( $definition[ 'force_neutral' ] ) ) {
				$this->assertArrayHasKey( 'stat_line', $definition );
				$this->assertNotSame( '', \trim( $definition[ 'stat_line' ] ) );
			}
		}
	}

	public function test_actions_landing_zone_definitions_are_keyed_by_slug() :void {
		foreach ( PluginNavs::actionsLandingZoneDefinitions() as $slug => $definition ) {
			$this->assertSame( $slug, $definition[ 'slug' ] );
			$this->assertNotSame( '', \trim( $definition[ 'label' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'icon' ] ) );
		}
	}

	public function test_actions_queue_scan_definitions_split_abandoned_and_add_queue_ignored_keys() :void {
		$landingDefinitions = PluginNavs::actionsLandingScanDefinitions();
		$queueDefinitions = PluginNavs::actionsQueueScanDefinitions();

		$this->assertSame( [ 'plugin_files' ], $landingDefinitions[ 'plugins' ][ 'summary_keys' ] );
		$this->assertSame( [ 'vulnerable_assets', 'abandoned' ], $landingDefinitions[ 'vulnerabilities' ][ 'summary_keys' ] );
		$this->assertContains( 'wp_files_ignored', $queueDefinitions[ 'wordpress' ][ 'summary_keys' ] );
		$this->assertContains( 'plugin_files_ignored', $queueDefinitions[ 'plugins' ][ 'summary_keys' ] );
		$this->assertContains( 'theme_files_ignored', $queueDefinitions[ 'themes' ][ 'summary_keys' ] );
		$this->assertContains( 'malware_ignored', $queueDefinitions[ 'malware' ][ 'summary_keys' ] );
		$this->assertSame( [ 'vulnerable_assets' ], $queueDefinitions[ 'vulnerabilities' ][ 'summary_keys' ] );
		$this->assertSame( [ 'abandoned' ], $queueDefinitions[ 'abandoned' ][ 'summary_keys' ] );
		$this->assertSame( 'vulnerabilities', PluginNavs::actionsQueueScanDefinitionForSummaryKey( 'vulnerable_assets' )[ 'slug' ] ?? '' );
		$this->assertSame( 'abandoned', PluginNavs::actionsQueueScanDefinitionForSummaryKey( 'abandoned' )[ 'slug' ] ?? '' );
		$this->assertSame( 'themes', PluginNavs::actionsQueueScanDefinitionForSummaryKey( 'theme_files_ignored' )[ 'slug' ] ?? '' );
		$this->assertSame( 'vulnerabilities', PluginNavs::actionsLandingScanDefinitionForSummaryKey( 'abandoned' )[ 'slug' ] ?? '' );
		$this->assertNull( PluginNavs::actionsLandingScanDefinitionForSummaryKey( 'theme_files_ignored' ) );
	}

	private function installZonesFixture(
		array $zones = [
			'secadmin' => true,
			'firewall' => true,
		],
		array $zoneComponents = [
			'secadmin' => true,
		]
	) :void {
		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'comps' => (object)[
					'zones' => new UnitTestZonesComponent( $zones, $zoneComponents ),
				],
			]
		);
	}
}
