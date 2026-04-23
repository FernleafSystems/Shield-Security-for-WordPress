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
		unset( $message );
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	CompleteQueue,
	ProcessQueueItem,
	QueueItemVO,
	RunState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class QueueRuntimeBehaviorTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
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

	public function test_process_queue_item_marks_scan_failed_when_scan_execution_throws() :void {
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
		$this->assertCount( 2, $scanUpdates );
		$this->assertSame( 'running', $scanUpdates[ 0 ][ 'data' ][ 'status' ] ?? null );
		$this->assertSame( 'failed', $scanUpdates[ 1 ][ 'data' ][ 'status' ] ?? null );
		$this->assertSame( 1700002000, $scanUpdates[ 1 ][ 'data' ][ 'finished_at' ] ?? null );
		$this->assertNotSame( '', (string)( $scanUpdates[ 1 ][ 'data' ][ 'meta' ] ?? '' ) );
		$this->assertSame( [ 99 ], $deletedScanItems );
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

	private function installController( array $properties ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		foreach ( $properties as $property => $value ) {
			$controller->{$property} = $value;
		}
		PluginControllerInstaller::install( $controller );
	}
}
