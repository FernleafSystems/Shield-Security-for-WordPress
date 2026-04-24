<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Init;

use Brain\Monkey\Functions;
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
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_run_marks_scan_built_after_persisting_queue_items() :void {
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
		$this->assertCount( 1, $scanUpdates );
		$this->assertSame( 'built', $scanUpdates[ 0 ][ 'data' ][ 'status' ] ?? null );
		$this->assertSame( 1700004000, $scanUpdates[ 0 ][ 'data' ][ 'ready_at' ] ?? null );
		$this->assertSame( 1700004000, $scanUpdates[ 0 ][ 'data' ][ 'last_process_at' ] ?? null );
		$this->assertSame(
			[ 'scan_meta' => 'value' ],
			\json_decode( \base64_decode( (string)$scanUpdates[ 0 ][ 'data' ][ 'meta' ] ), true )
		);
	}

	public function test_run_completes_empty_scan_with_metadata_in_completion_update() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$wpdb = new class extends \FernleafSystems\Wordpress\Services\Core\Db {
			public array $doSqlQueries = [];

			public function doSql( string $sqlQuery ) {
				$this->doSqlQueries[] = $sqlQuery;
				return 1;
			}

			public function selectCustom( $query, $format = null ) {
				unset( $query, $format );
				return [];
			}
		};
		ServicesState::mergeItems( [
			'service_wpdb' => $wpdb,
		] );
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
		$this->assertNotEmpty( $wpdb->doSqlQueries );
		$this->assertStringContainsString( "`status`='completed'", $wpdb->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( '`meta`=', $wpdb->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( 'NOT EXISTS', $wpdb->doSqlQueries[ 0 ] );
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

		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $this->buildScanController() )
			->run();
	}

	private function buildScanController( array $items = [ 'one', 'two', 'three' ] ) :object {
		return new class( $items ) {
			private array $items;

			public function __construct( array $items ) {
				$this->items = $items;
			}

			public function newScanActionVO() :object {
				return (object)[
					'scope_type' => '',
					'scope_key' => '',
				];
			}

			public function buildScanAction( object $scanActionVO ) :object {
				unset( $scanActionVO );

				return new class( $this->items ) {
					public array $items;

					public function __construct( array $items ) {
						$this->items = $items;
					}

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
					};
				}

				public function getTable() :string {
					return 'shield_scans';
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
