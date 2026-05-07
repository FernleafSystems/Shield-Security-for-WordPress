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
	CleanQueue,
	CompleteQueue,
	Controller as QueueController,
	ProcessQueueItem,
	QueueItemVO,
	QueueItems,
	QueueProcessor,
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

	public function test_mark_running_uses_queue_item_context_without_scan_row_reload() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700001555 ),
		] );

		$scanUpdates = [];
		$selectorCalls = 0;
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class( $scanUpdates, $selectorCalls ) {
					public array $updates;
					public int $selectorCalls;

					public function __construct( array &$updates, int &$selectorCalls ) {
						$this->updates = &$updates;
						$this->selectorCalls = &$selectorCalls;
					}

					public function getQuerySelector() :object {
						$this->selectorCalls++;
						return new class {
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
			],
		] );

		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id'         => 62,
			'scan_started_at' => 1699999999,
			'meta'            => [
				RunState::META_KEY_LAST_ERROR => 'stale error',
				'scan_meta'                   => 'value',
			],
		] );

		( new RunState() )->markRunning( $item );

		$this->assertSame( 0, $selectorCalls );
		$this->assertCount( 1, $scanUpdates );
		$this->assertSame( 62, $scanUpdates[ 0 ][ 'scan_id' ] );
		$this->assertSame( 'running', $scanUpdates[ 0 ][ 'data' ][ 'status' ] ?? null );
		$this->assertSame( 1700001555, $scanUpdates[ 0 ][ 'data' ][ 'last_process_at' ] ?? null );
		$this->assertArrayNotHasKey( 'started_at', $scanUpdates[ 0 ][ 'data' ] );
		$this->assertSame(
			[ 'scan_meta' => 'value' ],
			\json_decode( \base64_decode( (string)$scanUpdates[ 0 ][ 'data' ][ 'meta' ] ), true )
		);
	}

	public function test_process_queue_item_logs_processing_exception_without_failing_scan() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700002000 ),
		] );

		$scanItemUpdates = [];
		$scanUpdates = [];
		$deletedScanItems = [];
		$this->installController( [
			'db_con' => (object)[
				'scan_items' => new class( $scanItemUpdates, $deletedScanItems ) {
					public array $updates;
					public array $deleted;

					public function __construct( array &$updates, array &$deleted ) {
						$this->updates = &$updates;
						$this->deleted = &$deleted;
					}

					public function getQueryUpdater() :object {
						return new class( $this->updates ) {
							public array $updates;

							public function __construct( array &$updates ) {
								$this->updates = &$updates;
							}

							public function updateById( int $id, array $data ) :bool {
								$this->updates[] = [ 'id' => $id, 'data' => $data ];
								return true;
							}
						};
					}

					public function getQueryDeleter() :object {
						return new class( $this->deleted ) {
							public array $deleted;
							private int $scanID = 0;

							public function __construct( array &$deleted ) {
								$this->deleted = &$deleted;
							}

							public function filterByScan( int $scanID ) :self {
								$this->scanID = $scanID;
								return $this;
							}

							public function filterByNotFinished() :self {
								return $this;
							}

							public function query() :bool {
								$this->deleted[] = $this->scanID;
								return true;
							}
						};
					}
				},
				'scans' => new class( $scanUpdates ) {
					public array $updates;
					private object $record;

					public function __construct( array &$updates ) {
						$this->updates = &$updates;
						$this->record = new class {
							public int $id = 99;
							public int $started_at = 0;
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

							public function updateById( int $id, array $data ) :bool {
								$this->updates[] = [ 'id' => $id, 'data' => $data ];
								return true;
							}
						};
					}
				},
			],
		] );

		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id'  => 99,
			'qitem_id' => 7,
			'scan'     => 'bad',
			'meta'     => [],
			'items'    => [],
		] );

		( new ProcessQueueItem() )->run( $item );

		$this->assertCount( 1, $scanItemUpdates );
		$this->assertSame( 7, $scanItemUpdates[ 0 ][ 'id' ] );
		$this->assertSame( 1700002000, $scanItemUpdates[ 0 ][ 'data' ][ 'started_at' ] ?? null );
		$this->assertCount( 1, $scanUpdates );
		$this->assertSame( 'running', $scanUpdates[ 0 ][ 'data' ][ 'status' ] ?? null );
		$this->assertSame( [], $deletedScanItems );
		$this->assertTrue( QueueLifecycleLogSpy::contains( 'scan_id=99' ) );
		$this->assertTrue( QueueLifecycleLogSpy::contains( 'qitem_id=7' ) );
	}

	public function test_complete_queue_dispatches_next_builder_without_firing_queue_completed_when_backlog_remains() :void {
		$wpdb = new class extends Db {
			public array $queries = [];

			public function selectCustom( $query, $format = null ) {
				unset( $format );
				$this->queries[] = (string)$query;
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
		$this->assertCount( 1, $wpdb->queries );
		$this->assertStringContainsString( 'GROUP BY `status`', $wpdb->queries[ 0 ] );
	}

	public function test_scan_job_progress_uses_single_grouped_progress_query() :void {
		$selector = new class {
			public int $progressCalls = 0;

			public function countProgressForEachScan() :array {
				$this->progressCalls++;
				return [
					1 => [
						'total'      => 4,
						'unfinished' => 1,
					],
					2 => [
						'total'      => 2,
						'unfinished' => 0,
					],
				];
			}

			public function countAllForEachScan() :array {
				throw new \RuntimeException( 'Progress must use the consolidated count query.' );
			}

			public function countUnfinishedForEachScan() :array {
				throw new \RuntimeException( 'Progress must use the consolidated count query.' );
			}
		};
		$this->installController( [
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
		] );

		$this->assertSame( 0.875, ( new QueueController() )->getScanJobProgress() );
		$this->assertSame( 1, $selector->progressCalls );
	}

	public function test_scan_job_progress_reports_complete_when_no_grouped_counts_exist() :void {
		$selector = new class {
			public int $progressCalls = 0;

			public function countProgressForEachScan() :array {
				$this->progressCalls++;
				return [];
			}
		};
		$this->installController( [
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
		] );

		$this->assertSame( 1.0, ( new QueueController() )->getScanJobProgress() );
		$this->assertSame( 1, $selector->progressCalls );
	}

	public function test_scan_job_progress_ignores_zero_total_group_without_dividing_by_zero() :void {
		$selector = new class {
			public function countProgressForEachScan() :array {
				return [
					1 => [
						'total'      => 0,
						'unfinished' => 0,
					],
					2 => [
						'total'      => 2,
						'unfinished' => 1,
					],
				];
			}
		};
		$this->installController( [
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
		] );

		$this->assertSame( 0.25, ( new QueueController() )->getScanJobProgress() );
	}

	public function test_set_scan_completed_uses_conditional_update_and_single_bounded_result_lookup() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700003500 ),
			'service_wpdb'    => $wpdb = new class extends Db {
				public array $doSqlQueries = [];
				public array $selectQueries = [];

				public function doSql( string $sqlQuery ) {
					$this->doSqlQueries[] = $sqlQuery;
					return 1;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $format );
					$this->selectQueries[] = (string)$query;
					return [];
				}
			},
		] );

		$events = [];
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
				'scans' => new class {
					public function getScanCon( string $scan ) :object {
						unset( $scan );
						return new class {
							public function getScanName() :string {
								return 'WPV';
							}

							public function getNewResultsSet() :object {
								return new class {
									public function countItems() :int {
										return 0;
									}
								};
							}
						};
					}
				},
				'events' => new class( $events ) {
					public array $events;

					public function __construct( array &$events ) {
						$this->events = &$events;
					}

					public function fireEvent( string $event, array $meta = [] ) :void {
						$this->events[] = [
							'event' => $event,
							'meta'  => $meta,
						];
					}
				},
			],
		] );

		$this->assertTrue( ( new SetScanCompleted() )->run( 44 ) );

		$this->assertCount( 2, $wpdb->doSqlQueries );
		$this->assertStringContainsString( 'NOT EXISTS', $wpdb->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( '`finished_at`=0', $wpdb->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( 'shield_scan_results', $wpdb->doSqlQueries[ 1 ] );
		$this->assertCount( 1, $wpdb->selectQueries );
		$this->assertStringContainsString( 'LIMIT 31', $wpdb->selectQueries[ 0 ] );
		$this->assertSame( 'scan_run', $events[ 0 ][ 'event' ] ?? null );
	}

	public function test_queue_items_selects_built_and_running_scans_only() :void {
		$queries = [];
		ServicesState::installItems( [
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
						'items'    => base64_encode( json_encode( [ 'item-a' ] ) ),
					];
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
		$this->assertStringContainsString( "`scans`.`status` IN ('built','running')", $queries[ 0 ] );
		$this->assertStringContainsString( "`si`.`finished_at`=0", $queries[ 0 ] );
		$this->assertStringNotContainsString( "'building','running'", $queries[ 0 ] );
		$this->assertStringNotContainsString( "'queued'", $queries[ 0 ] );
	}

	public function test_queue_items_next_supplies_scan_runtime_contract() :void {
		ServicesState::installItems( [
			'service_wpdb' => new class extends Db {
				public function selectRow( string $query, $format = null ) {
					unset( $query, $format );
					return [
						'scan_id'                => '71',
						'scan'                   => 'afs',
						'scope_type'             => 'plugin',
						'scope_key'              => 'akismet/akismet.php',
						'run_trigger'            => 'asset_change',
						'scan_started_at'        => '1700000100',
						'meta'                   => base64_encode( json_encode( [ 'scan_meta' => 'value' ] ) ),
						'qitem_id'               => '8',
						'items'                  => base64_encode( json_encode( [ 'item-a' ] ) ),
						'is_last_item_for_scan'  => '1',
					];
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
		$this->assertTrue( $item->is_last_item_for_scan );
		$this->assertSame( [ 'scan_meta' => 'value' ], $item->meta );
		$this->assertSame( [ 'item-a' ], $item->items );
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
		$this->assertStringNotContainsString( '`items`', $queries[ 0 ] );
		$this->assertStringNotContainsString( '`meta`', $queries[ 0 ] );
	}

	public function test_clean_queue_does_not_fail_stale_queued_scan_while_active_scan_exists() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'queued',
			'created_at'      => 1699999000,
			'last_process_at' => 1699999000,
		] );

		( new CleanQueue() )->execute();

		$this->assertSame( 'queued', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
	}

	public function test_clean_queue_does_not_fail_stale_built_scan_with_queue_items() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
		] );
		$harness->insertScanItem( $scanID, [ 'afs-a' ] );
		$harness->sql->resetQueryLog();

		( new CleanQueue() )->execute();

		$this->assertSame( 'built', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
		$this->assertFalse( $this->queryLogContains( $harness->sql->queryLog(), 'COUNT(*) FROM `scan_items`' ) );
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

		$this->assertInstanceOf( QueueBuilder::class, $builder );
		$this->assertInstanceOf( QueueProcessor::class, $processor );
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

	private function installController( array $properties ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		foreach ( $properties as $property => $value ) {
			$controller->{$property} = $value;
		}
		PluginControllerInstaller::install( $controller );
	}

	private function queryLogContains( array $queries, string $needle ) :bool {
		foreach ( $queries as $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				return true;
			}
		}
		return false;
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
