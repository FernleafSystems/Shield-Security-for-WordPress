<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Malware\Ops\Record as MalwareRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultsSet as AfsResultsSet;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class RetrieveItemsFindingsQueryTest extends BaseUnitTest {

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

	public function test_retrieve_latest_for_findings_omits_state_exists_filter_when_none_are_requested() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		$results = ( new RetrieveItems() )
			->setScanController( $this->newScanController() )
			->retrieveLatestForFindings();

		$this->assertInstanceOf( ResultsSet::class, $results );
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( "`ri`.`scan`='wpv'", $queries[ 0 ] );
		$this->assertStringContainsString( "`ri`.`resolved_at`=0", $queries[ 0 ] );
		$this->assertStringContainsString( 'ORDER BY `ri`.`id` ASC', $queries[ 0 ] );
		$this->assertStringNotContainsString( '`sr`.`id`', $queries[ 0 ] );
		$this->assertStringNotContainsString( 'EXISTS (SELECT 1', $queries[ 0 ] );
	}

	public function test_retrieve_latest_for_findings_builds_exists_filters_for_unique_requested_states() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		( new RetrieveItems() )
			->setScanController( $this->newScanController() )
			->retrieveLatestForFindings( [ 'is_vulnerable', 'is_abandoned', 'is_vulnerable', '', 'is-bad' ] );

		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'EXISTS (SELECT 1', $queries[ 0 ] );
		$this->assertSame( 1, \substr_count( $queries[ 0 ], "meta_key`='is_vulnerable'" ) );
		$this->assertSame( 1, \substr_count( $queries[ 0 ], "meta_key`='is_abandoned'" ) );
		$this->assertSame( 1, \substr_count( $queries[ 0 ], "meta_key`='isbad'" ) );
	}

	public function test_retrieve_for_results_tables_preserves_existing_wheres_without_accumulating_duplicates() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		$retriever = ( new RetrieveItems() )
			->setScanController( $this->newAfsScanController() )
			->addWheres( [
				"`rim`.`meta_key`='ptg_slug'",
				"`rim`.`meta_value`='shield/shield.php'",
			] );

		$retriever->retrieveForResultsTables();
		$retriever->retrieveForResultsTables();

		$this->assertCount( 2, $queries );
		foreach ( $queries as $query ) {
			$this->assertStringContainsString( "`ri`.`scan`='afs'", $query );
			$this->assertStringContainsString( "`ri`.`auto_filtered_at`=0", $query );
			$this->assertStringContainsString( "`ri`.`ignored_at`=0", $query );
			$this->assertStringContainsString( "`ri`.`resolution_reason`!='clean_rescan'", $query );
			$this->assertStringContainsString( "`ri`.`resolution_reason`!='asset_replaced'", $query );
			$this->assertStringContainsString( "`rim`.`meta_key`='ptg_slug'", $query );
			$this->assertStringContainsString( "`rim`.`meta_value`='shield/shield.php'", $query );
			$this->assertSame( 1, \substr_count( $query, "`rim`.`meta_key`='ptg_slug'" ) );
			$this->assertSame( 1, \substr_count( $query, "`rim`.`meta_value`='shield/shield.php'" ) );
		}
	}

	public function test_convert_to_results_set_without_working_scan_uses_vo_scan_once_per_result() :void {
		$queries = [];
		$this->installControllerAndDb( $queries );

		$retriever = new class extends RetrieveItems {
			public function convert(array $results) :ResultsSet {
				return $this->convertToResultsSet( $results );
			}
		};

		$results = $retriever->convert( [
			[
				'scan' => 'wpv',
				'scan_created_at' => 0,
				'scan_id' => 0,
				'resultitem_id' => 101,
				'item_type' => 'p',
				'item_id' => 'example/example.php',
				'asset_type' => 'plugin',
				'asset_key' => 'example/example.php',
				'ignored_at' => 0,
				'notified_at' => 0,
				'attempt_repair_at' => 0,
				'last_seen_at' => 100,
				'resolved_at' => 0,
				'resolution_reason' => '',
				'created_at' => 50,
			],
		] );

		$this->assertInstanceOf( ResultsSet::class, $results );
		$this->assertCount( 1, $results->getAllItems() );
		$this->assertSame( 'wpv', $results->getAllItems()[ 0 ]->VO->scan );
		$this->assertSame( 101, $results->getAllItems()[ 0 ]->VO->resultitem_id );
	}

	public function test_conversion_batches_metadata_and_unique_malware_hydration_at_two_hundred() :void {
		$metaBatches = [];
		$malwareBatches = [];
		$metas = [];
		$records = [];
		$rows = [];
		for ( $id = 1; $id <= 201; $id++ ) {
			$metas[] = (object)[ 'ri_ref' => $id, 'meta_key' => 'malware_record_id', 'meta_value' => $id ];
			$metas[] = (object)[ 'ri_ref' => $id, 'meta_key' => 'ptg_slug', 'meta_value' => 'fixture-'.$id ];
			$records[ $id ] = new MalwareRecord( [ 'id' => $id ] );
			$rows[] = $this->afsRawResult( $id );
		}
		$this->installAfsHydrationController( $metas, $records, $metaBatches, $malwareBatches );
		$retriever = new class extends RetrieveItems {
			public function convert( array $results ) :ResultsSet {
				return $this->convertToResultsSet( $results );
			}
		};

		$items = $retriever->convert( $rows )->getAllItems();

		$this->assertSame( [ 200, 1 ], \array_map( 'count', $metaBatches ) );
		$this->assertSame( [ 200, 1 ], \array_map( 'count', $malwareBatches ) );
		$this->assertCount( 201, $items );
		$this->assertSame( 'fixture-200', $items[ 199 ]->ptg_slug );
		$this->assertSame( 'fixture-201', $items[ 200 ]->ptg_slug );
		$this->assertSame( 201, $items[ 200 ]->getMalwareRecord()->id );
	}

	public function test_hydration_loads_duplicate_ids_once_and_memoizes_missing_records() :void {
		$metaBatches = [];
		$malwareBatches = [];
		$metas = [
			(object)[ 'ri_ref' => 1, 'meta_key' => 'malware_record_id', 'meta_value' => 7 ],
			(object)[ 'ri_ref' => 2, 'meta_key' => 'malware_record_id', 'meta_value' => 7 ],
			(object)[ 'ri_ref' => 3, 'meta_key' => 'malware_record_id', 'meta_value' => 99 ],
		];
		$this->installAfsHydrationController(
			$metas,
			[ 7 => new MalwareRecord( [ 'id' => 7 ] ) ],
			$metaBatches,
			$malwareBatches
		);
		$retriever = new class extends RetrieveItems {
			public function convert( array $results ) :ResultsSet {
				return $this->convertToResultsSet( $results );
			}
		};

		$items = $retriever->convert( [
			$this->afsRawResult( 1 ),
			$this->afsRawResult( 2 ),
			$this->afsRawResult( 3 ),
		] )->getAllItems();

		$this->assertSame( [ [ 7, 99 ] ], $malwareBatches );
		$this->assertSame( $items[ 0 ]->getMalwareRecord(), $items[ 1 ]->getMalwareRecord() );
		$this->assertNull( $items[ 2 ]->getMalwareRecord() );
		$this->assertNull( $items[ 2 ]->getMalwareRecord() );
		$this->assertCount( 1, $malwareBatches );
	}

	private function afsRawResult( int $id ) :array {
		return [
			'scan' => 'afs',
			'scan_created_at' => 0,
			'scan_id' => 0,
			'resultitem_id' => $id,
			'item_type' => 'x',
			'item_id' => 'item-'.$id,
			'asset_type' => 'plugin',
			'asset_key' => 'fixture/fixture.php',
			'ignored_at' => 0,
			'notified_at' => 0,
			'attempt_repair_at' => 0,
			'last_seen_at' => 100,
			'resolved_at' => 0,
			'resolution_reason' => '',
			'created_at' => 50,
		];
	}

	private function installAfsHydrationController(
		array $metas,
		array $records,
		array &$metaBatches,
		array &$malwareBatches
	) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scan_result_item_meta' => new class( $metas, $metaBatches ) {
				private array $metas;
				private array $batches;

				public function __construct( array $metas, array &$batches ) {
					$this->metas = $metas;
					$this->batches = &$batches;
				}

				public function getQuerySelector() {
					return new class( $this->metas, $this->batches ) {
						private array $metas;
						private array $batches;
						private array $ids = [];

						public function __construct( array $metas, array &$batches ) {
							$this->metas = $metas;
							$this->batches = &$batches;
						}

						public function filterByResultItems( array $ids ) {
							$this->ids = $ids;
							$this->batches[] = $ids;
							return $this;
						}

						public function queryWithResult() :array {
							return \array_values( \array_filter(
								$this->metas,
								fn( $meta ) :bool => \in_array( $meta->ri_ref, $this->ids, true )
							) );
						}
					};
				}
			},
			'malware' => new class( $records, $malwareBatches ) {
				private array $records;
				private array $batches;

				public function __construct( array $records, array &$batches ) {
					$this->records = $records;
					$this->batches = &$batches;
				}

				public function getQuerySelector() {
					return new class( $this->records, $this->batches ) {
						private array $records;
						private array $batches;
						private array $ids = [];

						public function __construct( array $records, array &$batches ) {
							$this->records = $records;
							$this->batches = &$batches;
						}

						public function addWhereIn( string $column, array $ids ) {
							unset( $column );
							$this->ids = $ids;
							$this->batches[] = $ids;
							return $this;
						}

						public function queryWithResult() :array {
							return \array_values( \array_intersect_key( $this->records, \array_flip( $this->ids ) ) );
						}
					};
				}
			},
		];
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $slug ) {
					return $slug === 'afs' ? new class {
						public function getNewResultItem() :\FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem {
							return new \FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem();
						}
					} : null;
				}
			},
		];
		PluginControllerInstaller::install( $controller );
	}

	private function installControllerAndDb( array &$queries ) :void {
		ServicesState::installItems( [
			'service_wpdb' => new class( $queries ) extends Db {
				public array $queries = [];

				public function __construct( array &$queries ) {
					$this->queries = &$queries;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $format );
					$this->queries[] = $query;
					return [];
				}
			},
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class {
				public function getQuerySelector() {
					return new class {
						public function getLatestForScan( string $scanSlug ) {
							unset( $scanSlug );
							return (object)[ 'id' => 55 ];
						}
					};
				}

				public function getTable() :string {
					return 'shield_scans';
				}
			},
			'scan_results' => new class {
				public function getTable() :string {
					return 'shield_scan_results';
				}
			},
			'scan_result_items' => new class {
				public function getTable() :string {
					return 'shield_scan_result_items';
				}
			},
			'scan_result_item_meta' => new class {
				public function getTable() :string {
					return 'shield_scan_result_item_meta';
				}

				public function getQuerySelector() {
					return new class {
						public function filterByResultItems( array $resultItemIDs ) {
							unset( $resultItemIDs );
							return $this;
						}

						public function queryWithResult() :array {
							return [];
						}
					};
				}
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) :array {
				unset( $key );
				return [];
			}
		};
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $slug ) {
					if ( $slug !== 'wpv' ) {
						return null;
					}

					return new class {
						public function getNewResultItem() :ResultItem {
							return new ResultItem();
						}
					};
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function newScanController() {
		return new class {
			public function getSlug() :string {
				return 'wpv';
			}

			public function getNewResultsSet() :ResultsSet {
				return new ResultsSet();
			}
		};
	}

	private function newAfsScanController() {
		return new class {
			public function getSlug() :string {
				return 'afs';
			}

			public function getNewResultsSet() :AfsResultsSet {
				return new AfsResultsSet();
			}
		};
	}
}
