<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageOperatorModeLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		$this->installControllerStubWithQueuePayload( [] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_actions_lane_uses_summary_contract_for_status_copy_and_breakdown() :void {
		$page = new PageOperatorModeLanding();
		$lane = $this->invokeNonPublicMethod( $page, 'buildActionsLane', [
			[
				'has_items'   => true,
				'total_items' => 1,
				'severity'    => 'critical',
				'icon_class'  => 'from-summary',
				'subtext'     => 'Last scan: 2 minutes ago',
			],
			[
				[
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-scans',
					'severity'     => 'critical',
					'total_issues' => 2,
					'items'        => [
						[ 'severity' => 'critical', 'count' => 2 ],
					],
				],
				[
					'slug'         => 'maintenance',
					'label'        => 'Maintenance',
					'icon_class'   => 'bi bi-maintenance',
					'severity'     => 'warning',
					'total_issues' => 1,
					'items'        => [
						[ 'severity' => 'warning', 'count' => 1 ],
					],
				],
			],
		] );

		$this->assertSame( 'actions', $lane[ 'mode' ] ?? '' );
		$this->assertSame( 'status', $lane[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'critical', $lane[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'shield', $lane[ 'edge_status' ] ?? '' );
		$this->assertSame( ' has-critical', $lane[ 'extra_classes' ] ?? '' );
		$this->assertSame( '1 issue needs attention', $lane[ 'indicator_text' ] ?? '' );
		$this->assertSame( '2 critical - 1 warning', $lane[ 'indicator_subtext' ] ?? '' );
		$this->assertSame( 'bi bi-shield-x', $lane[ 'icon_class' ] ?? '' );
		$this->assertSame( '/admin/scans/overview', $lane[ 'href' ] ?? '' );
	}

	public function test_actions_lane_breakdown_uses_item_level_counts_within_same_zone() :void {
		$page = new PageOperatorModeLanding();
		$lane = $this->invokeNonPublicMethod( $page, 'buildActionsLane', [
			[
				'has_items'   => true,
				'total_items' => 3,
				'severity'    => 'critical',
				'icon_class'  => 'from-summary',
				'subtext'     => '',
			],
			[
				[
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-scans',
					'severity'     => 'critical',
					'total_issues' => 3,
					'items'        => [
						[ 'severity' => 'critical', 'count' => 1 ],
						[ 'severity' => 'warning', 'count' => 2 ],
					],
				],
			],
		] );

		$this->assertSame( '1 critical - 2 warnings', $lane[ 'indicator_subtext' ] ?? '' );
	}

	public function test_actions_lane_all_clear_branch_uses_all_clear_copy() :void {
		$page = new PageOperatorModeLanding();
		$lane = $this->invokeNonPublicMethod( $page, 'buildActionsLane', [
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => 'bi bi-shield-check',
				'subtext'     => '',
			],
			[],
		] );

		$this->assertSame( 'good', $lane[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'shield', $lane[ 'edge_status' ] ?? '' );
		$this->assertSame( '', $lane[ 'extra_classes' ] ?? 'not-empty' );
		$this->assertSame( 'All Clear', $lane[ 'indicator_text' ] ?? '' );
		$this->assertSame( '', $lane[ 'indicator_subtext' ] ?? 'not-empty' );
		$this->assertSame( 'bi bi-shield-check', $lane[ 'icon_class' ] ?? '' );
	}

	public function test_investigate_configure_and_reports_lanes_use_expected_indicator_contracts() :void {
		$page = new PageOperatorModeLanding();

		$investigate = $this->invokeNonPublicMethod( $page, 'buildInvestigateLane', [ 3 ] );
		$this->assertSame( 'status', $investigate[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'neutral', $investigate[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'info', $investigate[ 'edge_status' ] ?? '' );
		$this->assertSame( '3 active sessions', $investigate[ 'indicator_text' ] ?? '' );
		$this->assertSame( '/admin/activity/overview', $investigate[ 'href' ] ?? '' );

		$configure = $this->invokeNonPublicMethod( $page, 'buildConfigureLane', [ 95, 'good' ] );
		$this->assertSame( 'posture', $configure[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'good', $configure[ 'edge_status' ] ?? '' );
		$this->assertSame( 95, $configure[ 'posture_percentage' ] ?? null );
		$this->assertSame( 'good', $configure[ 'posture_status' ] ?? '' );
		$this->assertSame( '95% configured', $configure[ 'posture_text' ] ?? '' );
		$this->assertSame( '/admin/zones/overview', $configure[ 'href' ] ?? '' );

		$reportsWithData = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [ 5 ] );
		$this->assertSame( 'neutral', $reportsWithData[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'warning', $reportsWithData[ 'edge_status' ] ?? '' );
		$this->assertSame( '5 reports', $reportsWithData[ 'indicator_text' ] ?? '' );
		$this->assertSame( '/admin/reports/overview', $reportsWithData[ 'href' ] ?? '' );

		$reportsFallback = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [ 0 ] );
		$this->assertSame( 'Summaries & Alerts', $reportsFallback[ 'indicator_text' ] ?? '' );
	}

	public function test_queue_summary_is_loaded_from_render_data_contract_path() :void {
		$payload = [
			'vars'        => [
				'summary' => [
					'has_items'   => false,
					'total_items' => 0,
					'severity'    => 'good',
					'icon_class'  => 'wrong-path',
					'subtext'     => 'wrong-path',
				],
			],
			'render_data' => [
				'vars' => [
					'summary' => [
						'has_items'   => true,
						'total_items' => 3,
						'severity'    => 'warning',
						'icon_class'  => 'from-render-data',
						'subtext'     => 'from-render-data',
					],
				],
			],
		];

		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ $payload ] );

		$this->assertTrue( (bool)( $summary[ 'has_items' ] ?? false ) );
		$this->assertSame( 3, $summary[ 'total_items' ] ?? null );
		$this->assertSame( 'warning', $summary[ 'severity' ] ?? '' );
		$this->assertSame( 'from-render-data', $summary[ 'icon_class' ] ?? '' );
		$this->assertSame( 'from-render-data', $summary[ 'subtext' ] ?? '' );
	}

	public function test_queue_zone_groups_are_extracted_and_normalized() :void {
		$page = new PageOperatorModeLanding();
		$zoneGroups = $this->invokeNonPublicMethod( $page, 'getQueueZoneGroups', [ [
			'render_data' => [
				'vars' => [
					'zone_groups' => [
						[
							'slug'         => 'scans',
							'label'        => 'Scans',
							'icon_class'   => 'bi bi-scans',
							'severity'     => 'critical',
							'total_issues' => 2,
							'items'        => [
								[ 'severity' => 'critical', 'count' => 2 ],
							],
						],
						[
							'slug'         => 'maintenance',
							'label'        => 'Maintenance',
							'icon_class'   => 'bi bi-maintenance',
							'severity'     => 'warning',
							'total_issues' => 1,
							'items'        => [
								[ 'severity' => 'warning', 'count' => 1 ],
							],
						],
					],
				],
			],
		] ] );

		$this->assertSame(
			[
				[
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-scans',
					'severity'     => 'critical',
					'total_issues' => 2,
					'items'        => [
						[ 'severity' => 'critical', 'count' => 2 ],
					],
				],
				[
					'slug'         => 'maintenance',
					'label'        => 'Maintenance',
					'icon_class'   => 'bi bi-maintenance',
					'severity'     => 'warning',
					'total_issues' => 1,
					'items'        => [
						[ 'severity' => 'warning', 'count' => 1 ],
					],
				],
			],
			$zoneGroups
		);
	}

	public function test_queue_summary_falls_back_to_safe_defaults_for_missing_fields() :void {
		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ [] ] );

		$this->assertFalse( (bool)( $summary[ 'has_items' ] ?? true ) );
		$this->assertSame( 0, $summary[ 'total_items' ] ?? null );
		$this->assertSame( 'good', $summary[ 'severity' ] ?? '' );
		$this->assertSame( 'bi bi-shield-check', $summary[ 'icon_class' ] ?? '' );
		$this->assertSame( '', $summary[ 'subtext' ] ?? 'not-empty' );
	}

	public function test_live_monitor_vars_use_current_compact_contract() :void {
		$page = new PageOperatorModeLanding();
		$vars = $this->invokeNonPublicMethod( $page, 'buildLiveMonitorVars' );

		$this->assertArrayHasKey( 'is_collapsed', $vars );
		$this->assertIsBool( $vars[ 'is_collapsed' ] ?? null );
		$this->assertSame( 'Live Monitor', $vars[ 'title' ] ?? '' );
		$this->assertSame( 'WP Activity', $vars[ 'activity' ] ?? '' );
		$this->assertSame( 'Live Traffic', $vars[ 'traffic' ] ?? '' );
		$this->assertSame( 'Waiting for live updates...', $vars[ 'loading' ] ?? '' );
		$this->assertArrayNotHasKey( 'minimize', $vars );
		$this->assertArrayNotHasKey( 'expand', $vars );
	}

	private function installControllerStubWithQueuePayload( array $queuePayload ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class( $queuePayload ) {
			private array $queuePayload;

			public function __construct( array $queuePayload ) {
				$this->queuePayload = $queuePayload;
			}

			public function action( string $class ) :object {
				return new class( $this->queuePayload ) {
					private array $queuePayload;

					public function __construct( array $queuePayload ) {
						$this->queuePayload = $queuePayload;
					}

					public function payload() :array {
						return $this->queuePayload;
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
