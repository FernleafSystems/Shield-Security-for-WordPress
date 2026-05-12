<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results;

use Brain\Monkey\Functions;
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
use FernleafSystems\Wordpress\Services\Core\Db;

class StoreTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $value ) :string => \json_encode( $value ) ?: '' );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_store_inserts_new_observation_pair() :void {
		$queueItemUpdates = [];
		$wpdb = $this->installController( [], [], $queueItemUpdates );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$observationInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_results' );
		$this->assertCount( 1, $observationInserts );
		$this->assertStringContainsString( "(`scan_ref`,`resultitem_ref`,`created_at`)", $observationInserts[ 0 ] );
		$this->assertStringContainsString( "('91','77','1700000000')", $observationInserts[ 0 ] );
		$this->assertSame( [], $queueItemUpdates );
	}

	public function test_store_skips_duplicate_observation_pair_for_same_run() :void {
		$queueItemUpdates = [];
		$wpdb = $this->installController( [
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
		], [ 77 ], $queueItemUpdates );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$this->assertSame( [], $this->insertQueriesForTable( $wpdb, 'shield_scan_results' ) );
		$this->assertSame( [], $queueItemUpdates );
	}

	public function test_store_batches_existing_result_and_observation_lookups() :void {
		$queueItemUpdates = [];
		$metaDeletes = [];
		$wpdb = $this->installController( [
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
			$this->existingResultRow( 78, 'hello-dolly/hello.php' ),
		], [], $queueItemUpdates, $metaDeletes );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
			[
				'item_id' => 'hello-dolly/hello.php',
			],
		] );

		$this->assertCount( 2, $wpdb->selectQueries );
		$this->assertStringContainsString( 'shield_scan_result_items', $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( ' OR ', $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( 'shield_scan_results', $wpdb->selectQueries[ 1 ] );
		$this->assertStringContainsString( 'IN (77,78)', $wpdb->selectQueries[ 1 ] );
		$this->assertSame( [ [ 77, 78 ] ], $metaDeletes );
		$metaInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_result_item_meta' );
		$this->assertCount( 1, $metaInserts );
		$this->assertStringContainsString( "('77','ptg_slug','akismet/akismet.php'),('78','ptg_slug','hello-dolly/hello.php')", $metaInserts[ 0 ] );
		$observationInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_results' );
		$this->assertCount( 1, $observationInserts );
		$this->assertStringContainsString( "('91','77','1700000000'),('91','78','1700000000')", $observationInserts[ 0 ] );
		$this->assertSame( [], $queueItemUpdates );
	}

	public function test_store_bulk_meta_insert_encodes_non_scalar_meta_values() :void {
		$queueItemUpdates = [];
		$wpdb = $this->installController( [
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
		], [ 77 ], $queueItemUpdates );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
				'meta'    => [
					'details' => [
						'clean' => true,
					],
				],
			],
		] );

		$metaInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_result_item_meta' );
		$this->assertCount( 1, $metaInserts );
		$this->assertStringContainsString( "('77','details','{\"clean\":true}')", $metaInserts[ 0 ] );
		$this->assertSame( [], $this->insertQueriesForTable( $wpdb, 'shield_scan_results' ) );
		$this->assertSame( [], $queueItemUpdates );
	}

	public function test_store_reuses_blank_legacy_result_item_without_overwriting_history() :void {
		$queueItemUpdates = [];
		$metaDeletes = [];
		$resultItemInserts = [];
		$resultItemUpdates = [];
		$wpdb = $this->installController( [
			$this->legacyBlankResultRow( 77, 'akismet/akismet.php', [
				'ignored_at'        => 1699999800,
				'notified_at'       => 1699999810,
				'attempt_repair_at' => 1699999820,
				'created_at'        => 1699999700,
			] ),
		], [], $queueItemUpdates, $metaDeletes, $resultItemInserts, $resultItemUpdates );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$this->assertSame( [], $resultItemInserts );
		$this->assertCount( 1, $resultItemUpdates );
		$this->assertSame( 77, $resultItemUpdates[ 0 ][ 'id' ] );
		$this->assertSame( [
			'scan'              => 'afs',
			'asset_type'        => 'plugin',
			'asset_key'         => 'akismet/akismet.php',
			'auto_filtered_at'  => 0,
			'last_seen_at'      => 1700000000,
			'resolved_at'       => 0,
			'resolution_reason' => '',
		], $resultItemUpdates[ 0 ][ 'data' ] );
		foreach ( [ 'notified_at', 'ignored_at', 'attempt_repair_at', 'created_at', 'item_repaired_at', 'item_deleted_at' ] as $historyField ) {
			$this->assertArrayNotHasKey( $historyField, $resultItemUpdates[ 0 ][ 'data' ] );
		}
		$observationInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_results' );
		$this->assertCount( 1, $observationInserts );
		$this->assertStringContainsString( "('91','77','1700000000')", $observationInserts[ 0 ] );
		$this->assertSame( [ [ 77 ] ], $metaDeletes );
		$this->assertSame( [], $queueItemUpdates );
	}

	public function test_store_prefers_current_scan_result_item_over_matching_legacy_row() :void {
		$queueItemUpdates = [];
		$metaDeletes = [];
		$resultItemInserts = [];
		$resultItemUpdates = [];
		$wpdb = $this->installController( [
			$this->legacyBlankResultRow( 88, 'akismet/akismet.php' ),
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
		], [], $queueItemUpdates, $metaDeletes, $resultItemInserts, $resultItemUpdates );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$this->assertSame( [], $resultItemInserts );
		$this->assertCount( 1, $resultItemUpdates );
		$this->assertSame( 77, $resultItemUpdates[ 0 ][ 'id' ] );
		$observationInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_results' );
		$this->assertCount( 1, $observationInserts );
		$this->assertStringContainsString( "('91','77','1700000000')", $observationInserts[ 0 ] );
		$this->assertSame( [ [ 77 ] ], $metaDeletes );
		$this->assertSame( [], $queueItemUpdates );
	}

	public function test_existing_result_lookup_limits_legacy_candidates_to_unresolved_blank_rows() :void {
		$queueItemUpdates = [];
		$metaDeletes = [];
		$wpdb = $this->installController( [], [], $queueItemUpdates, $metaDeletes );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$this->assertStringContainsString( "`resolved_at`=0", $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( "`scan`='afs'", $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( "`scan`=''", $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( "`asset_type`=''", $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( "`asset_key`=''", $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( "`item_repaired_at`=0", $wpdb->selectQueries[ 0 ] );
		$this->assertStringContainsString( "`item_deleted_at`=0", $wpdb->selectQueries[ 0 ] );
		$this->assertSame( [], $queueItemUpdates );
	}

	private function insertQueriesForTable( object $wpdb, string $table ) :array {
		return \array_values( \array_filter(
			$wpdb->insertQueries,
			static fn( string $query ) :bool => \strpos( $query, '`'.$table.'`' ) !== false
		) );
	}

	private function installController(
		array $existingResultRows,
		array $observedResultItemIDs,
		array &$queueItemUpdates,
		array &$metaDeletes = [],
		array &$resultItemInserts = [],
		array &$resultItemUpdates = []
	) :object {
		$wpdb = new class( $existingResultRows, $observedResultItemIDs ) extends Db {
			public array $selectQueries = [];
			public array $insertQueries = [];
			private array $existingResultRows;
			private array $observedResultItemIDs;

			public function __construct( array $existingResultRows, array $observedResultItemIDs ) {
				$this->existingResultRows = $existingResultRows;
				$this->observedResultItemIDs = $observedResultItemIDs;
			}

			public function selectCustom( $query, $format = null ) {
				unset( $format );
				$this->selectQueries[] = (string)$query;
				if ( \strpos( (string)$query, 'shield_scan_result_items' ) !== false ) {
					return $this->existingResultRows;
				}
				if ( \strpos( (string)$query, 'shield_scan_results' ) !== false ) {
					return \array_map(
						static fn( int $resultItemID ) :array => [ 'resultitem_ref' => $resultItemID ],
						$this->observedResultItemIDs
					);
				}
				return [];
			}

			public function getVar( $sql ) {
				unset( $sql );
				return 77;
			}

			public function doSql( string $sqlQuery ) {
				$this->insertQueries[] = $sqlQuery;
				return 1;
			}
		};
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpdb'    => $wpdb,
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $scan ) :object {
					unset( $scan );
					return new class {
						public function buildScanResult( array $result ) :ResultItemRecord {
							$record = new ResultItemRecord();
							$record->scan = 'afs';
							$record->item_type = 'f';
							$record->item_id = $result[ 'item_id' ];
							$record->asset_type = 'plugin';
							$record->asset_key = $result[ 'item_id' ];
							$record->auto_filtered_at = 0;
							$record->last_seen_at = 1700000000;
							$record->resolved_at = 0;
							$record->resolution_reason = '';
							$record->meta = \array_merge( [
								'ptg_slug' => $result[ 'item_id' ],
							], $result[ 'meta' ] ?? [] );
							return $record;
						}
					};
				}
			},
		];
		$controller->db_con = (object)[
			'scan_result_items' => new class( $resultItemInserts, $resultItemUpdates ) {
				private array $resultItemInserts;
				private array $resultItemUpdates;

				public function __construct( array &$resultItemInserts, array &$resultItemUpdates ) {
					$this->resultItemInserts = &$resultItemInserts;
					$this->resultItemUpdates = &$resultItemUpdates;
				}

				public function getTable() :string {
					return 'shield_scan_result_items';
				}

				public function getQueryInserter() :object {
					return new class( $this->resultItemInserts ) {
						private array $resultItemInserts;

						public function __construct( array &$resultItemInserts ) {
							$this->resultItemInserts = &$resultItemInserts;
						}

						public function insert( ResultItemRecord $record ) :bool {
							$this->resultItemInserts[] = [
								'scan'              => $record->scan,
								'item_type'         => $record->item_type,
								'item_id'           => $record->item_id,
								'asset_type'        => $record->asset_type,
								'asset_key'         => $record->asset_key,
								'auto_filtered_at'  => $record->auto_filtered_at,
								'last_seen_at'      => $record->last_seen_at,
								'resolved_at'       => $record->resolved_at,
								'resolution_reason' => $record->resolution_reason,
							];
							return true;
						}
					};
				}

				public function getQueryUpdater() :object {
					return new class( $this->resultItemUpdates ) {
						private array $resultItemUpdates;

						public function __construct( array &$resultItemUpdates ) {
							$this->resultItemUpdates = &$resultItemUpdates;
						}

						public function updateRecord( ResultItemRecord $record, array $data ) :bool {
							$this->resultItemUpdates[] = [
								'id'   => (int)$record->id,
								'data' => $data,
							];
							return true;
						}
					};
				}
			},
			'scan_result_item_meta' => new class( $metaDeletes ) {
				public array $metaDeletes;

				public function __construct( array &$metaDeletes ) {
					$this->metaDeletes = &$metaDeletes;
				}

				public function getTable() :string {
					return 'shield_scan_result_item_meta';
				}

				public function getQueryDeleter() :object {
					return new class( $this->metaDeletes ) {
						public array $metaDeletes;
						private array $ids = [];

						public function __construct( array &$metaDeletes ) {
							$this->metaDeletes = &$metaDeletes;
						}

						public function filterByResultItems( array $resultItemIDs ) :self {
							$this->ids = \array_values( $resultItemIDs );
							return $this;
						}

						public function query() :bool {
							$this->metaDeletes[] = $this->ids;
							return true;
						}
					};
				}

			},
			'scan_results' => new class {
				public function getTable() :string {
					return 'shield_scan_results';
				}
			},
			'scan_items' => new class( $queueItemUpdates ) {
				public array $queueItemUpdates;

				public function __construct( array &$queueItemUpdates ) {
					$this->queueItemUpdates = &$queueItemUpdates;
				}

				public function getQueryUpdater() :object {
					return new class( $this->queueItemUpdates ) {
						public array $queueItemUpdates;

						public function __construct( array &$queueItemUpdates ) {
							$this->queueItemUpdates = &$queueItemUpdates;
						}

						public function updateById( int $queueItemID, array $data ) :bool {
							$this->queueItemUpdates[] = [
								'id'   => $queueItemID,
								'data' => $data,
							];
							return true;
						}
					};
				}
			},
		];

		PluginControllerInstaller::install( $controller );
		return $wpdb;
	}

	private function existingResultRow( int $id, string $itemID ) :array {
		return [
			'id'                => $id,
			'scan'              => 'afs',
			'item_type'         => 'f',
			'item_id'           => $itemID,
			'asset_type'        => 'plugin',
			'asset_key'         => $itemID,
			'auto_filtered_at'  => 0,
			'last_seen_at'      => 1699999900,
			'resolved_at'       => 0,
			'resolution_reason' => '',
			'item_repaired_at'  => 0,
			'item_deleted_at'   => 0,
		];
	}

	private function legacyBlankResultRow( int $id, string $itemID, array $overrides = [] ) :array {
		return \array_merge( $this->existingResultRow( $id, $itemID ), [
			'scan'       => '',
			'asset_type' => '',
			'asset_key'  => '',
		], $overrides );
	}

	private function newQueueItem() :QueueItemVO {
		$queueItem = new QueueItemVO();
		$queueItem->scan_id = 91;
		$queueItem->qitem_id = 14;
		$queueItem->scan = 'afs';
		return $queueItem;
	}
}
