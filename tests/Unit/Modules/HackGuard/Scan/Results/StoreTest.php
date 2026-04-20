<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Record as ResultItemRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Store;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class StoreTest extends BaseUnitTest {

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

	public function test_store_inserts_new_observation_pair() :void {
		$insertedObservationRows = [];
		$this->installController( false, $insertedObservationRows );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$this->assertCount( 1, $insertedObservationRows );
		$this->assertSame( [
			'scan_ref'       => 91,
			'resultitem_ref' => 77,
		], $insertedObservationRows[ 0 ] );
	}

	public function test_store_skips_duplicate_observation_pair_for_same_run() :void {
		$insertedObservationRows = [];
		$this->installController( true, $insertedObservationRows );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$this->assertSame( [], $insertedObservationRows );
	}

	private function installController( bool $observationExists, array &$insertedObservationRows ) :void {
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
		] );

		$resultRecord = new ResultItemRecord();
		$resultRecord->id = 77;
		$resultRecord->scan = 'afs';
		$resultRecord->item_type = 'f';
		$resultRecord->item_id = 'wp-content/plugins/akismet/akismet.php';
		$resultRecord->asset_type = 'plugin';
		$resultRecord->asset_key = 'akismet/akismet.php';
		$resultRecord->meta = [];

		$scanResultRecord = new ResultItemRecord();
		$scanResultRecord->scan = 'afs';
		$scanResultRecord->item_type = 'f';
		$scanResultRecord->item_id = 'wp-content/plugins/akismet/akismet.php';
		$scanResultRecord->asset_type = 'plugin';
		$scanResultRecord->asset_key = 'akismet/akismet.php';
		$scanResultRecord->auto_filtered_at = 0;
		$scanResultRecord->last_seen_at = 1700000000;
		$scanResultRecord->resolved_at = 0;
		$scanResultRecord->resolution_reason = '';
		$scanResultRecord->meta = [
			'ptg_slug' => 'akismet/akismet.php',
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'scans' => new class( $scanResultRecord ) {
				private ResultItemRecord $scanResultRecord;

				public function __construct( ResultItemRecord $scanResultRecord ) {
					$this->scanResultRecord = $scanResultRecord;
				}

				public function getScanCon( string $scan ) :object {
					unset( $scan );
					return new class( $this->scanResultRecord ) {
						private ResultItemRecord $scanResultRecord;

						public function __construct( ResultItemRecord $scanResultRecord ) {
							$this->scanResultRecord = $scanResultRecord;
						}

						public function buildScanResult( array $result ) :ResultItemRecord {
							unset( $result );
							return clone $this->scanResultRecord;
						}
					};
				}
			},
		];
		$controller->db_con = (object)[
			'scan_result_items' => new class( $resultRecord ) {
				private ResultItemRecord $resultRecord;

				public function __construct( ResultItemRecord $resultRecord ) {
					$this->resultRecord = $resultRecord;
				}

				public function getQuerySelector() :object {
					return new class( $this->resultRecord ) {
						private ResultItemRecord $resultRecord;

						public function __construct( ResultItemRecord $resultRecord ) {
							$this->resultRecord = $resultRecord;
						}

						public function filterByScan( string $scan ) :self {
							unset( $scan );
							return $this;
						}

						public function filterByItemType( string $itemType ) :self {
							unset( $itemType );
							return $this;
						}

						public function filterByItemID( string $itemID ) :self {
							unset( $itemID );
							return $this;
						}

						public function filterByUnresolved() :self {
							return $this;
						}

						public function first() :ResultItemRecord {
							return $this->resultRecord;
						}
					};
				}

				public function getQueryUpdater() :object {
					return new class {
						public function updateRecord( ResultItemRecord $record, array $data ) :bool {
							unset( $record, $data );
							return true;
						}
					};
				}
			},
			'scan_result_item_meta' => new class {
				public function getQueryDeleter() :object {
					return new class {
						public function filterByResultItemRef( int $resultItemID ) :self {
							unset( $resultItemID );
							return $this;
						}

						public function query() :bool {
							return true;
						}
					};
				}

				public function getQueryInserter() :object {
					return new class {
						public function setInsertData( array $data ) :self {
							unset( $data );
							return $this;
						}

						public function query() :bool {
							return true;
						}
					};
				}
			},
			'scan_results' => new class( $observationExists, $insertedObservationRows ) {
				private bool $observationExists;
				private array $insertedObservationRows;

				public function __construct( bool $observationExists, array &$insertedObservationRows ) {
					$this->observationExists = $observationExists;
					$this->insertedObservationRows = &$insertedObservationRows;
				}

				public function getQuerySelector() :object {
					return new class( $this->observationExists ) {
						private bool $observationExists;

						public function __construct( bool $observationExists ) {
							$this->observationExists = $observationExists;
						}

						public function filterByScan( int $scanID ) :self {
							unset( $scanID );
							return $this;
						}

						public function filterByResultItem( int $resultItemID ) :self {
							unset( $resultItemID );
							return $this;
						}

						public function count() :int {
							return $this->observationExists ? 1 : 0;
						}
					};
				}

				public function getQueryInserter() :object {
					return new class( $this->insertedObservationRows ) {
						private array $insertedObservationRows;
						private array $pending = [];

						public function __construct( array &$insertedObservationRows ) {
							$this->insertedObservationRows = &$insertedObservationRows;
						}

						public function setInsertData( array $data ) :self {
							$this->pending = $data;
							return $this;
						}

						public function query() :bool {
							$this->insertedObservationRows[] = $this->pending;
							return true;
						}
					};
				}
			},
			'scan_items' => new class {
				public function getQueryUpdater() :object {
					return new class {
						public function updateById( int $queueItemID, array $data ) :bool {
							unset( $queueItemID, $data );
							return true;
						}
					};
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function newQueueItem() :QueueItemVO {
		$queueItem = new QueueItemVO();
		$queueItem->scan_id = 91;
		$queueItem->qitem_id = 14;
		$queueItem->scan = 'afs';
		return $queueItem;
	}
}
