<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Init;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueHeartbeat;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Db;

class PopulateScanItemsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700004000 ),
		] );
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
		QueueHeartbeat::resetRuntimeCache();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_run_marks_scan_built_after_persisting_queue_items() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$itemInserts = [];
		$heartbeatQueries = [];
		$this->installHeartbeatDb( $heartbeatQueries );
		$this->installController( $scanUpdates, $itemInsertCount, true, $itemInserts );

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 17;
		$scanRecord->scan = 'afs';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';
		$scanController = $this->buildScanController();

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $scanController )
			->run();

		$this->assertSame( [
			[
				'items'      => [ 'one', 'two' ],
				'item_count' => 2,
			],
			[
				'items'      => [ 'three' ],
				'item_count' => 1,
			],
		], $itemInserts );
		$this->assertSame( 2, $scanController->lastAction->progressTicks );
		$this->assertNotEmpty( $heartbeatQueries );
		$this->assertCount( 1, $scanUpdates );
		$this->assertSame( 'built', $scanUpdates[ 0 ][ 'data' ][ 'status' ] );
		$this->assertSame( 1700004000, $scanUpdates[ 0 ][ 'data' ][ 'ready_at' ] );
		$this->assertSame( 1700004000, $scanUpdates[ 0 ][ 'data' ][ 'last_process_at' ] );
		$this->assertSame(
			[ 'scan_meta' => 'value' ],
			\json_decode( \base64_decode( (string)$scanUpdates[ 0 ][ 'data' ][ 'meta' ] ), true )
		);
	}

	public function test_run_attaches_progress_callback_before_building_scan_action() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$heartbeatQueries = [];
		$callbackAttachedBeforeBuild = false;
		$heartbeatFiredDuringBuild = false;
		$this->installHeartbeatDb( $heartbeatQueries );
		$this->installController( $scanUpdates, $itemInsertCount, true );

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 19;
		$scanRecord->scan = 'afs';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';
		$scanController = $this->buildScanController(
			[ 'one' ],
			static function ( BaseScanActionVO $scanActionVO ) use (
				&$callbackAttachedBeforeBuild,
				&$heartbeatFiredDuringBuild,
				&$heartbeatQueries
			) :void {
				$callbackAttachedBeforeBuild = \is_callable( $scanActionVO->progress_callback );
				$scanActionVO->tickProgress();
				$heartbeatFiredDuringBuild = \count( $heartbeatQueries ) === 1;
			}
		);

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $scanController )
			->run();

		$this->assertTrue( $callbackAttachedBeforeBuild );
		$this->assertTrue( $heartbeatFiredDuringBuild );
		$this->assertCount( 1, $heartbeatQueries );
		$this->assertStringContainsString( 'UPDATE `shield_scans`', $heartbeatQueries[ 0 ] );
		$this->assertStringContainsString( '`id`=19', $heartbeatQueries[ 0 ] );
		$this->assertStringContainsString( "`status`='building'", $heartbeatQueries[ 0 ] );
	}

	public function test_run_completes_empty_scan_with_metadata_in_completion_update() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$wpdbQueries = [];
		$this->installHeartbeatDb( $wpdbQueries );
		$this->installController( $scanUpdates, $itemInsertCount, true );

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 18;
		$scanRecord->scan = 'wpv';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';
		$scanRecord->run_trigger = 'manual';

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $this->buildScanController( [] ) )
			->run();

		$this->assertSame( 0, $itemInsertCount );
		$this->assertSame( [], $scanUpdates );
		$this->assertNotEmpty( $wpdbQueries );
		$this->assertStringContainsString( "`status`='completed'", $wpdbQueries[ 0 ] );
		$this->assertStringContainsString( '`meta`=', $wpdbQueries[ 0 ] );
		$this->assertStringContainsString( 'NOT EXISTS', $wpdbQueries[ 0 ] );
	}

	public function test_run_throws_when_queue_item_persistence_fails() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$this->installController( $scanUpdates, $itemInsertCount, false );

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 17;
		$scanRecord->scan = 'afs';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';
		$scanController = $this->buildScanController();

		try {
			( new PopulateScanItems() )
				->setRecord( $scanRecord )
				->setScanController( $scanController )
				->run();

			$this->fail( 'Expected queue item persistence failure.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Failed to persist queue items', $e->getMessage() );
		}

		$this->assertSame( 1, $itemInsertCount );
		$this->assertSame( [], $scanUpdates );
		$this->assertSame( 0, $scanController->lastAction->progressTicks );
	}

	private function buildScanController( array $items = [ 'one', 'two', 'three' ], ?callable $onBuild = null ) :object {
		return new class( $items, $onBuild ) {
			private array $items;
			private $onBuild;
			public ?BaseScanActionVO $lastAction = null;

			public function __construct( array $items, ?callable $onBuild ) {
				$this->items = $items;
				$this->onBuild = $onBuild;
			}

			public function newScanActionVO() :BaseScanActionVO {
				return new class extends BaseScanActionVO {
					public string $scope_type = '';
					public string $scope_key = '';
					public array $items = [];
					public int $progressTicks = 0;
					public $progress_callback = null;

					public function tickProgress() :void {
						$this->progressTicks++;
						parent::tickProgress();
					}

					public function getRawData() :array {
						return [ 'scan_meta' => 'value' ];
					}
				};
			}

			public function buildScanAction( BaseScanActionVO $scanActionVO ) :BaseScanActionVO {
				if ( \is_callable( $this->onBuild ) ) {
					( $this->onBuild )( $scanActionVO );
				}
				$scanActionVO->items = $this->items;
				$this->lastAction = $scanActionVO;
				return $scanActionVO;
			}

			public function getQueueGroupSize() :int {
				return 2;
			}
		};
	}

	private function installHeartbeatDb( array &$queries ) :void {
		ServicesState::mergeItems( [
			'service_wpdb' => new class( $queries ) extends Db {
				public array $queries;

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function doSql( $sql ) {
					$this->queries[] = (string)$sql;
					return 1;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $query, $format );
					return [];
				}
			},
		] );
	}

	private function installController( array &$scanUpdates, int &$itemInsertCount, bool $itemInsertSuccess, array &$itemInserts = [] ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
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

				public function getTable() :string {
					return 'shield_scans';
				}
			},
			'scan_items' => new class( $itemInsertCount, $itemInsertSuccess, $itemInserts ) {
				public int $insertCount;
				public array $inserts;
				private bool $insertSuccess;

				public function __construct( int &$insertCount, bool $insertSuccess, array &$inserts ) {
					$this->insertCount = &$insertCount;
					$this->insertSuccess = $insertSuccess;
					$this->inserts = &$inserts;
				}

				public function getRecord() :object {
					return new class {
						public int $scan_ref = 0;
						public array $items = [];
						public int $item_count = 0;
					};
				}

				public function getQueryInserter() :object {
					return new class( $this->insertCount, $this->insertSuccess, $this->inserts ) {
						public int $insertCount;
						public array $inserts;
						private bool $insertSuccess;

						public function __construct( int &$insertCount, bool $insertSuccess, array &$inserts ) {
							$this->insertCount = &$insertCount;
							$this->insertSuccess = $insertSuccess;
							$this->inserts = &$inserts;
						}

						public function insert( object $record ) :bool {
							$this->insertCount++;
							if ( $this->insertSuccess ) {
								$this->inserts[] = [
									'items'      => $record->items,
									'item_count' => $record->item_count,
								];
							}
							return $this->insertSuccess;
						}
					};
				}

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
		];
		$controller->comps = (object)[
			'scans'  => new class {
				public function getScanCon( string $scan ) :object {
					unset( $scan );
					return new class {
						public function getScanName() :string {
							return 'Scan';
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
			'events' => new class {
				public function fireEvent( string $event, array $meta = [] ) :void {
					unset( $event, $meta );
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
