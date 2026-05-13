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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core\Db;
use FernleafSystems\Wordpress\Services\Services;

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $queuePayload = [];
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
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
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	private function attentionQuery( array $scanItems, array $maintenanceItems = [] ) :array {
		$items = \array_values( \array_merge( $scanItems, $maintenanceItems ) );

		return [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => (int)\array_sum( \array_column( $items, 'count' ) ),
				'severity'     => $this->highestSeverity( $items ),
				'is_all_clear' => empty( $items ),
			],
			'items'        => $items,
			'groups'       => [
				'scans'       => $this->attentionGroup( 'scans', $scanItems ),
				'maintenance' => $this->attentionGroup( 'maintenance', $maintenanceItems ),
			],
		];
	}

	private function attentionGroup( string $zone, array $items ) :array {
		return [
			'zone'     => $zone,
			'total'    => (int)\array_sum( \array_column( $items, 'count' ) ),
			'severity' => $this->highestSeverity( $items ),
			'items'    => $items,
		];
	}

	private function attentionItem( string $key, string $zone, int $count, string $severity, string $label = '' ) :array {
		return [
			'key'                => $key,
			'zone'               => $zone,
			'source'             => $zone === 'scans' ? 'scan' : 'maintenance',
			'label'              => $label === '' ? $key : $label,
			'description'        => $key,
			'count'              => $count,
			'ignored_count'      => 0,
			'severity'           => $severity,
			'href'               => '/'.$key,
			'action'             => 'Open',
			'target'             => '',
			'supports_sub_items' => false,
		];
	}

	private function highestSeverity( array $items ) :string {
		$severities = \array_column( $items, 'severity' );
		if ( \in_array( 'critical', $severities, true ) ) {
			return 'critical';
		}
		if ( \in_array( 'warning', $severities, true ) ) {
			return 'warning';
		}

		return 'good';
	}

	public function test_investigate_configure_and_reports_lanes_use_expected_indicator_contracts() :void {
		$page = new PageOperatorModeLanding();

		$investigate = $this->invokeNonPublicMethod( $page, 'buildInvestigateLane', [
			[
				'active_count'        => 3,
				'recent_active_count' => 2,
			],
		] );
		$this->assertSame( 'status', $investigate[ 'indicator_type' ] );
		$this->assertSame( 'info', $investigate[ 'indicator_severity' ] );
		$this->assertSame( 'info', $investigate[ 'edge_status' ] );
		$this->assertCount( 2, $investigate[ 'indicator_badges' ] );
		$this->assertSame( $investigate[ 'indicator_text' ], $investigate[ 'indicator_badges' ][ 0 ][ 'text' ] );
		$this->assertIsString( $investigate[ 'href' ] );

		$configure = $this->invokeNonPublicMethod( $page, 'buildConfigureLane', [ 95, 'good' ] );
		$this->assertSame( 'posture', $configure[ 'indicator_type' ] );
		$this->assertSame( 'good', $configure[ 'edge_status' ] );
		$this->assertSame( 95, $configure[ 'posture_percentage' ] );
		$this->assertSame( 'good', $configure[ 'posture_status' ] );
		$this->assertIsString( $configure[ 'href' ] );

		$reportsWithData = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [
			[
				'count'            => 5,
				'latest_report_at' => 0,
				'latest_alert_at'  => 0,
			],
		] );
		$this->assertSame( 'info', $reportsWithData[ 'indicator_severity' ] );
		$this->assertSame( 'warning', $reportsWithData[ 'edge_status' ] );
		$this->assertCount( 1, $reportsWithData[ 'indicator_badges' ] );
		$this->assertSame( $reportsWithData[ 'indicator_text' ], $reportsWithData[ 'indicator_badges' ][ 0 ][ 'text' ] );
		$this->assertIsString( $reportsWithData[ 'href' ] );

		$reportsFallback = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [
			[
				'count'            => 0,
				'latest_report_at' => 0,
				'latest_alert_at'  => 0,
			],
		] );
		$this->assertCount( 1, $reportsFallback[ 'indicator_badges' ] );
	}

	public function test_investigate_session_summary_counts_active_and_recent_sessions() :void {
		$page = new PageOperatorModeLandingTestDouble(
			$this->attentionQuery( [] ),
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

		$this->assertSame( 3, $summary[ 'active_count' ] );
		$this->assertSame( 2, $summary[ 'recent_active_count' ] );
	}

	public function test_reports_summary_uses_one_aggregate_query() :void {
		$this->installControllerStubWithQueuePayload( [], [
			'reports_count'    => 4,
			'latest_report_at' => 190000,
			'latest_alert_at'  => 180000,
		] );

		$summary = $this->invokeNonPublicMethod( new PageOperatorModeLandingTestDouble( $this->queuePayload ), 'getReportsSummary' );
		$db = Services::WpDb();

		$this->assertSame( [
			'count'            => 4,
			'latest_report_at' => 190000,
			'latest_alert_at'  => 180000,
		], $summary );
		$this->assertInstanceOf( OperatorModeReportsDb::class, $db );
		$this->assertCount( 1, $db->queries );
	}

	public function test_live_monitor_vars_use_current_compact_contract() :void {
		$page = new PageOperatorModeLanding();
		$vars = $this->invokeNonPublicMethod( $page, 'buildLiveMonitorVars' );

		$this->assertArrayHasKey( 'is_collapsed', $vars );
		$this->assertIsBool( $vars[ 'is_collapsed' ] );
		$this->assertNotSame( '', $vars[ 'title' ] );
		$this->assertNotSame( '', $vars[ 'activity' ] );
		$this->assertNotSame( '', $vars[ 'traffic' ] );
		$this->assertNotSame( '', $vars[ 'loading' ] );
		$this->assertArrayNotHasKey( 'minimize', $vars );
		$this->assertArrayNotHasKey( 'expand', $vars );
	}

	public function test_render_data_filters_dashboard_attention_summary_and_keeps_visible_maintenance_items() :void {
		$ignoredPluginFiles = $this->attentionItem( 'plugin_files_ignored', 'scans', 1, 'warning', 'Plugin Files' );
		$wpUpdates = $this->attentionItem( 'wp_updates', 'maintenance', 1, 'warning', 'WordPress Version' );
		$page = new PageOperatorModeLandingTestDouble(
			$this->attentionQuery( [ $ignoredPluginFiles ], [ $wpUpdates ] ),
			[],
			200000
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$actionsQueueRows = $renderData[ 'vars' ][ 'actions_queue_rows' ];

		$this->assertSame(
			[ 'maintenance' ],
			\array_column( $actionsQueueRows, 'key' )
		);
		$this->assertSame(
			[
				'maintenance' => 1,
			],
			\array_combine( \array_column( $actionsQueueRows, 'key' ), \array_column( $actionsQueueRows, 'count' ) )
		);
		$this->assertSame(
			[
				'maintenance' => 'warning',
			],
			\array_combine( \array_column( $actionsQueueRows, 'key' ), \array_column( $actionsQueueRows, 'severity' ) )
		);
		$this->assertSame( 'warning', $renderData[ 'vars' ][ 'actions_lane' ][ 'indicator_severity' ] );
		$this->assertNotSame( '', $renderData[ 'strings' ][ 'subtitle' ] );
	}

	public function test_render_data_exposes_actions_queue_title_and_secondary_lanes() :void {
		$this->installControllerStubWithQueuePayload( $this->attentionQuery(
			[
				$this->attentionItem( 'malware', 'scans', 2, 'critical', 'Malware' ),
				$this->attentionItem( 'vulnerable_assets', 'scans', 0, 'good', 'Vulnerabilities' ),
				$this->attentionItem( 'file_locker', 'scans', 1, 'warning', 'File Locker' ),
			],
			[
				$this->attentionItem( 'wp_updates', 'maintenance', 1, 'warning', 'WordPress Version' ),
			]
		) );

		$renderData = $this->invokeNonPublicMethod( $this->newPage(), 'getRenderData' );

		$this->assertIsString( $renderData[ 'strings' ][ 'title' ] );
		$this->assertSame( 'actions', $renderData[ 'vars' ][ 'actions_lane' ][ 'mode' ] );
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $renderData[ 'vars' ][ 'secondary_lanes' ], 'mode' )
		);
		$this->assertSame(
			[ 'malware', 'file_locker', 'maintenance' ],
			\array_column( $renderData[ 'vars' ][ 'actions_queue_rows' ], 'key' )
		);
		$this->assertNull( $renderData[ 'vars' ][ 'actions_all_clear' ] ?? null );
	}

	public function test_render_data_ignores_ignored_only_scan_items_when_they_are_the_only_dashboard_issues() :void {
		$renderData = $this->invokeNonPublicMethod( new PageOperatorModeLandingTestDouble(
			$this->attentionQuery( [
				$this->attentionItem( 'wp_files_ignored', 'scans', 2, 'warning', 'ignored-wp-label' ),
				$this->attentionItem( 'plugin_files_ignored', 'scans', 1, 'warning', 'ignored-plugin-label' ),
				$this->attentionItem( 'theme_files_ignored', 'scans', 3, 'warning', 'ignored-theme-label' ),
				$this->attentionItem( 'malware_ignored', 'scans', 4, 'warning', 'ignored-malware-label' ),
			] ),
			[],
			200000
		), 'getRenderData' );

		$this->assertSame( [], $renderData[ 'vars' ][ 'actions_queue_rows' ] );
		$this->assertSame( 'good', $renderData[ 'vars' ][ 'actions_lane' ][ 'indicator_severity' ] );
		$this->assertSame( 'good', $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertIsArray( $renderData[ 'vars' ][ 'actions_all_clear' ] ?? null );
	}

	private function newPage() :PageOperatorModeLanding {
		return new PageOperatorModeLandingTestDouble( $this->queuePayload );
	}

	private function installControllerStubWithQueuePayload( array $queuePayload, array $reportsState = [] ) :void {
		$this->queuePayload = empty( $queuePayload ) ? $this->attentionQuery( [] ) : $queuePayload;
		$reportsState = \array_replace_recursive( [
			'reports_count'    => 0,
			'latest_report_at' => 0,
			'latest_alert_at'  => 0,
		], $reportsState );

		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps'  => (object)[
					'site_query' => new class {
						public function scanRuntime() :array {
							return [ 'is_running' => false ];
						}
					},
				],
				'db_con' => (object)[
					'reports' => new OperatorModeReportsStore(
						'shield_reports'
					),
				],
			]
		);
		ServicesState::mergeItems( [
			'service_wpdb' => new OperatorModeReportsDb(
				$reportsState[ 'reports_count' ],
				$reportsState[ 'latest_report_at' ],
				$reportsState[ 'latest_alert_at' ]
			),
		] );
	}
}

class PageOperatorModeLandingTestDouble extends PageOperatorModeLanding {

	private array $attentionQuery;

	private array $sessions;

	private int $currentTimestamp;

	public function __construct(
		array $attentionQuery,
		array $sessions = [],
		int $currentTimestamp = 200000
	) {
		$this->attentionQuery = $attentionQuery;
		$this->sessions = $sessions;
		$this->currentTimestamp = $currentTimestamp;
	}

	protected function getConfigurationCoverage() :array {
		return [
			'severity'   => 'warning',
			'percentage' => 72,
			'controls'   => [
				'total'    => 6,
				'good'     => 3,
				'warning'  => 2,
				'critical' => 1,
			],
			'zones'      => [
				'total'    => 3,
				'good'     => 1,
				'warning'  => 1,
				'critical' => 1,
			],
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
		throw new \RuntimeException( 'Operator dashboard must not build scan state directly.' );
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

	private string $table;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	public function getTable() :string {
		return $this->table;
	}
}

class OperatorModeReportsDb extends Db {

	/**
	 * @var list<string>
	 */
	public array $queries = [];

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

	public function selectRow( string $query, $format = null ) :array {
		unset( $format );
		$this->queries[] = $query;
		return [
			'count'            => $this->reportsCount,
			'latest_report_at' => $this->latestReportAt,
			'latest_alert_at'  => $this->latestAlertAt,
		];
	}
}
