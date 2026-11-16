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
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Db;

/**
 * The progress check, and through it the progress every scan action reports.
 */
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

	public function test_failed_tracked_scan_is_reported_with_its_reason() :void {
		$controller = $this->installController( 'producer_failure_detail' );

		$payload = $this->runScansCheck( [ 'scan_ids' => [ 21 ] ] )->response()->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertProgressPayload( $payload, ScansProgress::STATE_FAILED, '' );
		$this->assertRenderInput( $controller->action_router, ScansProgress::STATE_FAILED, [], [], 'producer_failure_detail' );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
		$this->assertSame( 0, $controller->comps->scans_queue->activeProgressRowCalls );
		$this->assertSame( 0, \FernleafSystems\Wordpress\Services\Services::WpDb()->selectCustomCalls );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_first_failure_in_tracked_order_wins_when_several_scans_failed() :void {
		$controller = $this->installController( '', '', [], 0.2, [
			(object)[
				'id'     => 32,
				'scan'   => 'afs',
				'status' => 'failed',
				'meta'   => [
					RunState::META_KEY_LAST_ERROR => 'second_requested_failure',
				],
			],
			(object)[
				'id'     => 21,
				'scan'   => 'afs',
				'status' => 'failed',
				'meta'   => [
					RunState::META_KEY_LAST_ERROR => 'first_requested_failure',
				],
			],
		] );

		$payload = $this->runScansCheck( [ 'scan_ids' => [ 21, 32 ] ] )->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_FAILED, '' );
		$this->assertSame( 'first_requested_failure', $controller->action_router->renderData[ 'failure_message' ] );
		$this->assertSame( [ 21, 32 ], $controller->db_con->scans->selector->filteredIDs );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_tracked_scan_ids_are_normalised_to_unique_positive_integers_at_the_request_boundary() :void {
		$controller = $this->installController();

		$this->runScansCheck( [
			'scan_ids' => [ 21, '32', 21, '1.5', 1.0, true, 0, -4, [], (string)\PHP_INT_MAX.'0' ],
		] );

		$this->assertSame( [ 21, 32 ], $controller->db_con->scans->selector->filteredIDs );
		$this->assertSame( 2, $controller->db_con->scans->selector->queryCount );
	}

	public function test_failed_scan_without_a_recorded_error_still_gives_a_reason() :void {
		$controller = $this->installController( '', '', [], 0.2, [
			(object)[
				'id'     => 21,
				'scan'   => 'afs',
				'status' => 'failed',
				'meta'   => [],
			],
		] );

		$payload = $this->runScansCheck( [ 'scan_ids' => [ 21 ] ] )->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_FAILED, '' );
		$this->assertSame( 'The scan failed before it could finish.', $controller->action_router->renderData[ 'failure_message' ] );
	}

	public function test_running_scans_are_reported_with_the_current_scan_for_the_indicator() :void {
		$controller = $this->installController( '', 'wpv', [ 'apc' ], 0.42 );

		$payload = $this->runScansCheck()->response()->payload();
		$rows = $controller->action_router->renderData[ 'scan_rows' ];

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertProgressPayload( $payload, ScansProgress::STATE_RUNNING, 'Scan Name: wpv 42%' );
		$this->assertRenderInput( $controller->action_router, ScansProgress::STATE_RUNNING, $rows, [], '' );
		$this->assertSame( [ 'wpv', 'apc' ], \array_column( $rows, 'scan' ) );
		$this->assertSame( [ 'running', 'waiting' ], \array_column( $rows, 'display_status' ) );
		$this->assertSame( [ 42, 0 ], \array_column( $rows, 'progress' ) );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_scans_that_just_finished_are_shown_as_finished_while_others_run() :void {
		$controller = $this->installController( '', 'wpv', [], 0.42, [], [ 'apc', 'afs' ] );

		$payload = $this->runScansCheck()->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_RUNNING, 'Scan Name: wpv 42%' );
		$this->assertSame( [ 'afs', 'apc' ], $controller->action_router->renderData[ 'scan_slugs' ] );
	}

	public function test_with_nothing_followed_or_just_finished_the_result_covers_every_ready_scan() :void {
		$controller = $this->installController();

		$payload = $this->runScansCheck()->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_COMPLETED, '' );
		$this->assertRenderInput( $controller->action_router, ScansProgress::STATE_COMPLETED, [], [ 'afs', 'apc' ], '' );
	}

	public function test_scans_are_reported_completed_once_none_is_active() :void {
		$controller = $this->installController();

		$payload = $this->runScansCheck( [ 'scan_ids' => [ 21 ] ] )->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_COMPLETED, '' );
		$this->assertRenderInput( $controller->action_router, ScansProgress::STATE_COMPLETED, [], [ 'afs' ], '' );
		$this->assertWatchdogNeverUsed( $controller->comps->scans_queue );
	}

	public function test_current_scan_progress_counts_exact_work_units() :void {
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

		$this->runScansCheck();
		$rows = $harness->actionRouter->renderData[ 'scan_rows' ];

		$this->assertCount( 1, $rows );
		$this->assertSame( 81, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 1, $rows[ 0 ][ 'unfinished' ] );
		$this->assertSame( 99, $rows[ 0 ][ 'progress' ] );
	}

	public function test_legacy_zero_item_counts_are_one_work_unit_each() :void {
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

		$this->runScansCheck();
		$rows = $harness->actionRouter->renderData[ 'scan_rows' ];

		$this->assertCount( 1, $rows );
		$this->assertSame( 2, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 1, $rows[ 0 ][ 'unfinished' ] );
		$this->assertSame( 50, $rows[ 0 ][ 'progress' ] );
	}

	public function test_current_scan_moves_on_to_the_next_in_the_queue() :void {
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
		$firstRows = $harness->actionRouter->renderData[ 'scan_rows' ];
		$this->assertSame( $firstScanID, $firstRows[ 0 ][ 'id' ] );
		$this->assertSame( $firstRows[ 0 ][ 'name' ].' 75%', $firstPayload[ 'progress_detail' ] );

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
		$secondRows = $harness->actionRouter->renderData[ 'scan_rows' ];

		$this->assertCount( 1, $secondRows );
		$this->assertSame( $secondScanID, $secondRows[ 0 ][ 'id' ] );
		$this->assertSame( $secondRows[ 0 ][ 'name' ].' 0%', $secondPayload[ 'progress_detail' ] );
		$this->assertNotSame( $firstPayload[ 'progress_detail' ], $secondPayload[ 'progress_detail' ] );
	}

	public function test_stalled_scan_is_reported_without_the_check_changing_it() :void {
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

		$payload = $this->runScansCheck( [ 'scan_ids' => [ $scanID ] ] )->response()->payload();
		$queries = $harness->sql->queryLog();
		$rows = $harness->actionRouter->renderData[ 'scan_rows' ];

		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$this->assertSame( 'running', $scan[ 'status' ] );
		$this->assertSame( 0, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 0, (int)$item[ 'finished_at' ] );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( ScansProgress::STATE_RUNNING, $payload[ 'progress_state' ] );
		$this->assertCount( 1, $rows );
		$this->assertSame( $scanID, $rows[ 0 ][ 'id' ] );
		$this->assertSame( 'stalled', $rows[ 0 ][ 'display_status' ] );
		$this->assertTrue( $rows[ 0 ][ 'is_stale' ] );
		$this->assertTrue( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( [], $harness->async->scheduled );
		$this->assertSame( [], $harness->async->remotePosts );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'DELETE FROM `scan_items`' ) );
	}

	private function assertProgressPayload( array $payload, string $state, string $detail ) :void {
		$this->assertSame( $state, $payload[ 'progress_state' ] );
		$this->assertSame( 'rendered-progress', $payload[ 'progress_html' ] );
		$this->assertSame( $detail, $payload[ 'progress_detail' ] );
		$this->assertSame( [ 'progress_detail', 'progress_html', 'progress_state', 'success' ], $this->sortedKeys( $payload ) );
	}

	private function assertRenderInput( object $actionRouter, string $state, array $rows, array $slugs, string $failureMessage ) :void {
		$this->assertSame( ScansProgress::class, $actionRouter->renderClass );
		$this->assertSame( [
			'state'           => $state,
			'scan_rows'       => $rows,
			'scan_slugs'      => $slugs,
			'failure_message' => $failureMessage,
		], $actionRouter->renderData );
	}

	private function sortedKeys( array $payload ) :array {
		$keys = \array_keys( $payload );
		\sort( $keys );
		return $keys;
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

	/**
	 * @param string $failureMessage  the error recorded on the first tracked scan, which fails it; '' for none
	 * @param string $currentScan     the scan running now; '' for none
	 * @param list<string> $enqueued  the scans waiting behind it
	 * @param list<object> $failedScanRows failed scan rows to report instead
	 * @param list<string> $recentlyFinished scans run by hand that just finished
	 */
	private function installController(
		string $failureMessage = '',
		string $currentScan = '',
		array $enqueued = [],
		float $progress = 0.2,
		array $failedScanRows = [],
		array $recentlyFinished = []
	) :Controller {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest(),
			'service_wpdb'    => new class( $currentScan, $enqueued ) extends Db {
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
			'scans' => new class( $failureMessage, $failedScanRows, $recentlyFinished ) {
				public object $selector;

				public function __construct( string $failureMessage, array $failedScanRows, array $recentlyFinished ) {
					$this->selector = new class( $failureMessage, $failedScanRows, $recentlyFinished ) {
						public int $queryCount = 0;
						public array $filteredIDs = [];
						private array $ids = [];
						private bool $failedOnly = false;
						private bool $recentQuery = false;

						private string $failureMessage;
						private array $failedScanRows;
						private array $recentlyFinished;

						public function __construct( string $failureMessage, array $failedScanRows, array $recentlyFinished ) {
							$this->failureMessage = $failureMessage;
							$this->failedScanRows = $failedScanRows;
							$this->recentlyFinished = $recentlyFinished;
						}

						public function filterByIDs( array $ids ) :self {
							$this->ids = $ids;
							$this->filteredIDs = $ids;
							$this->failedOnly = false;
							$this->recentQuery = false;
							return $this;
						}

						public function filterByScans( array $scans ) :self {
							unset( $scans );
							$this->recentQuery = true;
							return $this;
						}

						public function filterByStatus( string $status ) :self {
							$this->failedOnly = $status === 'failed';
							return $this;
						}

						public function addWhereEquals( string $column, $value ) :self {
							unset( $column, $value );
							return $this;
						}

						public function addWhereNewerThan( int $ts, string $column ) :self {
							unset( $ts, $column );
							return $this;
						}

						public function queryWithResult() :array {
							$this->queryCount++;
							if ( $this->recentQuery ) {
								$this->recentQuery = false;
								return \array_map( static fn( string $scan ) :object => (object)[ 'scan' => $scan ], $this->recentlyFinished );
							}
							if ( empty( $this->ids ) ) {
								return [];
							}
							if ( !$this->failedOnly ) {
								return \array_map( static fn( int $id ) :object => (object)[ 'id' => $id, 'scan' => 'afs' ], $this->ids );
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
									'scan'   => 'afs',
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
			'scans'       => new class {
				public function getAllScanCons() :array {
					$scanCon = static fn( bool $isReady ) :object => new class( $isReady ) {
						private bool $isReady;

						public function __construct( bool $isReady ) {
							$this->isReady = $isReady;
						}

						public function isReady() :bool {
							return $this->isReady;
						}
					};
					return [ 'apc' => $scanCon( true ), 'wpv' => $scanCon( false ), 'afs' => $scanCon( true ) ];
				}
			},
			'scans_queue' => new class( $progress ) {
				public int $activeProgressRowCalls = 0;
				public int $watchdogRequests = 0;

				private float $progress;

				public function __construct( float $progress ) {
					$this->progress = $progress;
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
				return 'rendered-progress';
			}
		};

		PluginControllerInstaller::install( $controller );
		return $controller;
	}
}
