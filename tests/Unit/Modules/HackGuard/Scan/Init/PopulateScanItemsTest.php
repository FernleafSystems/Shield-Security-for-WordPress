<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class PopulateScanItemsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700004000 ),
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_run_sets_ready_at_after_persisting_queue_items_without_marking_running() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$this->installController( $scanUpdates, $itemInsertCount, true );

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 17;
		$scanRecord->scan = 'afs';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $this->buildScanController() )
			->run();

		$this->assertSame( 2, $itemInsertCount );
		$this->assertCount( 2, $scanUpdates );
		$this->assertArrayHasKey( 'meta', $scanUpdates[ 0 ][ 'data' ] );
		$this->assertSame( 1700004000, $scanUpdates[ 1 ][ 'data' ][ 'ready_at' ] ?? null );
		$this->assertArrayNotHasKey( 'status', $scanUpdates[ 1 ][ 'data' ] );
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

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to persist queue items for scan "afs".' );

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $this->buildScanController() )
			->run();
	}

	private function buildScanController() :object {
		return new class {
			public function newScanActionVO() :object {
				return (object)[
					'scope_type' => '',
					'scope_key' => '',
				];
			}

			public function buildScanAction( object $scanActionVO ) :object {
				unset( $scanActionVO );

				return new class {
					public array $items = [ 'one', 'two', 'three' ];

					public function getRawData() :array {
						return [ 'scan_meta' => 'value' ];
					}
				};
			}

			public function getQueueGroupSize() :int {
				return 2;
			}
		};
	}

	private function installController( array &$scanUpdates, int &$itemInsertCount, bool $itemInsertSuccess ) :void {
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

						public function updateRecord( object $record, array $data ) :bool {
							$this->updates[] = [ 'scan_id' => $record->id, 'data' => $data ];
							return true;
						}
					};
				}
			},
			'scan_items' => new class( $itemInsertCount, $itemInsertSuccess ) {
				public int $insertCount;
				private bool $insertSuccess;

				public function __construct( int &$insertCount, bool $insertSuccess ) {
					$this->insertCount = &$insertCount;
					$this->insertSuccess = $insertSuccess;
				}

				public function getRecord() :object {
					return new class {
						public int $scan_ref = 0;
						public array $items = [];
					};
				}

				public function getQueryInserter() :object {
					return new class( $this->insertCount, $this->insertSuccess ) {
						public int $insertCount;
						private bool $insertSuccess;

						public function __construct( int &$insertCount, bool $insertSuccess ) {
							$this->insertCount = &$insertCount;
							$this->insertSuccess = $insertSuccess;
						}

						public function insert( object $record ) :bool {
							unset( $record );
							$this->insertCount++;
							return $this->insertSuccess;
						}
					};
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
