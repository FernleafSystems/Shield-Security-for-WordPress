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
		$wpdb = $this->installController( [], [] );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$observationInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_results' );
		$this->assertCount( 1, $observationInserts );
		$this->assertStringContainsString( "(`scan_ref`,`resultitem_ref`,`created_at`)", $observationInserts[ 0 ] );
		$this->assertStringContainsString( "('91','77','1700000000')", $observationInserts[ 0 ] );
	}

	public function test_store_skips_duplicate_observation_pair_for_same_run() :void {
		$wpdb = $this->installController( [
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
		], [ 77 ] );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
			],
		] );

		$this->assertSame( [], $this->insertQueriesForTable( $wpdb, 'shield_scan_results' ) );
	}

	public function test_store_batches_existing_result_and_observation_lookups() :void {
		$metaDeletes = [];
		$wpdb = $this->installController( [
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
			$this->existingResultRow( 78, 'hello-dolly/hello.php' ),
		], [], $metaDeletes );

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
	}

	public function test_store_bulk_meta_insert_encodes_non_scalar_meta_values() :void {
		$wpdb = $this->installController( [
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
		], [ 77 ] );

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
	}

	public function test_store_replaces_existing_comparison_basis_metadata() :void {
		$metaDeletes = [];
		$wpdb = $this->installController( [
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
		], [], $metaDeletes );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
				'meta'    => [
					'comparison_basis' => 'local_baseline',
				],
			],
		] );

		$this->assertSame( [ [ 77 ] ], $metaDeletes );
		$metaInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_result_item_meta' );
		$this->assertCount( 1, $metaInserts );
		$this->assertStringContainsString( "('77','comparison_basis','local_baseline')", $metaInserts[ 0 ] );
	}

	public function test_store_reuses_blank_legacy_result_item_without_overwriting_history() :void {
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
		], [], $metaDeletes, $resultItemInserts, $resultItemUpdates );

		( new Store() )->store( $this->newQueueItem(), [
			[
				'item_id' => 'akismet/akismet.php',
				'meta'    => [
					'comparison_basis' => 'published_reference',
				],
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
		$metaInserts = $this->insertQueriesForTable( $wpdb, 'shield_scan_result_item_meta' );
		$this->assertCount( 1, $metaInserts );
		$this->assertStringContainsString( "('77','comparison_basis','published_reference')", $metaInserts[ 0 ] );
	}

	public function test_store_prefers_current_scan_result_item_over_matching_legacy_row() :void {
		$metaDeletes = [];
		$resultItemInserts = [];
		$resultItemUpdates = [];
		$wpdb = $this->installController( [
			$this->legacyBlankResultRow( 88, 'akismet/akismet.php' ),
			$this->existingResultRow( 77, 'akismet/akismet.php' ),
		], [], $metaDeletes, $resultItemInserts, $resultItemUpdates );

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
	}

	public function test_full_afs_ineligible_malware_result_preserves_existing_row_state() :void {
		$metaDeletes = [];
		$resultItemInserts = [];
		$resultItemUpdates = [];
		$this->installController(
			[
				$this->existingResultRow( 77, 'akismet/akismet.php', [
					'asset_key' => 'akismet/akismet.php',
				] ),
			],
			[],
			$metaDeletes,
			$resultItemInserts,
			$resultItemUpdates,
			null,
			77,
			1,
			[
				[
					'ri_ref'     => 77,
					'meta_key'   => 'is_checksumfail',
					'meta_value' => '1',
				],
				[
					'ri_ref'     => 77,
					'meta_key'   => 'asset_version',
					'meta_value' => '5.0',
				],
				[
					'ri_ref'     => 77,
					'meta_key'   => 'comparison_basis',
					'meta_value' => 'published_reference',
				],
			]
		);

		( new Store() )->store( $this->newFullQueueItem(), [
			[
				'item_id'         => 'akismet/akismet.php',
				'auto_filtered_at' => 1700000042,
				'meta'             => [
					'asset_version'    => '5.0',
					'is_mal'           => 1,
					'malware_record_id' => 314,
				],
			],
		] );

		$this->assertSame( [], $resultItemInserts );
		$this->assertSame( [], $metaDeletes );
		$this->assertCount( 1, $resultItemUpdates );
		$this->assertSame( [
			'scan'              => 'afs',
			'asset_type'        => 'plugin',
			'asset_key'         => 'akismet/akismet.php',
			'auto_filtered_at'  => 1700000042,
			'last_seen_at'      => 1700000000,
			'resolved_at'       => 0,
			'resolution_reason' => '',
		], $resultItemUpdates[ 0 ][ 'data' ] );
	}

	public function test_full_afs_incomplete_marker_filters_integrity_from_entire_batch_and_preserves_malware() :void {
		$metaDeletes = [];
		$resultItemInserts = [];
		$this->installController( [], [], $metaDeletes, $resultItemInserts );
		$queueItem = $this->newFullQueueItem();
		$queueItem->meta = \array_merge( $queueItem->meta, [
			'asset_comparison_incomplete' => [
				'plugin' => [ 'akismet/akismet.php' ],
				'theme'  => [],
			],
		] );

		( new Store() )->store( $queueItem, [
			[
				'item_id'          => 'akismet/old.php',
				'is_in_plugin'     => true,
				'ptg_slug'         => 'akismet/akismet.php',
				'asset_version'     => '5.0',
				'is_checksumfail'   => true,
				'comparison_basis' => 'published_reference',
			],
			[
				'item_id'          => 'akismet/malware.php',
				'is_in_plugin'     => true,
				'ptg_slug'         => 'akismet/akismet.php',
				'asset_version'     => '5.0',
				'is_checksumfail'   => true,
				'comparison_basis' => 'published_reference',
				'is_mal'            => true,
				'malware_record_id' => 314,
			],
			[
				'item_id'          => 'other/other.php',
				'is_in_plugin'     => true,
				'ptg_slug'         => 'other/other.php',
				'asset_version'     => '1.0',
				'is_checksumfail'   => true,
				'comparison_basis' => 'local_baseline',
			],
		] );

		$this->assertCount( 2, $resultItemInserts );
		$insertsByID = \array_column( $resultItemInserts, null, 'item_id' );
		$this->assertArrayHasKey( 'akismet/malware.php', $insertsByID );
		$this->assertArrayHasKey( 'other/other.php', $insertsByID );
		$this->assertTrue( $insertsByID[ 'akismet/malware.php' ][ 'meta' ][ 'is_mal' ] ?? false );
		$this->assertSame( 314, $insertsByID[ 'akismet/malware.php' ][ 'meta' ][ 'malware_record_id' ] ?? null );
		$this->assertArrayNotHasKey( 'is_checksumfail', $insertsByID[ 'akismet/malware.php' ][ 'meta' ] );
		$this->assertArrayNotHasKey( 'comparison_basis', $insertsByID[ 'akismet/malware.php' ][ 'meta' ] );
		$this->assertTrue( $insertsByID[ 'other/other.php' ][ 'meta' ][ 'is_checksumfail' ] ?? false );
		$this->assertSame( 'local_baseline', $insertsByID[ 'other/other.php' ][ 'meta' ][ 'comparison_basis' ] ?? null );
	}

	public function test_malformed_incomplete_marker_filters_all_plugin_theme_integrity_fail_closed() :void {
		$metaDeletes = [];
		$resultItemInserts = [];
		$this->installController( [], [], $metaDeletes, $resultItemInserts );
		$queueItem = $this->newFullQueueItem();
		$queueItem->meta = \array_merge( $queueItem->meta, [
			'asset_comparison_incomplete' => [ 'plugin' => [] ],
		] );

		( new Store() )->store( $queueItem, [
			[
				'item_id'        => 'akismet/plugin.php',
				'is_in_plugin'   => true,
				'ptg_slug'       => 'akismet/akismet.php',
				'is_checksumfail' => true,
			],
			[
				'item_id'        => 'theme/style.php',
				'is_in_theme'    => true,
				'ptg_slug'       => 'theme',
				'is_unrecognised' => true,
				'is_mal'          => true,
			],
			[
				'item_id'        => 'wp-admin/core.php',
				'is_in_core'     => true,
				'is_checksumfail' => true,
			],
		] );

		$this->assertCount( 2, $resultItemInserts );
		$this->assertEqualsCanonicalizing( [ 'theme/style.php', 'wp-admin/core.php' ], \array_map(
			static fn( array $record ) :string => (string)$record[ 'item_id' ],
			$resultItemInserts
		) );
	}

	public function test_full_afs_protected_metadata_read_failure_prevents_mutation() :void {
		$metaDeletes = [];
		$resultItemInserts = [];
		$resultItemUpdates = [];
		$this->installController(
			[ $this->existingResultRow( 77, 'akismet/akismet.php' ) ],
			[],
			$metaDeletes,
			$resultItemInserts,
			$resultItemUpdates,
			null,
			77,
			1,
			[],
			true
		);

		$this->assertStoreFailure( 'metadata read', function () :void {
			( new Store() )->store( $this->newFullQueueItem(), [
				[
					'item_id' => 'akismet/akismet.php',
					'meta'    => [
						'asset_version' => '5.0',
						'is_mal'        => 1,
					],
				],
			] );
		} );

		$this->assertSame( [], $resultItemInserts );
		$this->assertSame( [], $resultItemUpdates );
		$this->assertSame( [], $metaDeletes );
	}

	/**
	 * @dataProvider persistenceFailureProvider
	 * @param list<string> $expectedStages
	 */
	public function test_store_stops_at_failed_persistence_stage(
		bool $hasExistingResult,
		string $failureStage,
		array $expectedStages,
		string $diagnosticStage
	) :void {
		$wpdb = $this->installControllerForFailure( $hasExistingResult, $failureStage );

		$this->assertStoreFailure( $diagnosticStage, function () :void {
			( new Store() )->store( $this->newQueueItem(), [ [ 'item_id' => 'akismet/akismet.php' ] ] );
		} );

		$this->assertSame( $expectedStages, $wpdb->stages );
	}

	public function persistenceFailureProvider() :array {
		return [
			'new result insert' => [ false, 'result_insert', [ 'result_insert' ], 'result item insert' ],
			'existing result update' => [ true, 'result_update', [ 'result_update' ], 'result item update' ],
			'metadata delete' => [ true, 'meta_delete', [ 'result_update', 'meta_delete' ], 'metadata delete' ],
			'metadata insert' => [ true, 'meta_insert', [ 'result_update', 'meta_delete', 'meta_insert' ], 'metadata insert' ],
			'observation insert' => [
				true,
				'observation_insert',
				[ 'result_update', 'meta_delete', 'meta_insert', 'observation_insert' ],
				'observation insert',
			],
		];
	}

	public function test_store_stops_when_new_result_id_is_invalid() :void {
		$wpdb = $this->installControllerForFailure( false, null, 0 );

		$this->assertStoreFailure( 'insert ID', function () :void {
			( new Store() )->store( $this->newQueueItem(), [ [ 'item_id' => 'akismet/akismet.php' ] ] );
		} );

		$this->assertSame( [ 'result_insert' ], $wpdb->stages );
		$this->assertSame( [], $wpdb->insertQueries );
	}

	public function test_store_accepts_zero_row_metadata_delete_as_success() :void {
		$wpdb = $this->installControllerForFailure( true, null, 77, 0 );

		( new Store() )->store( $this->newQueueItem(), [ [ 'item_id' => 'akismet/akismet.php' ] ] );

		$this->assertSame( [ 'result_update', 'meta_delete', 'meta_insert', 'observation_insert' ], $wpdb->stages );
		$this->assertCount( 1, $this->insertQueriesForTable( $wpdb, 'shield_scan_results' ) );
	}

	public function test_store_retry_reuses_partial_result_and_restores_missing_observation() :void {
		$firstAttempt = $this->installControllerForFailure( false, 'observation_insert' );
		$results = [ [ 'item_id' => 'akismet/akismet.php' ] ];

		$this->assertStoreFailure( 'observation insert', function () use ( $results ) :void {
			( new Store() )->store( $this->newQueueItem(), $results );
		} );
		$this->assertSame( [ 'result_insert', 'meta_insert', 'observation_insert' ], $firstAttempt->stages );

		$retryMetaDeletes = [];
		$retryResultItemInserts = [];
		$retryResultItemUpdates = [];
		$retry = $this->installController(
			[ $this->existingResultRow( 77, 'akismet/akismet.php' ) ],
			[],
			$retryMetaDeletes,
			$retryResultItemInserts,
			$retryResultItemUpdates
		);
		( new Store() )->store( $this->newQueueItem(), $results );

		$this->assertSame( [], $retryResultItemInserts );
		$this->assertCount( 1, $retryResultItemUpdates );
		$this->assertCount( 1, $this->insertQueriesForTable( $retry, 'shield_scan_result_item_meta' ) );
		$this->assertCount( 1, $this->insertQueriesForTable( $retry, 'shield_scan_results' ) );
	}

	public function test_existing_result_lookup_limits_legacy_candidates_to_unresolved_blank_rows() :void {
		$metaDeletes = [];
		$wpdb = $this->installController( [], [], $metaDeletes );

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
	}

	private function insertQueriesForTable( object $wpdb, string $table ) :array {
		return \array_values( \array_filter(
			$wpdb->insertQueries,
			static fn( string $query ) :bool => \strpos( $query, '`'.$table.'`' ) !== false
		) );
	}

	private function assertStoreFailure( string $stage, callable $operation ) :void {
		try {
			$operation();
			$this->fail( 'Expected scan result persistence failure.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( $stage, $e->getMessage() );
		}
	}

	private function installControllerForFailure(
		bool $hasExistingResult,
		?string $failingStage,
		int $lastInsertID = 77,
		$metaDeleteResult = 1
	) :object {
		$metaDeletes = [];
		$resultItemInserts = [];
		$resultItemUpdates = [];
		return $this->installController(
			$hasExistingResult ? [ $this->existingResultRow( 77, 'akismet/akismet.php' ) ] : [],
			[],
			$metaDeletes,
			$resultItemInserts,
			$resultItemUpdates,
			$failingStage,
			$lastInsertID,
			$metaDeleteResult
		);
	}

	private function installController(
		array $existingResultRows,
		array $observedResultItemIDs,
		array &$metaDeletes = [],
		array &$resultItemInserts = [],
		array &$resultItemUpdates = [],
		?string $failingStage = null,
		int $lastInsertID = 77,
		$metaDeleteResult = 1,
		array $existingMetaRows = [],
		bool $metaReadFailure = false
	) :object {
		$wpdb = new class(
			$existingResultRows,
			$observedResultItemIDs,
			$failingStage,
			$lastInsertID,
			$metaDeleteResult,
			$existingMetaRows,
			$metaReadFailure
		) extends Db {
			public array $selectQueries = [];
			public array $insertQueries = [];
			public array $stages = [];
			private array $existingResultRows;
			private array $observedResultItemIDs;
			private ?string $failingStage;
			private int $lastInsertID;
			private $metaDeleteResult;
			private array $existingMetaRows;
			private bool $metaReadFailure;

			public function __construct(
				array $existingResultRows,
				array $observedResultItemIDs,
				?string $failingStage,
				int $lastInsertID,
				$metaDeleteResult,
				array $existingMetaRows,
				bool $metaReadFailure
			) {
				$this->existingResultRows = $existingResultRows;
				$this->observedResultItemIDs = $observedResultItemIDs;
				$this->failingStage = $failingStage;
				$this->lastInsertID = $lastInsertID;
				$this->metaDeleteResult = $metaDeleteResult;
				$this->existingMetaRows = $existingMetaRows;
				$this->metaReadFailure = $metaReadFailure;
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
				if ( \strpos( (string)$query, 'shield_scan_result_item_meta' ) !== false ) {
					return $this->metaReadFailure ? false : $this->existingMetaRows;
				}
				return [];
			}

			public function getVar( $sql ) {
				unset( $sql );
				return $this->lastInsertID;
			}

			public function doSql( string $sqlQuery ) {
				$this->insertQueries[] = $sqlQuery;
				$stage = \strpos( $sqlQuery, '`shield_scan_result_item_meta`' ) !== false
					? 'meta_insert'
					: 'observation_insert';
				$this->stages[] = $stage;
				return $this->failsAt( $stage ) ? false : 1;
			}

			public function failsAt( string $stage ) :bool {
				return $this->failingStage === $stage;
			}

			public function metaDeleteResult() {
				return $this->metaDeleteResult;
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
							$record->auto_filtered_at = (int)( $result[ 'auto_filtered_at' ] ?? 0 );
							$record->last_seen_at = 1700000000;
							$record->resolved_at = 0;
							$record->resolution_reason = '';
							$meta = \array_merge( [
								'ptg_slug' => $result[ 'item_id' ],
							], $result[ 'meta' ] ?? [] );
							foreach ( [
								'is_in_core',
								'is_in_plugin',
								'is_in_theme',
								'ptg_slug',
								'asset_version',
								'is_checksumfail',
								'is_unrecognised',
								'is_unidentified',
								'is_missing',
								'comparison_basis',
								'is_mal',
								'malware_record_id',
							] as $metaKey ) {
								if ( \array_key_exists( $metaKey, $result ) ) {
									$meta[ $metaKey ] = $result[ $metaKey ];
								}
							}
							$record->meta = $meta;
							return $record;
						}
					};
				}
			},
		];
		$controller->db_con = (object)[
			'scan_result_items' => new class( $wpdb, $resultItemInserts, $resultItemUpdates ) {
				private object $wpdb;
				private array $resultItemInserts;
				private array $resultItemUpdates;

				public function __construct( object $wpdb, array &$resultItemInserts, array &$resultItemUpdates ) {
					$this->wpdb = $wpdb;
					$this->resultItemInserts =& $resultItemInserts;
					$this->resultItemUpdates =& $resultItemUpdates;
				}

				public function getTable() :string {
					return 'shield_scan_result_items';
				}

				public function getQueryInserter() :object {
					return new class( $this->wpdb, $this->resultItemInserts ) {
						private object $wpdb;
						private array $resultItemInserts;

						public function __construct( object $wpdb, array &$resultItemInserts ) {
							$this->wpdb = $wpdb;
							$this->resultItemInserts =& $resultItemInserts;
						}

						public function insert( ResultItemRecord $record ) :bool {
							$this->wpdb->stages[] = 'result_insert';
							if ( $this->wpdb->failsAt( 'result_insert' ) ) {
								return false;
							}
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
								'meta'              => $record->meta,
							];
							return true;
						}
					};
				}

				public function getQueryUpdater() :object {
					return new class( $this->wpdb, $this->resultItemUpdates ) {
						private object $wpdb;
						private array $resultItemUpdates;

						public function __construct( object $wpdb, array &$resultItemUpdates ) {
							$this->wpdb = $wpdb;
							$this->resultItemUpdates =& $resultItemUpdates;
						}

						public function updateRecord( ResultItemRecord $record, array $data ) :bool {
							$this->wpdb->stages[] = 'result_update';
							if ( $this->wpdb->failsAt( 'result_update' ) ) {
								return false;
							}
							$this->resultItemUpdates[] = [
								'id'   => (int)$record->id,
								'data' => $data,
							];
							return true;
						}
					};
				}
			},
			'scan_result_item_meta' => new class( $wpdb, $metaDeletes ) {
				private object $wpdb;
				private array $metaDeletes;

				public function __construct( object $wpdb, array &$metaDeletes ) {
					$this->wpdb = $wpdb;
					$this->metaDeletes =& $metaDeletes;
				}

				public function getTable() :string {
					return 'shield_scan_result_item_meta';
				}

				public function getQueryDeleter() :object {
					return new class( $this->wpdb, $this->metaDeletes ) {
						private object $wpdb;
						private array $metaDeletes;
						private array $ids = [];
						private $lastQueryResult = null;

						public function __construct( object $wpdb, array &$metaDeletes ) {
							$this->wpdb = $wpdb;
							$this->metaDeletes =& $metaDeletes;
						}

						public function filterByResultItems( array $resultItemIDs ) :self {
							$this->ids = \array_values( $resultItemIDs );
							return $this;
						}

						public function query() :bool {
							$this->wpdb->stages[] = 'meta_delete';
							$this->metaDeletes[] = $this->ids;
							$this->lastQueryResult = $this->wpdb->failsAt( 'meta_delete' )
								? false
								: $this->wpdb->metaDeleteResult();
							return $this->lastQueryResult !== false && $this->lastQueryResult > 0;
						}

						public function getLastQueryResult() {
							return $this->lastQueryResult;
						}
					};
				}

			},
			'scan_results' => new class {
				public function getTable() :string {
					return 'shield_scan_results';
				}
			},
		];

		PluginControllerInstaller::install( $controller );
		return $wpdb;
	}

	private function existingResultRow( int $id, string $itemID, array $overrides = [] ) :array {
		return \array_merge( [
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
		], $overrides );
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

	private function newFullQueueItem() :QueueItemVO {
		$queueItem = $this->newQueueItem();
		$queueItem->scope_type = 'full';
		$queueItem->meta = [
			'asset_snapshot_eligibility' => [
				'plugin' => [
					'akismet/akismet.php' => [
						'version'             => '5.0',
						'comparison_eligible' => false,
					],
				],
				'theme'  => [],
			],
		];
		return $queueItem;
	}
}
