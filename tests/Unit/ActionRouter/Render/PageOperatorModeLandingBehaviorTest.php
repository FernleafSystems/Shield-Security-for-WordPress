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
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $queuePayload = [];

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
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 2,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 2,
							'severity'    => 'critical',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
				[
					'zone'         => 'maintenance',
					'severity'     => 'warning',
					'total'        => 1,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 1,
							'severity'    => 'warning',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
			],
		] );

		$this->assertSame( 'actions', $lane[ 'mode' ] ?? '' );
		$this->assertSame( 'status', $lane[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'critical', $lane[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'shield', $lane[ 'edge_status' ] ?? '' );
		$this->assertSame( ' has-critical', $lane[ 'extra_classes' ] ?? '' );
		$this->assertNotSame( '', $lane[ 'indicator_text' ] ?? '' );
		$this->assertStringContainsString( '2', $lane[ 'indicator_subtext' ] ?? '' );
		$this->assertStringContainsString( '1', $lane[ 'indicator_subtext' ] ?? '' );
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
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 3,
					'items'        => [
						[ 'severity' => 'critical', 'count' => 1 ],
						[ 'severity' => 'warning', 'count' => 2 ],
					],
				],
			],
		] );

		$this->assertStringContainsString( '1', $lane[ 'indicator_subtext' ] ?? '' );
		$this->assertStringContainsString( '2', $lane[ 'indicator_subtext' ] ?? '' );
		$this->assertStringContainsString( 'critical', $lane[ 'indicator_subtext' ] ?? '' );
		$this->assertStringContainsString( 'warning', $lane[ 'indicator_subtext' ] ?? '' );
	}

	public function test_actions_lane_all_clear_branch_keeps_indicator_contract_without_breakdown() :void {
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
		$this->assertNotSame( '', $lane[ 'indicator_text' ] ?? '' );
		$this->assertSame( '', $lane[ 'indicator_subtext' ] ?? 'not-empty' );
		$this->assertSame( 'bi bi-shield-check', $lane[ 'icon_class' ] ?? '' );
	}

	public function test_investigate_configure_and_reports_lanes_use_expected_indicator_contracts() :void {
		$page = new PageOperatorModeLanding();

		$investigate = $this->invokeNonPublicMethod( $page, 'buildInvestigateLane', [
			[
				'active_count'        => 3,
				'recent_active_count' => 2,
			],
		] );
		$this->assertSame( 'status', $investigate[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'info', $investigate[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'info', $investigate[ 'edge_status' ] ?? '' );
		$this->assertCount( 2, $investigate[ 'indicator_badges' ] ?? [] );
		$this->assertSame( $investigate[ 'indicator_text' ] ?? '', $investigate[ 'indicator_badges' ][ 0 ][ 'text' ] ?? null );
		$this->assertStringContainsString( '3', $investigate[ 'indicator_text' ] ?? '' );
		$this->assertStringContainsString( '2', $investigate[ 'indicator_badges' ][ 1 ][ 'text' ] ?? '' );
		$this->assertSame( '/admin/activity/overview', $investigate[ 'href' ] ?? '' );

		$configure = $this->invokeNonPublicMethod( $page, 'buildConfigureLane', [ 95, 'good' ] );
		$this->assertSame( 'posture', $configure[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'good', $configure[ 'edge_status' ] ?? '' );
		$this->assertSame( 95, $configure[ 'posture_percentage' ] ?? null );
		$this->assertSame( 'good', $configure[ 'posture_status' ] ?? '' );
		$this->assertStringContainsString( '95', $configure[ 'posture_text' ] ?? '' );
		$this->assertSame( '/admin/zones/overview', $configure[ 'href' ] ?? '' );

		$reportsWithData = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [
			[
				'count'            => 5,
				'latest_report_at' => 0,
				'latest_alert_at'  => 0,
			],
		] );
		$this->assertSame( 'info', $reportsWithData[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'warning', $reportsWithData[ 'edge_status' ] ?? '' );
		$this->assertCount( 1, $reportsWithData[ 'indicator_badges' ] ?? [] );
		$this->assertSame( $reportsWithData[ 'indicator_text' ] ?? '', $reportsWithData[ 'indicator_badges' ][ 0 ][ 'text' ] ?? null );
		$this->assertStringContainsString( '5', $reportsWithData[ 'indicator_text' ] ?? '' );
		$this->assertSame( '/admin/reports/overview', $reportsWithData[ 'href' ] ?? '' );

		$reportsFallback = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [
			[
				'count'            => 0,
				'latest_report_at' => 0,
				'latest_alert_at'  => 0,
			],
		] );
		$this->assertStringContainsString( '0', $reportsFallback[ 'indicator_text' ] ?? '' );
		$this->assertCount( 1, $reportsFallback[ 'indicator_badges' ] ?? [] );
	}

	public function test_investigate_session_summary_counts_active_and_recent_sessions() :void {
		$page = new PageOperatorModeLandingTestDouble(
			[],
			[
				[
					'login'  => 189200,
					'shield' => [
						'last_activity_at' => 196400,
					],
				],
				[
					'login'  => 27200,
					'shield' => [
						'last_activity_at' => 27200,
					],
				],
				[
					'login' => 198200,
				],
			],
			200000
		);

		$summary = $this->invokeNonPublicMethod( $page, 'getInvestigateSessionSummary' );

		$this->assertSame( 3, $summary[ 'active_count' ] ?? null );
		$this->assertSame( 2, $summary[ 'recent_active_count' ] ?? null );
	}

	public function test_queue_summary_is_derived_from_attention_query_contract() :void {
		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 3,
				'severity'     => 'warning',
				'is_all_clear' => false,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [ 'zone' => 'scans', 'total' => 0, 'severity' => 'good', 'items' => [] ],
				'maintenance' => [ 'zone' => 'maintenance', 'total' => 0, 'severity' => 'good', 'items' => [] ],
			],
		] ] );

		$this->assertTrue( $summary[ 'has_items' ] );
		$this->assertSame( 3, $summary[ 'total_items' ] );
		$this->assertSame( 'warning', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-shield-exclamation', $summary[ 'icon_class' ] );
		$this->assertSame( '', $summary[ 'subtext' ] );
	}

	public function test_queue_zone_groups_are_extracted_from_attention_query() :void {
		$page = new PageOperatorModeLanding();
		$zoneGroups = $this->invokeNonPublicMethod( $page, 'getQueueZoneGroups', [ [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 3,
				'severity'     => 'critical',
				'is_all_clear' => false,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [
					'zone'     => 'scans',
					'severity' => 'critical',
					'total'    => 2,
					'items'    => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 2,
							'severity'    => 'critical',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
				'maintenance' => [
					'zone'     => 'maintenance',
					'severity' => 'warning',
					'total'    => 1,
					'items'    => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 1,
							'severity'    => 'warning',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
			],
		] ] );

		$this->assertSame(
			[
				[
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 2,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 2,
							'severity'    => 'critical',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
				[
					'zone'         => 'maintenance',
					'severity'     => 'warning',
					'total'        => 1,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 1,
							'severity'    => 'warning',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
			],
			$zoneGroups
		);
	}

	public function test_queue_summary_marks_empty_attention_query_as_all_clear() :void {
		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 0,
				'severity'     => 'good',
				'is_all_clear' => true,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [ 'zone' => 'scans', 'total' => 0, 'severity' => 'good', 'items' => [] ],
				'maintenance' => [ 'zone' => 'maintenance', 'total' => 0, 'severity' => 'good', 'items' => [] ],
			],
		] ] );

		$this->assertFalse( $summary[ 'has_items' ] );
		$this->assertSame( 0, $summary[ 'total_items' ] );
		$this->assertSame( 'good', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-shield-check', $summary[ 'icon_class' ] );
		$this->assertSame( '', $summary[ 'subtext' ] );
	}

	public function test_live_monitor_vars_use_current_compact_contract() :void {
		$page = new PageOperatorModeLanding();
		$vars = $this->invokeNonPublicMethod( $page, 'buildLiveMonitorVars' );

		$this->assertArrayHasKey( 'is_collapsed', $vars );
		$this->assertIsBool( $vars[ 'is_collapsed' ] ?? null );
		$this->assertNotSame( '', $vars[ 'title' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'activity' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'traffic' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'loading' ] ?? '' );
		$this->assertArrayNotHasKey( 'minimize', $vars );
		$this->assertArrayNotHasKey( 'expand', $vars );
	}

	public function test_actions_queue_rows_follow_queue_scan_item_order_and_append_maintenance() :void {
		$rows = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildActionsQueueRows', [
			[
				[ 'key' => 'malware', 'label' => 'Malware', 'severity' => 'critical', 'count' => 4 ],
				[ 'key' => 'vulnerable_assets', 'label' => 'Vulnerabilities', 'severity' => 'critical', 'count' => 3 ],
				[ 'key' => 'wp_files', 'label' => 'WordPress Files', 'severity' => 'critical', 'count' => 2 ],
				[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'severity' => 'warning', 'count' => 5 ],
				[ 'key' => 'theme_files', 'label' => 'Theme Files', 'severity' => 'warning', 'count' => 1 ],
				[ 'key' => 'abandoned', 'label' => 'Abandoned Assets', 'severity' => 'critical', 'count' => 6 ],
				[ 'key' => 'file_locker', 'label' => 'File Locker', 'severity' => 'warning', 'count' => 2 ],
			],
			[
				[
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 23,
					'items'        => [],
				],
				[
					'zone'         => 'maintenance',
					'severity'     => 'warning',
					'total'        => 7,
					'items'        => [],
				],
			],
		] );

		$this->assertSame(
			[
				'malware',
				'vulnerable_assets',
				'wp_files',
				'plugin_files',
				'theme_files',
				'abandoned',
				'file_locker',
				'maintenance',
			],
			\array_column( $rows, 'key' )
		);
		$this->assertSame( [ 4, 3, 2, 5, 1, 6, 2, 7 ], \array_column( $rows, 'count' ) );
		$this->assertSame(
			[ 'critical', 'critical', 'critical', 'warning', 'warning', 'critical', 'warning', 'warning' ],
			\array_column( $rows, 'severity' )
		);
		$this->assertSame(
			[
				'bi bi-bug',
				'bi bi-shield-exclamation',
				'bi bi-wordpress',
				'bi bi-plug',
				'bi bi-brush',
				'bi bi-archive',
				'bi bi-file-lock2',
				'bi bi-wrench',
			],
			\array_column( $rows, 'icon_class' )
		);
	}

	public function test_actions_queue_rows_only_include_queue_scan_items_and_maintenance() :void {
		$rows = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildActionsQueueRows', [
			[
				[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'severity' => 'warning', 'count' => 2 ],
				[ 'key' => 'file_locker', 'label' => 'File Locker', 'severity' => 'good', 'count' => 0 ],
			],
			[
				[
					'zone'         => 'scans',
					'severity'     => 'warning',
					'total'        => 2,
					'items'        => [],
				],
			],
		] );

		$this->assertSame(
			[ 'plugin_files', 'file_locker', 'maintenance' ],
			\array_column( $rows, 'key' )
		);
		$this->assertSame(
			[
				'plugin_files' => 'warning',
				'file_locker'  => 'good',
				'maintenance'  => 'good',
			],
			\array_combine( \array_column( $rows, 'key' ), \array_column( $rows, 'severity' ) )
		);
	}

	public function test_render_data_uses_scan_state_rows_without_replacing_attention_summary_state() :void {
		$page = new PageOperatorModeLandingTestDouble(
			[
				'generated_at' => 1700000000,
				'summary'      => [
					'total'        => 1,
					'severity'     => 'warning',
					'is_all_clear' => false,
				],
				'items'        => [],
				'groups'       => [
					'scans'       => [
						'zone'     => 'scans',
						'severity' => 'good',
						'total'    => 0,
						'items'    => [],
					],
					'maintenance' => [
						'zone'     => 'maintenance',
						'severity' => 'warning',
						'total'    => 1,
						'items'    => [],
					],
				],
			],
			[],
			200000,
			[
				'rows' => [
					[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'severity' => 'critical', 'count' => 99 ],
					[ 'key' => 'file_locker', 'label' => 'File Locker', 'severity' => 'good', 'count' => 0 ],
				],
				'tabs'               => [],
				'rail_accent_status' => 'critical',
			]
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$actionsQueueRows = $renderData[ 'vars' ][ 'actions_queue_rows' ] ?? [];

		$this->assertSame(
			[ 'plugin_files', 'file_locker', 'maintenance' ],
			\array_column( $actionsQueueRows, 'key' )
		);
		$this->assertSame(
			[
				'plugin_files' => 99,
				'file_locker'  => 0,
				'maintenance'  => 1,
			],
			\array_combine( \array_column( $actionsQueueRows, 'key' ), \array_column( $actionsQueueRows, 'count' ) )
		);
		$this->assertSame(
			[
				'plugin_files' => 'critical',
				'file_locker'  => 'good',
				'maintenance'  => 'warning',
			],
			\array_combine( \array_column( $actionsQueueRows, 'key' ), \array_column( $actionsQueueRows, 'severity' ) )
		);
		$this->assertSame( 'warning', $renderData[ 'vars' ][ 'actions_lane' ][ 'indicator_severity' ] ?? '' );
	}

	public function test_render_data_exposes_actions_queue_title_and_secondary_lanes() :void {
		$this->installControllerStubWithQueuePayload( [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 4,
				'severity'     => 'warning',
				'is_all_clear' => false,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [
					'zone'     => 'scans',
					'severity' => 'warning',
					'total'    => 3,
					'items'    => [
						[ 'key' => 'malware', 'label' => 'Malware', 'severity' => 'critical', 'count' => 2 ],
						[ 'key' => 'vulnerable_assets', 'label' => 'Vulnerabilities', 'severity' => 'good', 'count' => 0 ],
						[ 'key' => 'file_locker', 'label' => 'File Locker', 'severity' => 'warning', 'count' => 1 ],
					],
				],
				'maintenance' => [
					'zone'     => 'maintenance',
					'severity' => 'warning',
					'total'    => 1,
					'items'    => [],
				],
			],
		] );

		$renderData = $this->invokeNonPublicMethod( $this->newPage(), 'getRenderData' );

		$this->assertSame(
			PluginNavs::modeLabel( PluginNavs::MODE_ACTIONS ),
			$renderData[ 'strings' ][ 'title' ] ?? ''
		);
		$this->assertSame( 'actions', $renderData[ 'vars' ][ 'actions_lane' ][ 'mode' ] ?? '' );
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $renderData[ 'vars' ][ 'secondary_lanes' ] ?? [], 'mode' )
		);
		$this->assertSame(
			[ 'malware', 'vulnerable_assets', 'file_locker', 'maintenance' ],
			\array_column( $renderData[ 'vars' ][ 'actions_queue_rows' ] ?? [], 'key' )
		);
	}

	private function newPage() :PageOperatorModeLanding {
		return new PageOperatorModeLandingTestDouble( $this->queuePayload );
	}

	private function installControllerStubWithQueuePayload( array $queuePayload, array $reportsState = [] ) :void {
		$this->queuePayload = $queuePayload;
		$reportsState = \array_replace_recursive( [
			'reports_count'    => 0,
			'latest_report_at' => 0,
			'latest_alert_at'  => 0,
		], $reportsState );

		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps'  => (object)[],
				'db_con' => (object)[
					'reports' => new OperatorModeReportsStore(
						$reportsState[ 'reports_count' ],
						$reportsState[ 'latest_report_at' ],
						$reportsState[ 'latest_alert_at' ]
					),
				],
			]
		);
	}
}

class PageOperatorModeLandingTestDouble extends PageOperatorModeLanding {

	private array $attentionQuery;

	private array $sessions;

	private int $currentTimestamp;

	private ?array $scanState;

	public function __construct(
		array $attentionQuery,
		array $sessions = [],
		int $currentTimestamp = 200000,
		?array $scanState = null
	) {
		$this->attentionQuery = $attentionQuery;
		$this->sessions = $sessions;
		$this->currentTimestamp = $currentTimestamp;
		$this->scanState = $scanState;
	}

	protected function getZonePosture() :array {
		return [
			'components' => [],
			'signals'    => [],
			'totals'     => [
				'score'        => 72,
				'max_weight'   => 100,
				'percentage'   => 72,
				'letter_score' => 'B',
			],
			'percentage' => 72,
			'severity'   => 'warning',
			'status'     => 'warning',
		];
	}

	protected function getSessionsLoader() :LoadSessions {
		return new OperatorModeSessionsLoader( $this->sessions );
	}

	protected function getCurrentTimestamp() :int {
		return $this->currentTimestamp;
	}

	protected function buildAttentionQuery() :array {
		return $this->attentionQuery;
	}

	protected function buildScanState() :array {
		if ( $this->scanState !== null ) {
			return $this->scanState;
		}

		return [
			'rows'               => \is_array( $this->attentionQuery[ 'groups' ][ 'scans' ][ 'items' ] ?? null )
				? $this->attentionQuery[ 'groups' ][ 'scans' ][ 'items' ]
				: [],
			'tabs'               => [],
			'rail_accent_status' => 'good',
		];
	}
}

class OperatorModeSessionsLoader extends LoadSessions {

	private array $sessions;

	public function __construct( array $sessions ) {
		$this->sessions = $sessions;
	}

	public function flat() :array {
		return $this->sessions;
	}
}

class OperatorModeReportsStore {

	private int $reportsCount;

	private int $latestReportAt;

	private int $latestAlertAt;

	public function __construct(
		int $reportsCount,
		int $latestReportAt,
		int $latestAlertAt
	) {
		$this->reportsCount = $reportsCount;
		$this->latestReportAt = $latestReportAt;
		$this->latestAlertAt = $latestAlertAt;
	}

	public function getQuerySelector() :OperatorModeReportsQuerySelector {
		return new OperatorModeReportsQuerySelector(
			$this->reportsCount,
			$this->latestReportAt,
			$this->latestAlertAt
		);
	}
}

class OperatorModeReportsQuerySelector {

	private ?string $type = null;

	private int $reportsCount;

	private int $latestReportAt;

	private int $latestAlertAt;

	public function __construct(
		int $reportsCount,
		int $latestReportAt,
		int $latestAlertAt
	) {
		$this->reportsCount = $reportsCount;
		$this->latestReportAt = $latestReportAt;
		$this->latestAlertAt = $latestAlertAt;
	}

	public function addWhere( string $column, string $value, string $operator ) :self {
		return $this;
	}

	public function filterByType( string $type ) :self {
		$this->type = $type;
		return $this;
	}

	public function setOrderBy( string $column, string $direction = 'DESC', bool $replace = false ) :self {
		return $this;
	}

	public function count() :int {
		return $this->reportsCount;
	}

	public function first() :?object {
		$createdAt = $this->type === 'alt' ? $this->latestAlertAt : $this->latestReportAt;
		return $createdAt > 0 ? (object)[ 'created_at' => $createdAt ] : null;
	}
}
