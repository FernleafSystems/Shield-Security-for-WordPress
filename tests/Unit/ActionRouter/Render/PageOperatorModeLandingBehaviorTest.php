<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\DashboardRecentEventsDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops\Handler as EventHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops\Select as EventSelect;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageOperatorModeLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TaskGuideDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\ChartOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];
	private EventSelect $eventSelector;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_request' => new class extends Request {
				public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
					return Carbon::createFromTimestampUTC( 1700000000 )->locale( 'en' );
				}
			},
		] );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		$this->eventSelector = $this->createMock( EventSelect::class );
		$eventHandler = $this->createMock( EventHandler::class );
		$eventHandler->method( 'getQuerySelector' )->willReturn( $this->eventSelector );
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
				'db_con' => (object)[ 'events' => $eventHandler ],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_render_data_exposes_dashboard_strip_destinations_task_guide_recent_events_and_live_monitor() :void {
		$renderData = $this->invokeNonPublicMethod(
			new PageOperatorModeLandingTestDouble( $this->attentionQuery( [], [] ) ),
			'getRenderData'
		);

		$this->assertCount( 6, $renderData[ 'vars' ][ 'dashboard_activity_charts' ] );
		$this->assertCount( 12, $renderData[ 'vars' ][ 'dashboard_recent_events' ] );
		$recentEvents = \array_column( $renderData[ 'vars' ][ 'dashboard_recent_events' ], null, 'key' );
		foreach ( $renderData[ 'vars' ][ 'dashboard_activity_charts' ] as $chart ) {
			$this->assertSame( $chart[ 'icon_class' ], $recentEvents[ $chart[ 'key' ] ][ 'icon_class' ] );
		}
		$this->assertSame(
			[ 'login_block', 'ip_offense', 'ip_blocked', 'conn_kill', 'block_register', 'block_xml' ],
			\array_column( $renderData[ 'vars' ][ 'dashboard_activity_charts' ], 'key' )
		);
		$chartData = \json_decode( $renderData[ 'vars' ][ 'dashboard_activity_chart_data_json' ], true, 512, \JSON_THROW_ON_ERROR );
		$this->assertSame( ChartOptions::PERIOD_7_DAYS, $chartData[ 'period_key' ] );
		$this->assertCount( 7, $chartData[ 'labels' ] );
		$this->assertSame(
			[ 'overall', 'summaries' ],
			\array_keys( $renderData[ 'vars' ][ 'dashboard_strip' ] )
		);
		$this->assertCount( 2, $renderData[ 'vars' ][ 'dashboard_strip' ][ 'summaries' ] );
		$this->assertCount( 3, $renderData[ 'vars' ][ 'destination_cards' ] );
		$this->assertSame( [ 'launcher' ], \array_keys( $renderData[ 'vars' ][ 'dashboard_task_guide' ] ) );
		$this->assertArrayNotHasKey( 'strings', $renderData );
		$this->assertArrayNotHasKey( 'actions_queue_rows', $renderData[ 'vars' ] );
		$this->assertArrayNotHasKey( 'secondary_lanes', $renderData[ 'vars' ] );
	}

	public function test_dashboard_task_guide_exposes_static_branch_and_deep_link_contract() :void {
		$graph = ( new TaskGuideDataBuilder() )->buildGraph();
		$nodes = \array_column( $graph[ 'nodes' ], null, 'key' );

		$this->assertSame( 'start', $graph[ 'initial_node_key' ] );
		$this->assertSame( [ 'back_label', 'close_label' ], \array_keys( $graph[ 'strings' ] ) );
		$this->assertSame( [ 'start', 'ip_access', 'scans', 'investigate', 'configure', 'reports' ], \array_keys( $nodes ) );
		$this->assertSame(
			[ 'manage_ip_access', 'run_or_review_scans', 'investigate', 'configure', 'view_reports' ],
			\array_column( $nodes[ 'start' ][ 'choices' ], 'key' )
		);
		$this->assertSame( 'node', $nodes[ 'start' ][ 'choices' ][ 0 ][ 'target' ][ 'type' ] );
		$this->assertSame( 'ip_access', $nodes[ 'start' ][ 'choices' ][ 0 ][ 'target' ][ 'node_key' ] );
		$this->assertSame( '/admin/ips/rules', $nodes[ 'ip_access' ][ 'choices' ][ 0 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_ip', $nodes[ 'ip_access' ][ 'choices' ][ 1 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/scans/overview?zone=scans', $nodes[ 'scans' ][ 'choices' ][ 0 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/scans/run', $nodes[ 'scans' ][ 'choices' ][ 1 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_user', $nodes[ 'investigate' ][ 'choices' ][ 0 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_ip', $nodes[ 'investigate' ][ 'choices' ][ 1 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_plugin', $nodes[ 'investigate' ][ 'choices' ][ 2 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_theme', $nodes[ 'investigate' ][ 'choices' ][ 3 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/activity/by_core', $nodes[ 'investigate' ][ 'choices' ][ 4 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/zones/overview?zone=firewall', $nodes[ 'configure' ][ 'choices' ][ 0 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/reports/overview?workspace=list', $nodes[ 'reports' ][ 'choices' ][ 0 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/reports/overview?workspace=charts', $nodes[ 'reports' ][ 'choices' ][ 1 ][ 'target' ][ 'href' ] );
		$this->assertSame( '/admin/reports/overview?workspace=settings', $nodes[ 'reports' ][ 'choices' ][ 2 ][ 'target' ][ 'href' ] );
	}

	public function test_recent_events_query_once_and_use_real_timestamps_and_spam_subtypes() :void {
		$now = Carbon::createFromTimestampUTC( 1700000000 )->locale( 'en' );
		$this->eventSelector->expects( $this->once() )
			->method( 'getLatestTimestampsForEvents' )
			->with( $this->callback( static function ( array $keys ) :bool {
				return \count( $keys ) === 17
					&& \count( \array_unique( $keys ) ) === 17
					&& \in_array( 'login_success', $keys, true )
					&& \in_array( 'spam_block_antibot', $keys, true )
					&& \in_array( 'spam_block_bot', $keys, true )
					&& \in_array( 'spam_block_human', $keys, true )
					&& \in_array( 'spam_block_humanrepeated', $keys, true )
					&& \in_array( 'spam_block_cooldown', $keys, true );
			} ) )
			->willReturn( [
				'firewall_block' => 1699999880,
				'login_success' => 1699996400,
				'scan_run' => 1699000000,
				'spam_block_human' => 1699990000,
				'spam_block_antibot' => 1699999700,
				'comment_spam_block' => 1699980000,
			] );
		$items = \array_column( ( new DashboardRecentEventsDataBuilder() )->build(), null, 'key' );
		$this->assertCount( 12, $items );
		foreach ( [ 'firewall_block' => 1699999880, 'login_success' => 1699996400, 'comment_spam_block' => 1699999700 ] as $key => $timestamp ) {
			$this->assertTrue( $items[ $key ][ 'has_record' ] );
			$this->assertSame(
				Carbon::createFromTimestampUTC( $timestamp )->locale( 'en' )->diffForHumans( $now, CarbonInterface::DIFF_RELATIVE_TO_NOW, false, 2 ),
				$items[ $key ][ 'time_ago' ]
			);
		}
		$this->assertLessThan( $items[ 'login_success' ][ 'recency_hue' ], $items[ 'firewall_block' ][ 'recency_hue' ] );
		$this->assertSame( 120, $items[ 'scan_run' ][ 'recency_hue' ] );
		$this->assertFalse( $items[ 'login_block' ][ 'has_record' ] );
	}

	public function test_recent_events_without_records_do_not_invent_ages() :void {
		$this->eventSelector->expects( $this->once() )->method( 'getLatestTimestampsForEvents' )->willReturn( [] );
		$items = ( new DashboardRecentEventsDataBuilder() )->build();
		$this->assertCount( 12, $items );
		$this->assertSame( [ false ], \array_values( \array_unique( \array_column( $items, 'has_record' ) ) ) );
		$this->assertCount( 1, \array_unique( \array_column( $items, 'time_ago' ) ) );
		$this->assertNotEmpty( $items[ 0 ][ 'time_ago' ] );
	}

	public function test_recent_events_clamp_clock_skew_to_now() :void {
		$this->eventSelector->expects( $this->once() )->method( 'getLatestTimestampsForEvents' )->willReturn( [
			'firewall_block' => 1700000000,
			'login_success' => 1700000300,
		] );
		$items = \array_column( ( new DashboardRecentEventsDataBuilder() )->build(), null, 'key' );
		$this->assertSame( 0, $items[ 'login_success' ][ 'recency_hue' ] );
		$this->assertSame( $items[ 'firewall_block' ][ 'time_ago' ], $items[ 'login_success' ][ 'time_ago' ] );
		$this->assertTrue( $items[ 'login_success' ][ 'has_record' ] );
	}

	public function test_destination_cards_have_strict_lightweight_contract_and_canonical_routes() :void {
		$cards = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildDestinationCards' );

		$this->assertCount( 3, $cards );
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $cards, 'mode' )
		);
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $cards, 'accent' )
		);
		$this->assertSame(
			[ 'mode', 'sidebar_label', 'href', 'icon_class', 'accent', 'title', 'description', 'cta', 'accessible_label' ],
			\array_keys( $cards[ 0 ] )
		);
		foreach ( $cards as $card ) {
			$this->assertNotSame( '', $card[ 'href' ] );
			$this->assertNotSame( '', $card[ 'icon_class' ] );
			$this->assertNotSame( '', $card[ 'accessible_label' ] );
		}
	}

	public function test_dashboard_strip_is_passed_through_without_page_recalculation() :void {
		$renderData = $this->invokeNonPublicMethod(
			new PageOperatorModeLandingTestDouble( $this->attentionQuery(
				[ $this->attentionItem( 'malware', 'scans', 2, 'critical' ) ],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 1, 'warning' ) ]
			) ),
			'getRenderData'
		);
		$strip = $renderData[ 'vars' ][ 'dashboard_strip' ];

		$this->assertSame( 'critical', $strip[ 'overall' ][ 'status' ] );
		$this->assertNotSame( '', $strip[ 'overall' ][ 'title' ] );
		$this->assertSame( [ 2, 1 ], \array_column( $strip[ 'summaries' ], 'count' ) );
		$this->assertSame( [ 'critical', 'warning' ], \array_column( $strip[ 'summaries' ], 'status' ) );
	}

	public function test_live_monitor_vars_use_current_compact_contract() :void {
		$vars = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildLiveMonitorVars' );

		$this->assertArrayHasKey( 'is_collapsed', $vars );
		$this->assertIsBool( $vars[ 'is_collapsed' ] );
		$this->assertNotSame( '', $vars[ 'title' ] );
		$this->assertNotSame( '', $vars[ 'activity' ] );
		$this->assertNotSame( '', $vars[ 'traffic' ] );
		$this->assertNotSame( '', $vars[ 'loading' ] );
		$this->assertArrayNotHasKey( 'minimize', $vars );
		$this->assertArrayNotHasKey( 'expand', $vars );
	}

	private function attentionQuery( array $scanItems, array $maintenanceItems ) :array {
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

	private function attentionItem( string $key, string $zone, int $count, string $severity ) :array {
		return [
			'key'                => $key,
			'zone'               => $zone,
			'source'             => $zone === 'scans' ? 'scan' : 'maintenance',
			'label'              => $key,
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
}

class PageOperatorModeLandingTestDouble extends PageOperatorModeLanding {

	private array $attentionQuery;

	public function __construct( array $attentionQuery ) {
		$this->attentionQuery = $attentionQuery;
	}

	protected function buildAttentionQuery() :array {
		return $this->attentionQuery;
	}

	protected function buildDashboardActivityChartData() :array {
		$eventDefinitions = ChartOptions::eventDefinitions();

		return [
			'period_key'   => ChartOptions::PERIOD_7_DAYS,
			'period_label' => '7 days',
			'labels'       => \array_fill( 0, 7, '1 Jan 2026' ),
			'series'       => \array_map(
				static fn( string $key, array $definition ) :array => [
					'key'   => $key,
					'label' => $definition[ 'label' ],
					'data'  => \array_fill( 0, 7, 0 ),
				],
				\array_keys( $eventDefinitions ),
				$eventDefinitions
			),
		];
	}
}
