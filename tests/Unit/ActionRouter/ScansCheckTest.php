<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansCheck;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class ScansCheckTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_exec_reports_failed_started_scan_with_failure_message() :void {
		$failureMessage = 'producer_failure_detail';
		$controller = $this->installController( $failureMessage );

		$action = $this->runScansCheck( [
			'scan_ids' => [ 21 ],
		] );

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertTrue( $payload[ 'failed' ] );
		$this->assertSame( $failureMessage, $payload[ 'failure_message' ] );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_FAILED, $payload[ 'modal_state' ] );
		$this->assertModalPayloadContract( $payload, ScansCheck::SCAN_MODAL_STATE_FAILED );
		$this->assertArrayNotHasKey( 'vars', $payload );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_FAILED, $controller->action_router->renderData[ 'modal_state' ] );
		$this->assertModalRenderInputDoesNotCarryDerivedFlags( $controller->action_router->renderData );
		$this->assertRenderActionUsed( $controller->action_router );
		$this->assertSame( 100, $controller->action_router->renderData[ 'progress' ] );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_exec_preserves_request_id_precedence_when_failed_scan_query_returns_multiple_rows() :void {
		$controller = $this->installController( '', '', [], [ 'afs' => false, 'wpv' => false, 'apc' => false ], 0.2, [
			(object)[
				'id'     => 32,
				'status' => 'failed',
				'meta'   => [
					RunState::META_KEY_LAST_ERROR => 'second_requested_failure',
				],
			],
			(object)[
				'id'     => 21,
				'status' => 'failed',
				'meta'   => [
					RunState::META_KEY_LAST_ERROR => 'first_requested_failure',
				],
			],
		] );

		$action = $this->runScansCheck( [
			'scan_ids' => [ 21, 32 ],
		] );

		$payload = $action->response()->payload();

		$this->assertModalPayloadContract( $payload, ScansCheck::SCAN_MODAL_STATE_FAILED );
		$this->assertSame( 'first_requested_failure', $payload[ 'failure_message' ] );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
		$this->assertSame( [ 21, 32 ], $controller->db_con->scans->selector->filteredIDs );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_exec_normalizes_unique_positive_integer_scan_ids_at_request_boundary() :void {
		$controller = $this->installController();

		$this->runScansCheck( [
			'scan_ids' => [ 21, '32', 21, '1.5', 1.0, true, 0, -4, [], (string)\PHP_INT_MAX.'0' ],
		] );

		$this->assertSame( [ 21, 32 ], $controller->db_con->scans->selector->filteredIDs );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
	}

	public function test_exec_uses_default_failed_message_when_failed_row_has_no_error_meta() :void {
		$controller = $this->installController( '', '', [], [ 'afs' => false, 'wpv' => false, 'apc' => false ], 0.2, [
			(object)[
				'id'     => 21,
				'status' => 'failed',
				'meta'   => [],
			],
		] );

		$action = $this->runScansCheck( [
			'scan_ids' => [ 21 ],
		] );

		$payload = $action->response()->payload();

		$this->assertModalPayloadContract( $payload, ScansCheck::SCAN_MODAL_STATE_FAILED );
		$this->assertArrayHasKey( 'failure_message', $payload );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_exec_failed_scan_clears_active_rows_and_uses_terminal_progress() :void {
		$controller = $this->installController(
			'failed_scan_detail',
			'wpv',
			[ 'apc' ],
			[ 'afs' => false, 'wpv' => true, 'apc' => true ],
			0.42
		);

		$action = $this->runScansCheck( [
			'scan_ids' => [ 21 ],
		] );
		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'failed' ] );
		$this->assertModalPayloadContract( $payload, ScansCheck::SCAN_MODAL_STATE_FAILED );
		$this->assertSame( [], $payload[ 'scan_rows' ] );
		$this->assertSame( [], $controller->action_router->renderData[ 'scan_rows' ] );
		$this->assertSame( 100, $controller->action_router->renderData[ 'progress' ] );
		$this->assertSame( [ 'afs' => false, 'wpv' => false, 'apc' => false ], $payload[ 'running' ] );
		$this->assertSame( [], $controller->comps->scans_queue->receivedEnqueued );
		$this->assertSame( 0, $controller->comps->scans_queue->activeProgressRowCalls );
		$this->assertSame( 0, \FernleafSystems\Wordpress\Services\Services::WpDb()->selectCustomCalls );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_exec_reports_running_scan_modal_state_and_render_input() :void {
		$controller = $this->installController(
			'',
			'wpv',
			[ 'wpv' ],
			[ 'afs' => false, 'wpv' => true, 'apc' => false ],
			0.42
		);

		$action = $this->runScansCheck();

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'failed' ] );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_RUNNING, $payload[ 'modal_state' ] );
		$this->assertModalPayloadContract( $payload, ScansCheck::SCAN_MODAL_STATE_RUNNING );
		$this->assertArrayNotHasKey( 'vars', $payload );
		$this->assertSame( [ 'afs' => false, 'wpv' => true, 'apc' => false ], $payload[ 'running' ] );
		$this->assertSame( [ 'wpv' ], $controller->comps->scans_queue->receivedEnqueued );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_RUNNING, $controller->action_router->renderData[ 'modal_state' ] );
		$this->assertModalRenderInputDoesNotCarryDerivedFlags( $controller->action_router->renderData );
		$this->assertRenderActionUsed( $controller->action_router );
		$this->assertSame( 42, $controller->action_router->renderData[ 'progress' ] );
		$this->assertArrayHasKey( 'current_scan', $controller->action_router->renderData );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_exec_reports_completed_scan_modal_state_and_render_input() :void {
		$controller = $this->installController(
			'',
			'',
			[],
			[ 'afs' => false, 'wpv' => false, 'apc' => false ],
			0.25
		);

		$action = $this->runScansCheck();

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'failed' ] );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_COMPLETED, $payload[ 'modal_state' ] );
		$this->assertModalPayloadContract( $payload, ScansCheck::SCAN_MODAL_STATE_COMPLETED );
		$this->assertArrayNotHasKey( 'vars', $payload );
		$this->assertSame( [], $controller->comps->scans_queue->receivedEnqueued );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_COMPLETED, $controller->action_router->renderData[ 'modal_state' ] );
		$this->assertModalRenderInputDoesNotCarryDerivedFlags( $controller->action_router->renderData );
		$this->assertRenderActionUsed( $controller->action_router );
		$this->assertSame( 100, $controller->action_router->renderData[ 'progress' ] );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_exec_reports_separate_running_and_waiting_scan_rows() :void {
		$controller = $this->installController(
			'',
			'wpv',
			[ 'apc' ],
			[ 'afs' => false, 'wpv' => true, 'apc' => true ],
			0.42
		);

		$action = $this->runScansCheck();
		$payload = $action->response()->payload();
		$rows = $payload[ 'scan_rows' ];

		$this->assertModalPayloadContract( $payload, ScansCheck::SCAN_MODAL_STATE_RUNNING );
		$this->assertSame( [ 'afs' => false, 'wpv' => true, 'apc' => true ], $payload[ 'running' ] );
		$this->assertCount( 2, $rows );
		$this->assertSame( 1, $rows[ 0 ][ 'id' ] );
		$this->assertSame( 'wpv', $rows[ 0 ][ 'scan' ] );
		$this->assertSame( 'running', $rows[ 0 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 42, $rows[ 0 ][ 'progress' ] );
		$this->assertSame( 2, $rows[ 1 ][ 'id' ] );
		$this->assertSame( 'apc', $rows[ 1 ][ 'scan' ] );
		$this->assertSame( 'waiting', $rows[ 1 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 1 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'progress' ] );
		$this->assertSame( $rows, $controller->action_router->renderData[ 'scan_rows' ] );
		$this->assertRenderActionUsed( $controller->action_router );
		$this->assertSame( 42, $controller->action_router->renderData[ 'progress' ] );
		$this->assertSame( [ 'wpv', 'apc' ], $controller->comps->scans_queue->receivedEnqueued );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_exec_uses_exact_work_units_for_current_scan_progress() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'created_at'      => 1699999900,
			'ready_at'        => 1699999990,
			'last_process_at' => 1699999990,
			'started_at'      => 1699999990,
		] );
		$harness->insertScanItem( $scanID, [ 'large-chunk' ], 1699999900, 1699999950, 1, 80 );
		$harness->insertScanItem( $scanID, [ 'small-chunk' ], 0, 0, 0, 1 );

		$payload = $this->runScansCheck()->response()->payload();
		$rows = $payload[ 'scan_rows' ];

		$this->assertCount( 1, $rows );
		$this->assertSame( 81, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 1, $rows[ 0 ][ 'unfinished' ] );
		$this->assertSame( 99, $rows[ 0 ][ 'progress' ] );
		$this->assertSame( 99, $harness->actionRouter->renderData[ 'progress' ] );
	}

	public function test_exec_treats_legacy_zero_item_counts_as_one_work_unit_in_sqlite_harness() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'created_at'      => 1699999900,
			'ready_at'        => 1699999990,
			'last_process_at' => 1699999990,
			'started_at'      => 1699999990,
		] );
		$harness->insertScanItem( $scanID, [ 'legacy-finished' ], 1699999900, 1699999950, 1, 0 );
		$harness->insertScanItem( $scanID, [ 'legacy-unfinished' ], 0, 0, 0, 0 );

		$payload = $this->runScansCheck()->response()->payload();
		$rows = $payload[ 'scan_rows' ];

		$this->assertCount( 1, $rows );
		$this->assertSame( 2, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 1, $rows[ 0 ][ 'unfinished' ] );
		$this->assertSame( 50, $rows[ 0 ][ 'progress' ] );
		$this->assertSame( 50, $harness->actionRouter->renderData[ 'progress' ] );
	}

	public function test_exec_moves_modal_subject_and_progress_to_next_ordered_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$firstScanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'created_at'      => 1699999900,
			'ready_at'        => 1699999990,
			'last_process_at' => 1699999990,
			'started_at'      => 1699999990,
		] );
		$secondScanID = $harness->insertScan( [
			'scan'       => 'wpv',
			'status'     => 'queued',
			'created_at' => 1699999910,
		] );
		$harness->insertScanItem( $firstScanID, [ 'afs-done' ], 1699999900, 1699999950, 1, 3 );
		$harness->insertScanItem( $firstScanID, [ 'afs-pending' ], 0, 0, 0, 1 );
		$harness->insertScanItem( $secondScanID, [ 'wpv-pending' ], 0, 0, 0, 2 );

		$firstPayload = $this->runScansCheck()->response()->payload();
		$firstRows = $firstPayload[ 'scan_rows' ];
		$firstSubject = $harness->actionRouter->renderData[ 'current_scan' ];
		$this->assertSame( $firstRows[ 0 ][ 'name' ], $firstSubject );
		$this->assertSame( 75, $harness->actionRouter->renderData[ 'progress' ] );

		$harness->sql->updateRowById( 'scans', $firstScanID, [
			'status'      => 'completed',
			'finished_at' => 1700000000,
		] );
		$harness->sql->updateRowById( 'scans', $secondScanID, [
			'status'          => 'running',
			'ready_at'        => 1699999990,
			'last_process_at' => 1699999990,
			'started_at'      => 1699999990,
		] );

		$secondPayload = $this->runScansCheck()->response()->payload();
		$secondRows = $secondPayload[ 'scan_rows' ];

		$this->assertCount( 1, $secondRows );
		$this->assertSame( $secondScanID, $secondRows[ 0 ][ 'id' ] );
		$this->assertSame( $secondRows[ 0 ][ 'name' ], $harness->actionRouter->renderData[ 'current_scan' ] );
		$this->assertNotSame( $firstSubject, $harness->actionRouter->renderData[ 'current_scan' ] );
		$this->assertSame( 0, $harness->actionRouter->renderData[ 'progress' ] );
	}

	public function test_exec_reports_stalled_scan_without_watchdog_mutation() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ] );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansCheck( [
			'scan_ids' => [ $scanID ],
		] )->response()->payload();
		$queries = $harness->sql->queryLog();
		$rows = $payload[ 'scan_rows' ];

		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$this->assertSame( 'running', $scan[ 'status' ] );
		$this->assertSame( 0, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 0, (int)$item[ 'finished_at' ] );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'failed' ] );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_RUNNING, $payload[ 'modal_state' ] );
		$this->assertSame( [ 'afs' => true, 'apc' => false, 'wpv' => false ], $payload[ 'running' ] );
		$this->assertCount( 1, $rows );
		$this->assertSame( $scanID, $rows[ 0 ][ 'id' ] );
		$this->assertSame( 'afs', $rows[ 0 ][ 'scan' ] );
		$this->assertSame( 'stalled', $rows[ 0 ][ 'display_status' ] );
		$this->assertTrue( $rows[ 0 ][ 'is_stale' ] );
		$this->assertTrue( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_RUNNING, $harness->actionRouter->renderData[ 'modal_state' ] );
		$this->assertSame( [], $harness->async->scheduled );
		$this->assertSame( [], $harness->async->remotePosts );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'DELETE FROM `scan_items`' ) );
	}

	private function assertModalRenderInputDoesNotCarryDerivedFlags( array $renderData ) :void {
		foreach ( [ 'is_initiating', 'is_running', 'is_complete', 'is_failed' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $renderData );
		}
	}

	private function assertModalPayloadContract( array $payload, string $expectedState ) :void {
		$this->assertArrayHasKey( 'modal_state', $payload );
		$this->assertSame( $expectedState, $payload[ 'modal_state' ] );
		$this->assertArrayHasKey( 'modal_html', $payload );
		$this->assertIsString( $payload[ 'modal_html' ] );
		$this->assertNotSame( '', $payload[ 'modal_html' ] );
	}

	private function assertRenderActionUsed( object $actionRouter ) :void {
		$this->assertSame( ScansProgress::class, $actionRouter->renderClass );
	}

	private function assertWatchdogNeverUsed( object $queue ) :void {
		$this->assertSame( 0, $queue->watchdogRequests );
	}

	private function runScansCheck( array $actionData = [] ) :ScansCheck {
		$action = new ScansCheck( $actionData );
		$method = new \ReflectionMethod( ScansCheck::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );
		return $action;
	}

	private function scanMeta( array $scan ) :array {
		return \json_decode( \base64_decode( (string)( $scan[ 'meta' ] ?? '' ) ), true ) ?: [];
	}

	private function queryLogContains( array $queries, string $needle ) :bool {
		foreach ( $queries as $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private function installController(
		string $failureMessage = '',
		string $currentScan = '',
		array $enqueued = [],
		array $runningStates = [ 'afs' => false, 'wpv' => false, 'apc' => false ],
		float $progress = 0.2,
		array $failedScanRows = []
	) :Controller {
		ServicesState::installItems( [
			'service_wpdb' => new class( $currentScan, $enqueued ) extends Db {
				public int $selectCustomCalls = 0;

				private string $currentScan;
				private array $enqueued;

				public function __construct( string $currentScan, array $enqueued ) {
					$this->currentScan = $currentScan;
					$this->enqueued = $enqueued;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $query, $format );
					$this->selectCustomCalls++;
					$ordered = $this->currentScan === '' ? [] : [ $this->currentScan ];
					foreach ( $this->enqueued as $scan ) {
						if ( !\in_array( $scan, $ordered, true ) ) {
							$ordered[] = $scan;
						}
					}
					return \array_map(
						static fn( string $scan, int $offset ) :array => [
							'id'              => $offset + 1,
							'scan'            => $scan,
							'status'          => 'running',
							'scope_type'      => 'full',
							'scope_key'       => '',
							'created_at'      => $offset + 1,
							'started_at'      => $offset + 1,
							'ready_at'        => $offset + 1,
							'last_process_at' => $offset + 1,
						],
						$ordered,
						\array_keys( $ordered )
					);
				}
			},
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class( $failureMessage, $failedScanRows ) {
				public object $selector;

				private string $failureMessage;
				private array $failedScanRows;

				public function __construct( string $failureMessage, array $failedScanRows ) {
					$this->failureMessage = $failureMessage;
					$this->failedScanRows = $failedScanRows;
					$this->selector = new class( $failureMessage, $failedScanRows ) {
						public int $queryCount = 0;
						public array $filteredIDs = [];
						private array $ids = [];

						private string $failureMessage;
						private array $failedScanRows;

						public function __construct( string $failureMessage, array $failedScanRows ) {
							$this->failureMessage = $failureMessage;
							$this->failedScanRows = $failedScanRows;
						}

						public function filterByIDs( array $ids ) :self {
							$this->ids = $ids;
							$this->filteredIDs = $ids;
							return $this;
						}

						public function filterByStatus( string $status ) :self {
							unset( $status );
							return $this;
						}

						public function queryWithResult() :array {
							$this->queryCount++;
							if ( empty( $this->ids ) ) {
								return [];
							}
							if ( !empty( $this->failedScanRows ) ) {
								return \array_values( \array_filter(
									$this->failedScanRows,
									fn( object $row ) :bool => \in_array( (int)$row->id, $this->ids, true )
								) );
							}
							if ( $this->failureMessage === '' ) {
								return [];
							}
							return [
								(object)[
							'id'     => $this->ids[ 0 ],
							'status' => 'failed',
							'meta'   => [
								RunState::META_KEY_LAST_ERROR => $this->failureMessage,
							],
						],
					];
						}
					};
				}

				public function getTable() :string {
					return 'shield_scans';
				}

				public function getQuerySelector() :object {
					return $this->selector;
				}
			},
		];
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $slug ) :object {
					return new class( $slug ) {
						private string $slug;

						public function __construct( string $slug ) {
							$this->slug = $slug;
						}

						public function getScanName() :string {
							return 'Scan Name: '.$this->slug;
						}
					};
				}
			},
			'scans_queue' => new class( $runningStates, $progress ) {
				public array $receivedEnqueued = [];
				public int $activeProgressRowCalls = 0;
				public int $watchdogRequests = 0;

				private array $runningStates;
				private float $progress;

				public function __construct( array $runningStates, float $progress ) {
					$this->runningStates = $runningStates;
					$this->progress = $progress;
				}

				public function getScansRunningStates( ?array $enqueued = null ) :array {
					$this->receivedEnqueued = $enqueued ?? [];
					$states = \array_fill_keys( \array_keys( $this->runningStates ), false );
					foreach ( $this->receivedEnqueued as $scan ) {
						$states[ $scan ] = true;
					}
					return $states;
				}

				public function getActiveScanProgressRows( array $activeScans ) :array {
					$this->activeProgressRowCalls++;
					$rows = [];
					foreach ( $activeScans as $index => $activeScan ) {
						$isCurrent = $index === 0;
						$rows[] = [
							'id'                   => (int)$activeScan[ 'id' ],
							'scan'                 => $activeScan[ 'scan' ],
							'name'                 => 'Scan Name: '.$activeScan[ 'scan' ],
							'scope_type'           => $activeScan[ 'scope_type' ],
							'scope_key'            => $activeScan[ 'scope_key' ],
							'raw_status'           => $activeScan[ 'status' ],
							'display_status'       => $isCurrent ? 'running' : 'waiting',
							'is_current'           => $isCurrent,
							'is_stale'             => false,
							'can_attempt_recovery' => false,
							'progress'             => $isCurrent ? (int)\round( 100*$this->progress ) : 0,
							'total_items'          => 10,
							'unfinished'           => $isCurrent ? 6 : 10,
						];
					}
					return $rows;
				}

				public function getQueueWatchdog() :object {
					$this->watchdogRequests++;
					return new \stdClass();
				}
			},
		];
		$controller->action_router = new class {
			public array $renderData = [];
			public string $renderClass = '';

			public function render( string $renderClass, array $data ) :string {
				$this->renderClass = $renderClass;
				$this->renderData = $data;
				return 'rendered-modal';
			}
		};

		PluginControllerInstaller::install( $controller );
		return $controller;
	}
}
