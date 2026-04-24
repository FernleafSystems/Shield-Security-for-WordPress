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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\QueueLifecycleLogSpy;
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

		( new RunState() )->markBuilt( 61 );

		$this->assertSame( [
			[
				'scan_id' => 61,
				'data'    => [
					'status'          => 'built',
					'ready_at'        => 1700001500,
					'last_process_at' => 1700001500,
				],
			],
		], $scanUpdates );
	}

	public function test_touch_updates_last_process_timestamp_only() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700001555 ),
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

		( new RunState() )->touch( 62 );

		$this->assertSame( [
			[
				'scan_id' => 62,
				'data'    => [
					'last_process_at' => 1700001555,
				],
			],
		], $scanUpdates );
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
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700003000 ),
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
					public function getQuerySelector() :object {
						return new class {
							private string $status = '';
							private bool $activeQuery = false;

							public function filterByNotFinished() :self {
								$this->activeQuery = true;
								return $this;
							}

							public function addWhereIn( string $column, array $values ) :self {
								unset( $column, $values );
								return $this;
							}

							public function filterByStatus( string $status ) :self {
								$this->status = $status;
								return $this;
							}

							public function count() :int {
								if ( $this->status === 'queued' ) {
									return 1;
								}
								return $this->activeQuery ? 1 : 0;
							}
						};
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

	public function test_clean_queue_does_not_fail_stale_queued_scan_while_active_scan_exists() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700005000 ),
			'service_wpdb'    => new class extends Db {
				public function doSql( string $sqlQuery ) {
					unset( $sqlQuery );
					return true;
				}
			},
		] );

		$scanUpdates = [];
		$deletedScanItems = [];
		$this->installCleanQueueController(
			1,
			[
				'queued:created_at' => [ 81 ],
			],
			$scanUpdates,
			$deletedScanItems
		);

		( new CleanQueue() )->execute();

		$this->assertSame( [], $scanUpdates );
		$this->assertSame( [], $deletedScanItems );
	}

	public function test_clean_queue_does_not_fail_stale_built_scan_with_queue_items() :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700006000 ),
			'service_wpdb'    => new class extends Db {
				public function doSql( string $sqlQuery ) {
					unset( $sqlQuery );
					return true;
				}
			},
		] );

		$scanUpdates = [];
		$deletedScanItems = [];
		$this->installCleanQueueController(
			1,
			[
				'built:ready_at'        => [ 91 ],
				'built:last_process_at' => [ 91 ],
			],
			$scanUpdates,
			$deletedScanItems
		);

		( new CleanQueue() )->execute();

		$this->assertSame( [], $scanUpdates );
		$this->assertSame( [], $deletedScanItems );
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

	private function installCleanQueueController(
		int $activeScanCount,
		array $staleIDsByStatusAndColumn,
		array &$scanUpdates,
		array &$deletedScanItems
	) :void {
		$this->installController( [
			'db_con' => (object)[
				'scans' => new class( $activeScanCount, $staleIDsByStatusAndColumn, $scanUpdates ) {
					private object $selector;
					public array $updates;

					public function __construct( int $activeScanCount, array $staleIDsByStatusAndColumn, array &$updates ) {
						$this->updates = &$updates;
						$this->selector = new class( $activeScanCount, $staleIDsByStatusAndColumn ) {
							private int $activeScanCount;
							private array $staleIDsByStatusAndColumn;
							private string $status = '';
							private array $statuses = [];
							private string $olderColumn = 'created_at';

							public function __construct( int $activeScanCount, array $staleIDsByStatusAndColumn ) {
								$this->activeScanCount = $activeScanCount;
								$this->staleIDsByStatusAndColumn = $staleIDsByStatusAndColumn;
							}

							public function reset() :self {
								$this->status = '';
								$this->statuses = [];
								$this->olderColumn = 'created_at';
								return $this;
							}

							public function filterByStatus( string $status ) :self {
								$this->status = $status;
								return $this;
							}

							public function filterByNotFinished() :self {
								return $this;
							}

							public function filterByReady() :self {
								return $this;
							}

							public function addWhereIn( string $column, array $values ) :self {
								if ( $column === 'status' ) {
									$this->statuses = $values;
								}
								return $this;
							}

							public function addWhereOlderThan( int $timestamp, string $column = 'created_at' ) :self {
								unset( $timestamp );
								$this->olderColumn = $column;
								return $this;
							}

							public function count() :int {
								return $this->statuses === [ 'building', 'built', 'running' ] ? $this->activeScanCount : 0;
							}

							public function getDistinctForColumn( string $column ) :array {
								unset( $column );
								return $this->staleIDsByStatusAndColumn[ $this->status.':'.$this->olderColumn ] ?? [];
							}

							public function queryWithResult() :array {
								return \array_map(
									static fn( int $scanID ) :object => (object)[ 'id' => $scanID ],
									$this->staleIDsByStatusAndColumn[ $this->status.':'.$this->olderColumn ] ?? []
								);
							}

							public function byId( int $scanID ) :object {
								return new class( $scanID ) {
									public int $id;
									public array $meta = [];

									public function __construct( int $scanID ) {
										$this->id = $scanID;
									}

									public function __get( string $key ) {
										return $this->{$key} ?? null;
									}

									public function __set( string $key, $value ) :void {
										$this->{$key} = $value;
									}

									public function getRawData() :array {
										return [
											'id'   => $this->id,
											'meta' => base64_encode( wp_json_encode( $this->meta ) ?: '{}' ),
										];
									}
								};
							}
						};
					}

					public function getQuerySelector() :object {
						return $this->selector;
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

					public function getTable() :string {
						return 'shield_scan_items';
					}

					public function getQuerySelector() :object {
						return new class {
							public function filterByScan( int $scanID ) :self {
								unset( $scanID );
								return $this;
							}

							public function filterByNotFinished() :self {
								return $this;
							}

							public function count() :int {
								return 1;
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
			],
		] );
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
