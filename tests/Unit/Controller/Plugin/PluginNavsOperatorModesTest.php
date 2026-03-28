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

		UnitTestControllerFactory::install(
			extras: (object)[
				'comps' => (object)[
					'zones' => new UnitTestZonesComponent(),
				],
			]
		);
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

	public function test_reports_workspace_filters_are_filtered_views_of_the_workspace_definition() :void {
		$workspace = PluginNavs::reportsWorkspaceDefinitions();
		$sidebar = PluginNavs::reportsSidebarWorkspaceDefinitions();
		$landing = PluginNavs::reportsLandingWorkspaceDefinitions();
		$routeHandlers = PluginNavs::reportsRouteHandlers();

		$this->assertSame(
			\array_keys( \array_filter( $workspace, static fn( array $definition ) :bool => $definition[ 'show_in_sidebar' ] ) ),
			\array_keys( $sidebar )
		);
		$this->assertSame(
			\array_keys( \array_filter( $workspace, static fn( array $definition ) :bool => $definition[ 'show_on_landing' ] ) ),
			\array_keys( $landing )
		);
		$this->assertSame( \key( $workspace ), PluginNavs::reportsDefaultWorkspaceSubNav() );
		$this->assertSame( PluginAdminPages\PageReportsLanding::class, $routeHandlers[ PluginNavs::SUBNAV_REPORTS_OVERVIEW ] );

		foreach ( $workspace as $subNav => $definition ) {
			$this->assertSame( PluginAdminPages\PageReports::class, $routeHandlers[ $subNav ] );
			$this->assertContains( $definition[ 'render_action' ], [
				OptionsFormFor::class,
				Reports\PageReportsView::class,
				Reports\ChartsSummary::class,
			] );
		}
	}

	public function test_investigate_subject_definitions_drive_activity_routes_and_legacy_helpers() :void {
		$hierarchy = PluginNavs::GetNavHierarchy()[ PluginNavs::NAV_ACTIVITY ][ 'sub_navs' ];

		foreach ( PluginNavs::investigateLandingSubjectDefinitions() as $subjectKey => $definition ) {
			$subNav = $definition[ 'subnav_hint' ];
			$this->assertIsString( $definition[ 'lookup_key' ] );
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
			$this->assertTrue( PluginNavs::isInvestigateLegacyContextSubNav( $subNav ) );
			$this->assertSame( $subNav, $definition[ 'render_subnav' ] );
		}

		$this->assertFalse( PluginNavs::isInvestigateLegacyContextSubNav( PluginNavs::SUBNAV_LOGS ) );
		$this->assertFalse( PluginNavs::isInvestigateLegacyContextSubNav( PluginNavs::SUBNAV_ACTIVITY_SESSIONS ) );
	}

	public function test_activity_and_reports_hierarchy_keep_route_specific_handlers() :void {
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
		$this->assertSame(
			PluginAdminPages\PageReportsLanding::class,
			$hierarchy[ PluginNavs::NAV_REPORTS ][ 'sub_navs' ][ PluginNavs::SUBNAV_REPORTS_OVERVIEW ][ 'handler' ]
		);
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
		}
	}

	public function test_actions_landing_zone_definitions_are_keyed_by_slug() :void {
		foreach ( PluginNavs::actionsLandingZoneDefinitions() as $slug => $definition ) {
			$this->assertSame( $slug, $definition[ 'slug' ] );
			$this->assertNotSame( '', \trim( $definition[ 'label' ] ) );
			$this->assertNotSame( '', \trim( $definition[ 'icon' ] ) );
		}
	}
}
