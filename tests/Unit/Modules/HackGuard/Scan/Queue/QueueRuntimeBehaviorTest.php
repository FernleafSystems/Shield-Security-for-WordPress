<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

if ( !\function_exists( __NAMESPACE__.'\\error_log' ) ) {
	function error_log( string $message ) :bool {
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\QueueLifecycleLogSpy::record( $message );
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record as ScanRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	Build\QueueBuilder,
	CompleteQueue,
	Controller as QueueController,
	ProcessQueueItem,
	QueueHeartbeat,
	QueueItemVO,
	QueueItems,
	QueueMaintenance,
	QueueProcessor,
	QueueWatchdog,
	ReconcileQueue,
	RunState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\{
	QueueLifecycleLogSpy,
	ScanQueueLifecycleHarness
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Db;

class QueueRuntimeBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		QueueLifecycleLogSpy::reset();
		QueueHeartbeat::resetRuntimeCache();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_mark_failed_updates_run_and_deletes_unfinished_items() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700001234 ),
		] );

		$scanUpdates = [];
		$deletedScanItems = [];
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class( $scanUpdates ) {
					public array $updates;
					private object $record;

					public function __construct( array &$updates ) {
						$this->updates = &$updates;
						$this->record = new class {
							public int $id = 55;
							public array $meta = [];

							public function __get( string $key ) {
								return $this->{$key} ?? null;
							}

							public function __set( string $key, $value ) :void {
								$this->{$key} = $value;
							}

							public function getRawData() :array {
								return [
									'id' => $this->id,
									'meta' => base64_encode( wp_json_encode( $this->meta ) ?: '{}' ),
								];
							}
						};
					}

					public function getQuerySelector() :object {
						return new class( $this->record ) {
							private object $record;

							public function __construct( object $record ) {
								$this->record = $record;
							}

							public function byId( int $scanID ) :object {
								$this->record->id = $scanID;
								return $this->record;
							}
						};
					}

					public function getQueryUpdater() :object {
						return new class( $this->updates ) {
							public array $updates;

							public function __construct( array &$updates ) {
								$this->updates = &$updates;
							}

							public function updateById( int $scanID, array $data ) :bool {
								$this->updates[] = [ 'scan_id' => $scanID, 'data' => $data ];
								return true;
							}
						};
					}
				},
				'scan_items' => new class( $deletedScanItems ) {
					public array $deleted;

					public function __construct( array &$deleted ) {
						$this->deleted = &$deleted;
					}

					public function getQueryDeleter() :object {
						return new class( $this->deleted ) {
							public array $deleted;
							private int $scanID = 0;
							private bool $notFinished = false;

							public function __construct( array &$deleted ) {
								$this->deleted = &$deleted;
							}

							public function filterByScan( int $scanID ) :self {
								$this->scanID = $scanID;
								return $this;
							}

							public function filterByNotFinished() :self {
								$this->notFinished = true;
								return $this;
							}

							public function query() :bool {
								$this->deleted[] = [
									'scan_id'      => $this->scanID,
									'not_finished' => $this->notFinished,
								];
								return true;
							}
						};
					}
				},
			],
		] );

		( new RunState() )->markFailed( 55, 'Queue build failed.' );

		$this->assertCount( 1, $scanUpdates );
		$this->assertSame( 55, $scanUpdates[ 0 ][ 'scan_id' ] );
		$this->assertSame( 'failed', $scanUpdates[ 0 ][ 'data' ][ 'status' ] ?? null );
		$this->assertSame( 1700001234, $scanUpdates[ 0 ][ 'data' ][ 'finished_at' ] ?? null );
		$this->assertSame( 1700001234, $scanUpdates[ 0 ][ 'data' ][ 'last_process_at' ] ?? null );
		$this->assertNotSame( '', (string)( $scanUpdates[ 0 ][ 'data' ][ 'meta' ] ?? '' ) );
		$this->assertSame( [
			[
				'scan_id'      => 55,
				'not_finished' => true,
			]
		], $deletedScanItems );
	}

	public function test_mark_built_sets_status_and_ready_timestamp() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700001500 ),
		] );

		$scanUpdates = [];
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class( $scanUpdates ) {
					public array $updates;

					public function __construct( array &$updates ) {
						$this->updates = &$updates;
					}

					public function getQueryUpdater() :object {
						return new class( $this->updates ) {
							public array $updates;

							public function __construct( array &$updates ) {
								$this->updates = &$updates;
							}

							public function updateById( int $scanID, array $data ) :bool {
								$this->updates[] = [ 'scan_id' => $scanID, 'data' => $data ];
								return true;
							}
						};
					}
				},
			],
		] );

		$scan = new ScanRecord();
		$scan->id = 61;
		$scan->meta = [ 'scan_meta' => 'value' ];

		( new RunState() )->markBuilt( $scan );

		$this->assertCount( 1, $scanUpdates );
		$this->assertSame( 61, $scanUpdates[ 0 ][ 'scan_id' ] );
		$this->assertSame( 'built', $scanUpdates[ 0 ][ 'data' ][ 'status' ] ?? null );
		$this->assertSame( 1700001500, $scanUpdates[ 0 ][ 'data' ][ 'ready_at' ] ?? null );
		$this->assertSame( 1700001500, $scanUpdates[ 0 ][ 'data' ][ 'last_process_at' ] ?? null );
		$this->assertSame(
			[ 'scan_meta' => 'value' ],
			\json_decode( \base64_decode( (string)$scanUpdates[ 0 ][ 'data' ][ 'meta' ] ), true )
		);
	}

	public function test_mark_running_preserves_current_marker_while_clearing_stale_diagnostics() :void {
		$harness = ( new ScanQueueLifecycleHarness( 1700001555 ) )->install();
		$marker = [ 'plugin' => [ 'current/plugin.php' ], 'theme' => [] ];
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999999,
			'last_process_at' => 1699999999,
			'meta'            => $this->encodedScanMeta( [
				'asset_comparison_incomplete'       => $marker,
				RunState::META_KEY_LAST_ERROR         => 'stale error',
				RunState::META_KEY_WATCHDOG_RECOVERY => [ 'attempts' => 1 ],
				'scan_meta'                           => 'value',
			] ),
		] );
		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id'         => $scanID,
			'scan_started_at' => 1699999999,
			'meta'            => [
				RunState::META_KEY_LAST_ERROR         => 'stale error',
				RunState::META_KEY_WATCHDOG_RECOVERY => [ 'attempts' => 1 ],
				'scan_meta'                           => 'value',
			],
		] );

		( new RunState() )->markRunning( $item );

		$scan = $harness->scanRow( $scanID );
		$meta = $this->scanMeta( $scan );
		$this->assertSame( 'running', $scan[ 'status' ] );
		$this->assertSame( 1700001555, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 0, (int)$scan[ 'started_at' ] );
		$this->assertSame( $marker, $meta[ 'asset_comparison_incomplete' ] ?? null );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $meta );
		$this->assertArrayNotHasKey( RunState::META_KEY_WATCHDOG_RECOVERY, $meta );
		$this->assertSame( 'value', $meta[ 'scan_meta' ] ?? null );
		$this->assertSame( $meta, $item->meta );
	}

	public function test_mark_running_preserves_queue_item_exception_and_refreshes_item_meta() :void {
		$diagnostic = 'Queue item exception: scan=afs qitem_id=17 attempt=1 exception=RuntimeException message=hard death';
		$harness = ( new ScanQueueLifecycleHarness( 1700001555 ) )->install();
		$marker = [ 'plugin' => [ 'current/plugin.php' ], 'theme' => [] ];
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999999,
			'last_process_at' => 1699999999,
			'meta'            => $this->encodedScanMeta( [
				'asset_comparison_incomplete' => $marker,
				RunState::META_KEY_LAST_ERROR => $diagnostic,
			] ),
		] );
		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id'         => $scanID,
			'scan_started_at' => 1699999999,
			'meta'            => [ RunState::META_KEY_LAST_ERROR => $diagnostic ],
		] );

		( new RunState() )->markRunning( $item );

		$meta = $this->scanMeta( $harness->scanRow( $scanID ) );
		$this->assertSame( $marker, $meta[ 'asset_comparison_incomplete' ] ?? null );
		$this->assertSame( $diagnostic, $meta[ RunState::META_KEY_LAST_ERROR ] ?? null );
		$this->assertSame( $meta, $item->meta );
	}

	public function test_mark_running_clears_only_watchdog_recovery_alongside_queue_item_exception() :void {
		$diagnostic = 'Queue item exception: scan=afs qitem_id=18 attempt=1 exception=RuntimeException message=hard death';
		$harness = ( new ScanQueueLifecycleHarness( 1700001555 ) )->install();
		$marker = [ 'plugin' => [ 'current/plugin.php' ], 'theme' => [] ];
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999999,
			'last_process_at' => 1699999999,
			'meta'            => $this->encodedScanMeta( [
				'asset_comparison_incomplete'       => $marker,
				RunState::META_KEY_LAST_ERROR         => $diagnostic,
				RunState::META_KEY_WATCHDOG_RECOVERY => [ 'attempts' => 1 ],
				'scan_meta'                           => 'value',
			] ),
		] );
		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id'         => $scanID,
			'scan_started_at' => 1699999999,
			'meta'            => [
				RunState::META_KEY_LAST_ERROR         => $diagnostic,
				RunState::META_KEY_WATCHDOG_RECOVERY => [ 'attempts' => 1 ],
				'scan_meta'                           => 'value',
			],
		] );

		( new RunState() )->markRunning( $item );

		$meta = $this->scanMeta( $harness->scanRow( $scanID ) );
		$this->assertSame( [
			'asset_comparison_incomplete' => $marker,
			RunState::META_KEY_LAST_ERROR => $diagnostic,
			'scan_meta'                   => 'value',
		], $meta );
		$this->assertSame( $meta, $item->meta );
	}

	public function test_mark_running_primes_heartbeat_throttle_without_scan_item_writes() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000 );
		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id'         => $scanID,
			'qitem_id'        => $itemID,
			'scan'            => 'afs',
			'scan_started_at' => '0',
			'meta'            => [],
			'items'           => [ 'afs-a' ],
		] );

		( new RunState() )->markRunning( $item );
		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'running', $scan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'started_at' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'last_process_at' ] );
		$harness->sql->resetQueryLog();

		$this->assertFalse( ( new QueueHeartbeat() )->tick( $scanID ) );
		$this->assertSame( [], $harness->sql->queryLog() );
		$this->assertSame( 1699999000, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
	}

	public function test_mark_building_primes_heartbeat_throttle_without_scan_item_writes() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'queued',
			'last_process_at' => 1699999000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000 );
		$scan = new ScanRecord();
		$scan->id = $scanID;
		$scan->meta = [
			RunState::META_KEY_LAST_ERROR         => 'stale builder error',
			RunState::META_KEY_WATCHDOG_RECOVERY => [ 'attempts' => 2 ],
			'scan_meta'                           => 'value',
		];

		( new RunState() )->markBuilding( $scan );
		$scanRow = $harness->scanRow( $scanID );
		$this->assertSame( 'building', $scanRow[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scanRow[ 'last_process_at' ] );
		$this->assertSame(
			[ 'scan_meta' => 'value' ],
			\json_decode( \base64_decode( (string)$scanRow[ 'meta' ] ), true )
		);
		$harness->sql->resetQueryLog();

		$this->assertFalse( ( new QueueHeartbeat() )->tickBuilding( $scanID ) );
		$this->assertSame( [], $harness->sql->queryLog() );
		$this->assertSame( 1699999000, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
	}

	public function test_process_queue_item_logs_processing_exception_without_failing_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness( 1700002000 ) )->install();
		$scanID = $harness->insertScan( [
			'scan'   => 'bad',
			'status' => 'built',
			'meta'   => $this->encodedScanMeta( [
				RunState::META_KEY_LAST_ERROR => 'Queue item exception: scan=bad qitem_id=7 attempt=1 exception=RuntimeException message=old failure',
			] ),
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'bad-item' ] );

		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id'  => $scanID,
			'qitem_id' => $itemID,
			'scan'     => 'bad',
			'attempts' => 2,
			'meta'     => [
				RunState::META_KEY_LAST_ERROR => 'Queue item exception: scan=bad qitem_id=7 attempt=1 exception=RuntimeException message=old failure',
			],
			'items'    => [],
		] );

		( new ProcessQueueItem() )->run( $item );

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'running', $scan[ 'status' ] );
		$this->assertSame( 1700002000, (int)$scan[ 'last_process_at' ] );
		$meta = $this->scanMeta( $scan );
		$this->assertArrayHasKey( RunState::META_KEY_LAST_ERROR, $meta );
		$message = $meta[ RunState::META_KEY_LAST_ERROR ];
		$this->assertStringStartsWith( 'Queue item exception:', $message );
		$this->assertStringContainsString( 'scan=bad', $message );
		$this->assertStringContainsString( 'qitem_id='.$itemID, $message );
		$this->assertStringContainsString( 'attempt=2', $message );
		$this->assertStringContainsString( 'exception=InvalidArgumentException', $message );
		$this->assertStringContainsString( 'Unknown scan slug: bad', $message );
		$this->assertStringNotContainsString( 'old failure', $message );
		$this->assertSame( 0, (int)$harness->scanItemRow( $itemID )[ 'finished_at' ] );
		$this->assertTrue( QueueLifecycleLogSpy::contains(
			\sprintf(
				'Shield scan processing exception: scan_id=%d qitem_id=%d scan=bad message=Unknown scan slug: bad',
				$scanID,
				$itemID
			)
		) );
	}

	public function test_complete_queue_dispatches_next_builder_without_firing_queue_completed_when_backlog_remains() :void {
		$wpdb = new class extends Db {
			public array $queries = [];

			public function selectCustom( $query, $format = null ) {
				unset( $format );
				$this->queries[] = (string)$query;
				if ( \strpos( (string)$query, 'GROUP BY `status`' ) === false ) {
					return [];
				}
				return [
					[
						'status' => 'queued',
						'count'  => 1,
					],
				];
			}
		};
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700003000 ),
			'service_wpdb'    => $wpdb,
		] );

		$finishedDeletes = 0;
		$dispatches = 0;
		$this->installController( [
			'db_con' => (object)[
				'scan_items' => new class( $finishedDeletes ) {
					public int $finishedDeletes;

					public function __construct( int &$finishedDeletes ) {
						$this->finishedDeletes = &$finishedDeletes;
					}

					public function getQueryDeleter() :object {
						return new class( $this->finishedDeletes ) {
							public int $finishedDeletes;

							public function __construct( int &$finishedDeletes ) {
								$this->finishedDeletes = &$finishedDeletes;
							}

							public function filterByFinished() :self {
								return $this;
							}

							public function query() :bool {
								$this->finishedDeletes++;
								return true;
							}
						};
					}

					public function getTable() :string {
						return 'shield_scan_items';
					}
				},
				'scans' => new class {
					public function getTable() :string {
						return 'shield_scans';
					}
				},
			],
			'comps' => (object)[
				'scans_queue' => new class( $dispatches ) {
					public int $dispatches;

					public function __construct( int &$dispatches ) {
						$this->dispatches = &$dispatches;
					}

					public function getQueueBuilder() :object {
						return new class( $this->dispatches ) {
							public int $dispatches;

							public function __construct( int &$dispatches ) {
								$this->dispatches = &$dispatches;
							}

							public function dispatch() :void {
								$this->dispatches++;
							}
						};
					}
				},
			],
			'opts' => new class {
				public function optGet( string $key ) :bool {
					unset( $key );
					return true;
				}

				public function optSet( string $key, $value ) :self {
					unset( $key, $value );
					return $this;
				}
			},
		] );

		Functions\expect( 'do_action' )->never();
		Functions\expect( 'wp_next_scheduled' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		( new CompleteQueue() )->complete();

		$this->assertSame( 1, $finishedDeletes );
		$this->assertSame( 1, $dispatches );
		$this->assertTrue( $this->queryLogContains( $wpdb->queries, 'GROUP BY `status`' ) );
	}

	public function test_scan_job_progress_uses_single_grouped_progress_query() :void {
		$selector = $this->installProgressController( [
			1 => [
				'total'      => 4,
				'unfinished' => 1,
			],
			2 => [
				'total'      => 2,
				'unfinished' => 0,
			],
		] );

		$this->assertSame( 0.875, ( new QueueController() )->getScanJobProgress() );
		$this->assertSame( 1, $selector->progressCalls );
	}

	public function test_scan_job_progress_reports_complete_when_no_grouped_counts_exist() :void {
		$selector = $this->installProgressController( [] );

		$this->assertSame( 1.0, ( new QueueController() )->getScanJobProgress() );
		$this->assertSame( 1, $selector->progressCalls );
	}

	public function test_scan_job_progress_ignores_zero_total_group_without_dividing_by_zero() :void {
		$this->installProgressController( [
			1 => [
				'total'      => 0,
				'unfinished' => 0,
			],
			2 => [
				'total'      => 2,
				'unfinished' => 1,
			],
		] );

		$this->assertSame( 0.25, ( new QueueController() )->getScanJobProgress() );
	}

	public function test_active_scan_progress_rows_use_single_grouped_progress_query() :void {
		$selector = $this->installProgressController( [
			11 => [
				'total'      => 4,
				'unfinished' => 1,
			],
			12 => [
				'total'      => 2,
				'unfinished' => 0,
			],
		], true );

		$rows = ( new QueueController() )->getActiveScanProgressRows( [
			$this->activeScanRow( 11, 'afs', 'running', 1699999950, 1699999950, 1699999950 ),
			$this->activeScanRow( 12, 'wpv', 'built', 1699999960, 1699999960, 1699999960 ),
		] );

		$this->assertSame( 1, $selector->progressCalls );
		$this->assertCount( 2, $rows );
		$this->assertSame( 11, $rows[ 0 ][ 'id' ] );
		$this->assertSame( 'afs', $rows[ 0 ][ 'scan' ] );
		$this->assertSame( 'Scan Name: afs', $rows[ 0 ][ 'name' ] );
		$this->assertSame( 'running', $rows[ 0 ][ 'display_status' ] );
		$this->assertTrue( $rows[ 0 ][ 'is_current' ] );
		$this->assertFalse( $rows[ 0 ][ 'is_stale' ] );
		$this->assertFalse( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 75, $rows[ 0 ][ 'progress' ] );
		$this->assertSame( 4, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 1, $rows[ 0 ][ 'unfinished' ] );
		$this->assertSame( 12, $rows[ 1 ][ 'id' ] );
		$this->assertSame( 'wpv', $rows[ 1 ][ 'scan' ] );
		$this->assertSame( 'waiting', $rows[ 1 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 1 ][ 'is_current' ] );
		$this->assertFalse( $rows[ 1 ][ 'is_stale' ] );
		$this->assertFalse( $rows[ 1 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'progress' ] );
		$this->assertSame( 2, $rows[ 1 ][ 'total_items' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'unfinished' ] );
	}

	public function test_active_scan_progress_rows_handle_missing_and_zero_counts() :void {
		$this->installProgressController( [
			21 => [
				'total'      => 0,
				'unfinished' => 0,
			],
		], true );

		$rows = ( new QueueController() )->getActiveScanProgressRows( [
			$this->activeScanRow( 21, 'afs', 'running', 1699999950, 1699999950, 1699999950 ),
			$this->activeScanRow( 22, 'apc', 'queued', 1699999960, 0, 0 ),
		] );

		$this->assertSame( 0, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 0, $rows[ 0 ][ 'unfinished' ] );
		$this->assertSame( 0, $rows[ 0 ][ 'progress' ] );
		$this->assertSame( 'running', $rows[ 0 ][ 'display_status' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'total_items' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'unfinished' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'progress' ] );
		$this->assertSame( 'waiting', $rows[ 1 ][ 'display_status' ] );
	}

	public function test_active_scan_progress_rows_report_stalled_without_recovery_mutation() :void {
		$this->installProgressController( [
			31 => [
				'total'      => 4,
				'unfinished' => 2,
			],
		], true );

		$rows = ( new QueueController() )->getActiveScanProgressRows( [
			$this->activeScanRow( 31, 'afs', 'running', 1699999000, 1699999000, 1699999000 ),
		] );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'stalled', $rows[ 0 ][ 'display_status' ] );
		$this->assertTrue( $rows[ 0 ][ 'is_stale' ] );
		$this->assertTrue( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 50, $rows[ 0 ][ 'progress' ] );
		$this->assertSame( 4, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 2, $rows[ 0 ][ 'unfinished' ] );
	}

	/**
	 * @dataProvider activeScanStaleProvider
	 */
	public function test_active_scan_progress_rows_apply_stale_timestamp_rules(
		string $status,
		int $createdAt,
		int $readyAt,
		int $lastProcessAt,
		bool $expectedStale
	) :void {
		$this->installProgressController( [
			41 => [
				'total'      => 5,
				'unfinished' => 2,
			],
		], true );

		$rows = ( new QueueController() )->getActiveScanProgressRows( [
			$this->activeScanRow( 41, 'afs', $status, $createdAt, $readyAt, $lastProcessAt ),
		] );

		$this->assertSame( $expectedStale, $rows[ 0 ][ 'is_stale' ] );
		$this->assertSame( $expectedStale, $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( $expectedStale ? 'stalled' : 'running', $rows[ 0 ][ 'display_status' ] );
		$this->assertSame( 60, $rows[ 0 ][ 'progress' ] );
	}

	public static function activeScanStaleProvider() :array {
		return [
			'queued stale last process' => [ 'queued', 1699999990, 0, 1699999000, true ],
			'queued stale created fallback' => [ 'queued', 1699999000, 0, 0, true ],
			'queued fresh created fallback' => [ 'queued', 1699999900, 0, 0, false ],
			'building stale created fallback' => [ 'building', 1699999000, 0, 0, true ],
			'built stale ready fallback' => [ 'built', 1699999990, 1699999000, 0, true ],
			'built missing ready guard' => [ 'built', 1699999000, 0, 0, false ],
			'running stale last process' => [ 'running', 1699999000, 1699999900, 1699999000, true ],
			'running missing ready guard' => [ 'running', 1699999000, 0, 0, false ],
			'running fresh last process' => [ 'running', 1699999000, 1699999000, 1699999900, false ],
			'running exact cutoff boundary' => [ 'running', 1699999000, 1699999000, 1699999820, false ],
		];
	}

	/**
	 * @dataProvider claimedScanReloadProvider
	 */
	public function test_explicit_recovery_stops_safely_when_claimed_scan_is_missing_finished_or_non_active(
		?string $status,
		int $finishedAt
	) :void {
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpdb'    => $wpdb = new class extends Db {
				public array $writes = [];

				public function doSql( string $sqlQuery ) {
					$this->writes[] = $sqlQuery;
					return 1;
				}

				public function getVar( $sql ) {
					unset( $sql );
					return 0;
				}
			},
		] );
		$selector = new class( $status, $finishedAt ) {
			public int $calls = 0;
			private ?string $status;
			private int $finishedAt;

			public function __construct( ?string $status, int $finishedAt ) {
				$this->status = $status;
				$this->finishedAt = $finishedAt;
			}

			public function byId( int $scanID ) {
				$this->calls++;
				if ( $this->status === null ) {
					return null;
				}
				$scan = new ScanRecord();
				$scan->id = $scanID;
				$scan->status = $this->status;
				$scan->finished_at = $this->finishedAt;
				return $scan;
			}
		};
		$this->installController( [
			'cfg'    => (object)[
				'properties' => [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				],
			],
			'db_con' => (object)[
				'scans' => new class( $selector ) {
					private object $selector;

					public function __construct( object $selector ) {
						$this->selector = $selector;
					}

					public function getTable() :string {
						return 'shield_scans';
					}

					public function getQuerySelector() :object {
						return $this->selector;
					}
				},
			],
		] );

		$this->assertTrue( ( new QueueWatchdog() )->recoverScanIfStale( 77 ) );
		$this->assertSame( 1, $selector->calls );
		$this->assertCount( 1, $wpdb->writes );
		$this->assertStringContainsString( 'active_head', $wpdb->writes[ 0 ] );
	}

	public static function claimedScanReloadProvider() :array {
		return [
			'missing row'         => [ null, 0 ],
			'non-active row'      => [ 'completed', 0 ],
			'active finished row' => [ 'running', 1700000000 ],
		];
	}

	public function test_selected_ready_reconciliation_returns_null_when_classification_query_fails() :void {
		ServicesState::installItems( [
			'service_wpdb' => new class extends Db {
				public function selectRow( string $query, $format = null ) {
					unset( $query, $format );
					return false;
				}
			},
		] );
		$this->installController( [
			'db_con' => (object)[
				'scan_items' => new class {
					public function getTable() :string {
						return 'shield_scan_items';
					}
				},
			],
		] );
		$scan = new ScanRecord();
		$scan->id = 88;

		$this->assertNull( ( new ReconcileQueue() )->reconcileReadyScan( $scan ) );
	}

	public function test_active_scan_progress_rows_report_non_current_stale_row_as_waiting() :void {
		$this->installProgressController( [
			51 => [
				'total'      => 4,
				'unfinished' => 1,
			],
			52 => [
				'total'      => 4,
				'unfinished' => 2,
			],
		], true );

		$rows = ( new QueueController() )->getActiveScanProgressRows( [
			$this->activeScanRow( 51, 'afs', 'running', 1699999900, 1699999900, 1699999900 ),
			$this->activeScanRow( 52, 'wpv', 'queued', 1699999000, 0, 0 ),
		] );

		$this->assertSame( 'running', $rows[ 0 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 0 ][ 'is_stale' ] );
		$this->assertFalse( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 75, $rows[ 0 ][ 'progress' ] );
		$this->assertFalse( $rows[ 1 ][ 'is_current' ] );
		$this->assertFalse( $rows[ 1 ][ 'is_stale' ] );
		$this->assertFalse( $rows[ 1 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 'waiting', $rows[ 1 ][ 'display_status' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'progress' ] );
	}

	public function test_active_scan_progress_rows_clamp_overrun_counts() :void {
		$this->installProgressController( [
			61 => [
				'total'      => 4,
				'unfinished' => 8,
			],
		], true );

		$rows = ( new QueueController() )->getActiveScanProgressRows( [
			$this->activeScanRow( 61, 'afs', 'running', 1699999900, 1699999900, 1699999900 ),
		] );

		$this->assertSame( 0, $rows[ 0 ][ 'progress' ] );
		$this->assertSame( 4, $rows[ 0 ][ 'total_items' ] );
		$this->assertSame( 8, $rows[ 0 ][ 'unfinished' ] );
	}

	public function test_set_scan_completed_uses_conditional_update_and_single_bounded_result_lookup() :void {
		$harness = $this->installSetScanCompletedHarness( [ 1, 1 ] );

		$this->assertTrue( ( new SetScanCompleted() )->run( 44 ) );

		$this->assertCount( 2, $harness->wpdb->doSqlQueries );
		$this->assertStringContainsString( 'NOT EXISTS', $harness->wpdb->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( '`finished_at`=0', $harness->wpdb->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( 'shield_scan_results', $harness->wpdb->doSqlQueries[ 1 ] );
		$this->assertSame( 1, $harness->scans->memoizationResets );
		$this->assertCount( 1, $harness->wpdb->selectQueries );
		$this->assertStringContainsString( 'LIMIT 31', $harness->wpdb->selectQueries[ 0 ] );
		$this->assertCount( 1, $harness->events );
		$this->assertSame( 'scan_run', $harness->events[ 0 ][ 'event' ] );
	}

	public function test_set_scan_completed_keeps_audit_lookup_without_memoization_reset_when_no_stale_items_change() :void {
		$harness = $this->installSetScanCompletedHarness( [ 1, 0 ] );

		$this->assertTrue( ( new SetScanCompleted() )->run( 44 ) );

		$this->assertCount( 2, $harness->wpdb->doSqlQueries );
		$this->assertSame( 0, $harness->scans->memoizationResets );
		$this->assertCount( 1, $harness->wpdb->selectQueries );
		$this->assertStringContainsString( 'LIMIT 31', $harness->wpdb->selectQueries[ 0 ] );
		$this->assertCount( 1, $harness->events );
		$this->assertSame( 'scan_run', $harness->events[ 0 ][ 'event' ] );
	}

	public function test_set_scan_completed_preserves_scan_run_and_items_found_audit_events() :void {
		Functions\when( '__' )->returnArg();
		$harness = $this->installSetScanCompletedHarness( [ 1, 1 ], [ 'stable-result-description' ] );

		$this->assertTrue( ( new SetScanCompleted() )->run( 44 ) );

		$this->assertSame( [ 'scan_run', 'scan_items_found' ], \array_column( $harness->events, 'event' ) );
	}

	public function test_queue_items_selects_built_and_running_scans_only() :void {
		$queries = [];
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700003600 ),
			'service_wpdb' => new class( $queries ) extends Db {
				public array $queries;

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function selectRow( string $query, $format = null ) {
					unset( $format );
					$this->queries[] = $query;
					return [
						'scan_id'  => 71,
						'scan'     => 'afs',
						'meta'     => base64_encode( json_encode( [ 'scan_meta' => 'value' ] ) ),
						'qitem_id' => 8,
						'attempts' => 0,
						'items'    => base64_encode( json_encode( [ 'item-a' ] ) ),
					];
				}

				public function doSql( string $sqlQuery ) {
					$this->queries[] = $sqlQuery;
					return 1;
				}
			},
		] );
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class {
					public function getTable() :string {
						return 'shield_scans';
					}
				},
				'scan_items' => new class {
					public function getTable() :string {
						return 'shield_scan_items';
					}
				},
			],
		] );

		$item = ( new QueueItems() )->next();

		$this->assertSame( 71, $item->scan_id );
		$this->assertSame( 8, $item->qitem_id );
		$this->assertNotEmpty( $queries );
		$this->assertStringContainsString( "`oldest_scan`.`status` IN ('built','running')", $queries[ 0 ] );
		$this->assertStringContainsString( "WHERE `scans`.`id` = (SELECT `oldest_scan`.`id`", $queries[ 0 ] );
		$this->assertStringContainsString( "`oldest_scan`.`ready_at` > 0", $queries[ 0 ] );
		$this->assertStringContainsString( "`oldest_scan`.`finished_at`=0", $queries[ 0 ] );
		$this->assertStringContainsString(
			"ORDER BY `oldest_scan`.`created_at` ASC, `oldest_scan`.`id` ASC",
			$queries[ 0 ]
		);
		$this->assertStringContainsString( "ORDER BY `si`.`id` ASC", $queries[ 0 ] );
		$this->assertStringContainsString( "`si`.`started_at`=0", $queries[ 0 ] );
		$this->assertStringContainsString( "`si`.`finished_at`=0", $queries[ 0 ] );
		$this->assertStringNotContainsString( "'building','running'", $queries[ 0 ] );
		$this->assertStringNotContainsString( "'queued'", $queries[ 0 ] );
		$this->assertTrue( $this->queryLogContains( $queries, '`attempts`=`attempts`+1' ) );
		$this->assertTrue( $this->queryLogContains( $queries, '`started_at`=0' ) );
		$this->assertTrue( $this->queryLogContains( $queries, '`finished_at`=0' ) );
	}

	public function test_queue_items_next_supplies_scan_runtime_contract() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700003600 ),
			'service_wpdb' => new class extends Db {
				public function selectRow( string $query, $format = null ) {
					unset( $query, $format );
					return [
						'scan_id'         => '71',
						'scan'            => 'afs',
						'scope_type'      => 'plugin',
						'scope_key'       => 'akismet/akismet.php',
						'run_trigger'     => 'asset_change',
						'scan_started_at' => '1700000100',
						'meta'            => base64_encode( json_encode( [ 'scan_meta' => 'value' ] ) ),
						'qitem_id'        => '8',
						'attempts'        => '1',
						'items'           => base64_encode( json_encode( [ 'item-a' ] ) ),
					];
				}

				public function doSql( string $sqlQuery ) {
					unset( $sqlQuery );
					return 1;
				}
			},
		] );
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class {
					public function getTable() :string {
						return 'shield_scans';
					}
				},
				'scan_items' => new class {
					public function getTable() :string {
						return 'shield_scan_items';
					}
				},
			],
		] );

		$item = ( new QueueItems() )->next();

		$this->assertSame( 71, $item->scan_id );
		$this->assertSame( 8, $item->qitem_id );
		$this->assertSame( 'plugin', $item->scope_type );
		$this->assertSame( 'akismet/akismet.php', $item->scope_key );
		$this->assertSame( 'asset_change', $item->run_trigger );
		$this->assertSame( 1700000100, $item->scan_started_at );
		$this->assertSame( [ 'scan_meta' => 'value' ], $item->meta );
		$this->assertSame( [ 'item-a' ], $item->items );
		$this->assertSame( 2, $item->attempts );
	}

	public function test_queue_items_next_retries_when_selected_row_was_already_claimed() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700003600 ),
			'service_wpdb' => $wpdb = new class extends Db {
				public int $selects = 0;
				public int $claims = 0;

				public function selectRow( string $query, $format = null ) {
					unset( $query, $format );
					$this->selects++;
					return [
						'scan_id'         => '71',
						'scan'            => 'afs',
						'scope_type'      => 'full',
						'scope_key'       => '',
						'run_trigger'     => 'manual',
						'scan_started_at' => '0',
						'meta'            => base64_encode( json_encode( [] ) ),
						'qitem_id'        => (string)( 7 + $this->selects ),
						'attempts'        => '0',
						'items'           => base64_encode( json_encode( [ 'item-'.$this->selects ] ) ),
					];
				}

				public function doSql( string $sqlQuery ) {
					unset( $sqlQuery );
					$this->claims++;
					return $this->claims === 1 ? 0 : 1;
				}
			},
		] );
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class {
					public function getTable() :string {
						return 'shield_scans';
					}
				},
				'scan_items' => new class {
					public function getTable() :string {
						return 'shield_scan_items';
					}
				},
			],
		] );

		$item = ( new QueueItems() )->next();

		$this->assertSame( 2, $wpdb->selects );
		$this->assertSame( 2, $wpdb->claims );
		$this->assertSame( 9, $item->qitem_id );
		$this->assertSame( [ 'item-2' ], $item->items );
		$this->assertSame( 1, $item->attempts );
	}

	public function test_has_next_item_uses_existence_query_without_loading_queue_payload() :void {
		$queries = [];
		ServicesState::installItems( [
			'service_wpdb' => new class( $queries ) extends Db {
				public array $queries;

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function getVar( $sql ) {
					$this->queries[] = (string)$sql;
					return 1;
				}

				public function selectRow( string $query, $format = null ) {
					unset( $query, $format );
					throw new \RuntimeException( 'hasNextItem must not load full queue rows.' );
				}
			},
		] );
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class {
					public function getTable() :string {
						return 'shield_scans';
					}
				},
				'scan_items' => new class {
					public function getTable() :string {
						return 'shield_scan_items';
					}
				},
			],
		] );

		$this->assertTrue( ( new QueueItems() )->hasNextItem() );
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'SELECT 1', $queries[ 0 ] );
		$this->assertStringContainsString( "`si`.`scan_ref` = (SELECT `oldest_scan`.`id`", $queries[ 0 ] );
		$this->assertStringContainsString( "`oldest_scan`.`status` IN ('built','running')", $queries[ 0 ] );
		$this->assertStringContainsString( "`oldest_scan`.`ready_at` > 0", $queries[ 0 ] );
		$this->assertStringContainsString( "`oldest_scan`.`finished_at`=0", $queries[ 0 ] );
		$this->assertStringContainsString(
			"ORDER BY `oldest_scan`.`created_at` ASC, `oldest_scan`.`id` ASC",
			$queries[ 0 ]
		);
		$this->assertStringContainsString( "`si`.`started_at`=0", $queries[ 0 ] );
		$this->assertStringContainsString( "`si`.`finished_at`=0", $queries[ 0 ] );
		$this->assertStringNotContainsString( '`items`', $queries[ 0 ] );
		$this->assertStringNotContainsString( '`meta`', $queries[ 0 ] );
	}

	public function test_heartbeat_updates_running_scan_by_scan_id_without_touching_items() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$meta = \base64_encode( '{"keep":"running"}' );
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'started_at'      => 1699999000,
			'last_process_at' => 1699999000,
			'created_at'      => 1699998000,
			'meta'            => $meta,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000 );
		$harness->sql->resetQueryLog();

		$this->assertTrue( ( new QueueHeartbeat() )->tick( $scanID ) );

		$queries = $harness->sql->queryLog();
		$this->assertSingleHeartbeatScanUpdateOnlySetsLastProcessAt( $queries );
		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 1700000000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 'running', $scan[ 'status' ] );
		$this->assertSame( 1699999000, (int)$scan[ 'ready_at' ] );
		$this->assertSame( 1699999000, (int)$scan[ 'started_at' ] );
		$this->assertSame( 0, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1699998000, (int)$scan[ 'created_at' ] );
		$this->assertSame( $meta, $scan[ 'meta' ] );
		$this->assertSame( 1699999000, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'scan_items' ) );
	}

	public function test_building_heartbeat_updates_building_scan_by_scan_id_without_touching_items() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$meta = \base64_encode( '{"keep":"building"}' );
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'building',
			'ready_at'        => 0,
			'started_at'      => 0,
			'last_process_at' => 1699999000,
			'created_at'      => 1699998000,
			'meta'            => $meta,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000 );
		$harness->sql->resetQueryLog();

		$this->assertTrue( ( new QueueHeartbeat() )->tickBuilding( $scanID ) );

		$queries = $harness->sql->queryLog();
		$this->assertSingleHeartbeatScanUpdateOnlySetsLastProcessAt( $queries );
		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 1700000000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 'building', $scan[ 'status' ] );
		$this->assertSame( 0, (int)$scan[ 'ready_at' ] );
		$this->assertSame( 0, (int)$scan[ 'started_at' ] );
		$this->assertSame( 0, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1699998000, (int)$scan[ 'created_at' ] );
		$this->assertSame( $meta, $scan[ 'meta' ] );
		$this->assertSame( 1699999000, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'scan_items' ) );
	}

	public function test_running_heartbeat_ignores_building_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'building',
			'last_process_at' => 1699999000,
		] );
		$harness->sql->resetQueryLog();

		$this->assertFalse( ( new QueueHeartbeat() )->tick( $scanID ) );

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'building', $scan[ 'status' ] );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertTrue( ( new QueueHeartbeat() )->tickBuilding( $scanID ) );
		$this->assertSame( 1700000000, (int)$harness->scanRow( $scanID )[ 'last_process_at' ] );
	}

	public function test_repeated_heartbeats_inside_throttle_window_do_not_write_again() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'started_at'      => 1699999000,
			'last_process_at' => 1699999000,
		] );
		$harness->sql->resetQueryLog();

		$this->assertTrue( ( new QueueHeartbeat() )->tick( $scanID ) );
		$this->assertFalse( ( new QueueHeartbeat() )->tick( $scanID ) );

		$this->assertSame( 1, $this->queryLogCount( $harness->sql->queryLog(), 'UPDATE `scans`' ) );
	}

	public function test_repeated_building_heartbeats_inside_throttle_window_do_not_write_again() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'building',
			'last_process_at' => 1699999000,
		] );
		$harness->sql->resetQueryLog();

		$this->assertTrue( ( new QueueHeartbeat() )->tickBuilding( $scanID ) );
		$this->assertFalse( ( new QueueHeartbeat() )->tickBuilding( $scanID ) );

		$this->assertSame( 1, $this->queryLogCount( $harness->sql->queryLog(), 'UPDATE `scans`' ) );
	}

	public function test_heartbeat_refuses_finished_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'started_at'      => 1699999000,
			'last_process_at' => 1699999000,
			'finished_at'     => 1699999900,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000 );
		$harness->sql->resetQueryLog();

		$this->assertFalse( ( new QueueHeartbeat() )->tick( $scanID ) );

		$queries = $harness->sql->queryLog();
		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 1699999900, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1699999000, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertTrue( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'scan_items' ) );
	}

	public function test_building_heartbeat_refuses_finished_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'building',
			'last_process_at' => 1699999000,
			'finished_at'     => 1699999900,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000 );
		$harness->sql->resetQueryLog();

		$this->assertFalse( ( new QueueHeartbeat() )->tickBuilding( $scanID ) );

		$queries = $harness->sql->queryLog();
		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 1699999900, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1699999000, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertTrue( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'scan_items' ) );
	}

	public function test_watchdog_does_not_fail_stale_queued_scan_while_builder_can_resume_it() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'queued',
			'created_at'      => 1699999000,
			'last_process_at' => 1699999000,
		] );

		( new QueueWatchdog() )->run();

		$this->assertSame( 'queued', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
	}

	public function test_watchdog_resumes_stale_built_scan_with_unstarted_items_without_resetting_items() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ] );
		$harness->sql->resetQueryLog();

		( new QueueWatchdog() )->run();

		$this->assertSame( 'built', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
		$this->assertSame( 0, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $harness->sql->queryLog(), 'UPDATE `scan_items` SET `started_at`=0' ) );
	}

	public function test_queue_maintenance_completes_all_finished_ready_scan_without_resetting_active_items() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$completedScanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$activeScanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$harness->insertScanItem( $completedScanID, [ 'wpv-a' ], 0, 1699999000 );
		$activeItemID = $harness->insertScanItem( $activeScanID, [ 'afs-a' ], 1699999000 );
		$harness->sql->resetQueryLog();

		( new QueueMaintenance() )->run();

		$this->assertSame( 'completed', $harness->scanRow( $completedScanID )[ 'status' ] );
		$this->assertSame( 'running', $harness->scanRow( $activeScanID )[ 'status' ] );
		$this->assertSame( 1699999000, (int)$harness->scanItemRow( $activeItemID )[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $harness->sql->queryLog(), 'UPDATE `scan_items` SET `started_at`=0' ) );
	}

	public function test_on_wp_loaded_registers_queue_workers_without_scan_db_connection() :void {
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );

		$this->installController( [
			'cfg' => (object)[
				'properties' => [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				],
			],
		] );

		$controller = new QueueController();
		$controller->onWpLoaded();

		$builder = $this->readObjectProperty( $controller, 'queueBuilder' );
		$processor = $this->readObjectProperty( $controller, 'queueProcessor' );
		$watchdog = $this->readObjectProperty( $controller, 'queueWatchdog' );

		$this->assertInstanceOf( QueueBuilder::class, $builder );
		$this->assertInstanceOf( QueueProcessor::class, $processor );
		$this->assertInstanceOf( QueueWatchdog::class, $watchdog );
		$this->assertSame( 'icwp_wpsf_shield_scanqbuild_cron_interval', $this->readObjectProperty( $builder, 'cron_interval_identifier' ) );
		$this->assertSame( 'icwp_wpsf_shield_scanq_cron_interval', $this->readObjectProperty( $processor, 'cron_interval_identifier' ) );
	}

	public function test_scan_queue_transport_uses_plugin_prefix() :void {
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				unset( $hook );
				return $value;
			}
		);

		$this->installController( [
			'cfg' => (object)[
				'properties' => [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				],
			],
		] );

		$builder = new QueueBuilder();
		$processor = new QueueProcessor();

		$this->assertSame( 'icwp_wpsf_shield_scanqbuild', $this->readObjectProperty( $builder, 'identifier' ) );
		$this->assertSame( 'icwp_wpsf_shield_scanqbuild_cron_interval', $this->readObjectProperty( $builder, 'cron_interval_identifier' ) );
		$this->assertSame( 5, $builder->get_cron_interval() );
		$this->assertSame( 'icwp_wpsf_shield_scanq', $this->readObjectProperty( $processor, 'identifier' ) );
		$this->assertSame( 'icwp_wpsf_shield_scanq_cron_interval', $this->readObjectProperty( $processor, 'cron_interval_identifier' ) );
		$this->assertSame( 5, $processor->get_cron_interval() );
		$this->assertSame( \MINUTE_IN_SECONDS*10, $processor->getExpirationInterval() );
	}

	/**
	 * @param array<int,array{total:int,unfinished:int}> $counts
	 */
	private function installProgressController(
		array $counts,
		bool $includeScanNames = false
	) :object {
		$selector = new class( $counts ) {
			public int $progressCalls = 0;
			private array $counts;

			public function __construct( array $counts ) {
				$this->counts = $counts;
			}

			public function countProgressForEachScan() :array {
				$this->progressCalls++;
				return $this->counts;
			}

			public function countAllForEachScan() :array {
				return $this->rejectLegacySelector();
			}

			public function countUnfinishedForEachScan() :array {
				return $this->rejectLegacySelector();
			}

			private function rejectLegacySelector() :array {
				throw new \RuntimeException( 'Progress must use the consolidated count query.' );
			}
		};
		$properties = [
			'db_con' => (object)[
				'scan_items' => new class( $selector ) {
					private object $selector;

					public function __construct( object $selector ) {
						$this->selector = $selector;
					}

					public function getQuerySelector() :object {
						return $this->selector;
					}
				},
			],
		];
		if ( $includeScanNames ) {
			ServicesState::installItems( [
				'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			] );
			$properties[ 'comps' ] = (object)[
				'scans' => $this->scanNameComponent(),
			];
		}
		$this->installController( $properties );
		return $selector;
	}

	private function activeScanRow(
		int $id,
		string $scan,
		string $status,
		int $createdAt,
		int $readyAt,
		int $lastProcessAt
	) :array {
		return [
			'id'              => $id,
			'scan'            => $scan,
			'status'          => $status,
			'scope_type'      => 'full',
			'scope_key'       => '',
			'created_at'      => $createdAt,
			'started_at'      => $readyAt,
			'ready_at'        => $readyAt,
			'last_process_at' => $lastProcessAt,
		];
	}

	private function scanNameComponent() :object {
		return new class {
			public function getScanCon( string $scan ) :object {
				return new class( $scan ) {
					private string $scan;

					public function __construct( string $scan ) {
						$this->scan = $scan;
					}

					public function getScanName() :string {
						return 'Scan Name: '.$this->scan;
					}
				};
			}
		};
	}

	private function installSetScanCompletedHarness( array $doSqlReturns, array $newResultDescriptions = [] ) :object {
		$harness = (object)[
			'events' => [],
		];
		$wpdb = new class( $doSqlReturns ) extends Db {
			public array $doSqlQueries = [];
			public array $selectQueries = [];
			private array $doSqlReturns;

			public function __construct( array $doSqlReturns ) {
				$this->doSqlReturns = $doSqlReturns;
			}

			public function doSql( string $sqlQuery ) :int {
				$this->doSqlQueries[] = $sqlQuery;
				if ( empty( $this->doSqlReturns ) ) {
					throw new \RuntimeException( 'Unexpected SQL write.' );
				}
				return \array_shift( $this->doSqlReturns );
			}

			public function selectCustom( $query, $format = null ) {
				unset( $format );
				$this->selectQueries[] = (string)$query;
				return [];
			}
		};
		$harness->wpdb = $wpdb;

		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700003500 ),
			'service_wpdb'    => $wpdb,
		] );

		$harness->scans = new class( $newResultDescriptions ) {
			public int $memoizationResets = 0;
			private array $newResultDescriptions;

			public function __construct( array $newResultDescriptions ) {
				$this->newResultDescriptions = $newResultDescriptions;
			}

			public function getScanCon( string $scan ) :object {
				unset( $scan );
				return new class( $this->newResultDescriptions ) {
					private array $newResultDescriptions;

					public function __construct( array $newResultDescriptions ) {
						$this->newResultDescriptions = $newResultDescriptions;
					}

					public function getScanName() :string {
						return 'WPV';
					}

					public function getNewResultsSet() :object {
						return new class( $this->newResultDescriptions ) {
							private array $items;

							public function __construct( array $descriptions ) {
								$this->items = \array_map( static fn( string $description ) :object => new class( $description ) {
									private string $description;

									public function __construct( string $description ) {
										$this->description = $description;
									}

									public function getDescriptionForAudit() :string {
										return $this->description;
									}
								}, $descriptions );
							}

							public function countItems() :int {
								return \count( $this->items );
							}

							public function getAllItems() :array {
								return $this->items;
							}
						};
					}
				};
			}

			public function resetScanResultsCountMemoization() :void {
				$this->memoizationResets++;
			}
		};

		$this->installController( [
			'db_con' => (object)[
				'scans' => new class {
					public function getTable() :string {
						return 'shield_scans';
					}

					public function getQuerySelector() :object {
						return new class {
							public function byId( int $scanID ) :ScanRecord {
								$record = new ScanRecord();
								$record->id = $scanID;
								$record->scan = 'wpv';
								$record->scope_type = 'full';
								$record->scope_key = '';
								$record->run_trigger = 'manual';
								return $record;
							}
						};
					}
				},
				'scan_items' => new class {
					public function getTable() :string {
						return 'shield_scan_items';
					}
				},
				'scan_result_items' => new class {
					public function getTable() :string {
						return 'shield_scan_result_items';
					}
				},
				'scan_results' => new class {
					public function getTable() :string {
						return 'shield_scan_results';
					}
				},
			],
			'comps' => (object)[
				'scans'  => $harness->scans,
				'events' => new class( $harness ) {
					private object $harness;

					public function __construct( object $harness ) {
						$this->harness = $harness;
					}

					public function fireEvent( string $event, array $meta = [] ) :void {
						$this->harness->events[] = [
							'event' => $event,
							'meta'  => $meta,
						];
					}
				},
			],
		] );

		return $harness;
	}

	private function installController( array $properties ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		foreach ( $properties as $property => $value ) {
			$controller->{$property} = $value;
		}
		PluginControllerInstaller::install( $controller );
	}

	private function queryLogContains( array $queries, string $needle ) :bool {
		return $this->queryLogCount( $queries, $needle ) > 0;
	}

	private function queryLogCount( array $queries, string $needle ) :int {
		$count = 0;
		foreach ( $queries as $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				$count++;
			}
		}
		return $count;
	}

	private function encodedScanMeta( array $meta ) :string {
		$scan = new ScanRecord();
		$scan->meta = $meta;
		return (string)( $scan->getRawData()[ 'meta' ] ?? '' );
	}

	private function scanMeta( array $scan ) :array {
		return \json_decode( \base64_decode( (string)( $scan[ 'meta' ] ?? '' ) ), true ) ?: [];
	}

	private function assertSingleHeartbeatScanUpdateOnlySetsLastProcessAt( array $queries ) :void {
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'UPDATE `scans`', $queries[ 0 ] );
		$setAt = \strpos( $queries[ 0 ], 'SET ' );
		$whereAt = \strpos( $queries[ 0 ], 'WHERE ' );
		if ( !\is_int( $setAt ) || !\is_int( $whereAt ) || $whereAt <= $setAt ) {
			$this->fail( 'Expected heartbeat update query with SET and WHERE clauses.' );
		}

		$setClause = \substr( $queries[ 0 ], $setAt, $whereAt - $setAt );
		$this->assertStringContainsString( 'SET `last_process_at`=1700000000', $setClause );
		foreach ( [ '`status`', '`ready_at`', '`started_at`', '`finished_at`', '`meta`', '`created_at`' ] as $column ) {
			$this->assertStringNotContainsString( $column, $setClause );
		}
	}

	private function readObjectProperty( object $object, string $property ) {
		$reflectionClass = new \ReflectionClass( $object );
		while ( !$reflectionClass->hasProperty( $property ) ) {
			$reflectionClass = $reflectionClass->getParentClass();
		}
		$reflection = $reflectionClass->getProperty( $property );
		$reflection->setAccessible( true );
		return $reflection->getValue( $object );
	}
}
