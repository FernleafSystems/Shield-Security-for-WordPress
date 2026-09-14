<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Init;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueHeartbeat;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\SnapshotPluginVo;
use FernleafSystems\Wordpress\Services\Core\{
	Db,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class PopulateScanItemsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];
	private PopulateScanItemsAssetCoordinator $assetCoordinator;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->assetCoordinator = new PopulateScanItemsAssetCoordinator();
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700004000 ),
			'service_wpplugins' => new PopulateScanItemsPlugins(),
			'service_wpthemes'  => new PopulateScanItemsThemes(),
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
		$scanMeta = \json_decode( \base64_decode( (string)$scanUpdates[ 0 ][ 'data' ][ 'meta' ] ), true );
		$this->assertSame( 'value', $scanMeta[ 'scan_meta' ] ?? null );
		$this->assertSame( [
			'plugin' => [],
			'theme'  => [],
		], $scanMeta[ 'asset_snapshot_eligibility' ] ?? null );
		$this->assertArrayHasKey( 'coverage_families', $scanMeta );
		$this->assertSame( $this->coverageFamilies(), $scanMeta[ 'coverage_families' ] );
		$this->assertSame( 'full', $scanMeta[ 'scope_type' ] ?? null );
		$this->assertSame( '', $scanMeta[ 'scope_key' ] ?? null );
		$this->assertArrayNotHasKey( 'progress_callback', $scanMeta );
		$this->assertArrayNotHasKey( 'items', $scanMeta );
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
		$this->assertIsCallable( $scanController->lastAction->progress_callback );
		$this->assertCount( 1, $heartbeatQueries );
		$this->assertStringContainsString( 'UPDATE `shield_scans`', $heartbeatQueries[ 0 ] );
		$this->assertStringContainsString( '`id`=19', $heartbeatQueries[ 0 ] );
		$this->assertStringContainsString( "`status`='building'", $heartbeatQueries[ 0 ] );
	}

	public function test_full_afs_inventory_is_captured_once_and_eligibility_is_ready_before_build() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$heartbeatQueries = [];
		$asset = new SnapshotPluginVo( 'inventory/plugin.php', '1.0.0' );
		$plugins = new PopulateScanItemsPlugins( [ $asset ] );
		$themes = new PopulateScanItemsThemes();
		ServicesState::mergeItems( [
			'service_wpplugins' => $plugins,
			'service_wpthemes'  => $themes,
		] );
		$this->assetCoordinator->result = [
			'plugin' => [
				$asset->file => [
					'version'             => $asset->Version,
					'comparison_eligible' => true,
				],
			],
			'theme'  => [],
		];
		$this->installHeartbeatDb( $heartbeatQueries );
		$this->installController( $scanUpdates, $itemInsertCount, true );
		$validBeforeBuild = false;
		$scanController = $this->buildScanController(
			[ 'one' ],
			static function ( BaseScanActionVO $action ) use ( &$validBeforeBuild, $asset ) :void {
				$validBeforeBuild = $action instanceof ScanActionVO
					&& $action->hasValidAssetSnapshotEligibility()
					&& $action->isAssetSnapshotComparisonEligible( 'plugin', $asset->file, $asset->Version );
			}
		);

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 21;
		$scanRecord->scan = 'afs';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';
		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $scanController )
			->run();

		$this->assertTrue( $validBeforeBuild );
		$this->assertSame( 1, $plugins->calls );
		$this->assertSame( 1, $themes->calls );
		$this->assertSame( 1, $this->assetCoordinator->calls );
		$this->assertCount( 1, $this->assetCoordinator->inventories );
		$this->assertSame( [ $asset ], $this->assetCoordinator->inventories[ 0 ] );
		$this->assertCount( 1, $heartbeatQueries );
	}

	/**
	 * @dataProvider provideScanScopesWithoutFullAfsReadiness
	 */
	public function test_scoped_afs_and_other_scan_families_skip_full_snapshot_readiness(
		string $scan,
		string $scopeType
	) :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$heartbeatQueries = [];
		$plugins = new PopulateScanItemsPlugins( [
			new SnapshotPluginVo( 'skipped/plugin.php', '1.0.0' ),
		] );
		$themes = new PopulateScanItemsThemes();
		ServicesState::mergeItems( [
			'service_wpplugins' => $plugins,
			'service_wpthemes'  => $themes,
		] );
		$this->installHeartbeatDb( $heartbeatQueries );
		$this->installController( $scanUpdates, $itemInsertCount, true );

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 22;
		$scanRecord->scan = $scan;
		$scanRecord->scope_type = $scopeType;
		$scanRecord->scope_key = $scopeType === 'plugin' ? 'skipped/plugin.php' : '';
		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $this->buildScanController( [ 'one' ] ) )
			->run();

		$this->assertSame( 0, $plugins->calls );
		$this->assertSame( 0, $themes->calls );
		$this->assertSame( 0, $this->assetCoordinator->calls );
	}

	public function provideScanScopesWithoutFullAfsReadiness() :array {
		return [
			'scoped AFS'     => [ 'afs', 'plugin' ],
			'other full scan' => [ 'wpv', 'full' ],
		];
	}

	public function test_invalid_coordinator_eligibility_fails_before_scan_action_build() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$heartbeatQueries = [];
		$built = false;
		$this->assetCoordinator->result = [ 'plugin' => [] ];
		$this->installHeartbeatDb( $heartbeatQueries );
		$this->installController( $scanUpdates, $itemInsertCount, true );
		$scanController = $this->buildScanController(
			[ 'one' ],
			static function () use ( &$built ) :void {
				$built = true;
			}
		);
		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 23;
		$scanRecord->scan = 'afs';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';

		$this->expectException( \UnexpectedValueException::class );
		try {
			( new PopulateScanItems() )
				->setRecord( $scanRecord )
				->setScanController( $scanController )
				->run();
		}
		finally {
			$this->assertFalse( $built );
			$this->assertSame( 0, $itemInsertCount );
			$this->assertSame( [], $scanUpdates );
		}
	}

	/**
	 * @dataProvider provideInvalidBuiltReadbacks
	 */
	public function test_nonempty_scan_fails_closed_when_built_state_readback_does_not_match(
		?string $readbackStatus,
		?string $readbackMeta,
		bool $persistScanUpdates
	) :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$itemInserts = [];
		$heartbeatQueries = [];
		$this->installHeartbeatDb( $heartbeatQueries );
		$this->installController(
			$scanUpdates,
			$itemInsertCount,
			true,
			$itemInserts,
			$readbackStatus,
			$readbackMeta,
			$persistScanUpdates
		);
		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 24;
		$scanRecord->scan = 'afs';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to persist built scan state' );
		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $this->buildScanController( [ 'one' ] ) )
			->run();
	}

	public function provideInvalidBuiltReadbacks() :array {
		return [
			'write failure'            => [ null, null, false ],
			'wrong status'             => [ ScanStatus::BUILDING, null, true ],
			'wrong raw metadata bytes' => [ null, \base64_encode( '{"different":true}' ), true ],
		];
	}

	public function test_run_completes_empty_scan_with_metadata_in_completion_update() :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$itemInserts = [];
		$wpdbQueries = [];
		$this->installHeartbeatDb( $wpdbQueries );
		$this->installController(
			$scanUpdates,
			$itemInsertCount,
			true,
			$itemInserts,
			null,
			$this->encodedScanMeta( 'full', '' )
		);

		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 18;
		$scanRecord->scan = 'wpv';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';
		$scanRecord->run_trigger = 'manual';

		$scanController = $this->buildScanController( [] );
		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $scanController )
			->run();

		$this->assertSame( 0, $itemInsertCount );
		$this->assertSame( [], $scanUpdates );
		$completionQueries = \array_values( \array_filter(
			$wpdbQueries,
			static fn( string $query ) :bool => \strpos( $query, "`status`='completed'" ) !== false
		) );
		$this->assertCount( 1, $completionQueries );
		$completionQuery = $completionQueries[ 0 ];
		$this->assertStringContainsString( 'NOT EXISTS', $completionQuery );
		$this->assertSame( 1, \preg_match( "/`meta`='([^']+)'/", $completionQuery, $matches ) );
		$decodedMeta = \base64_decode( $matches[ 1 ], true );
		$this->assertIsString( $decodedMeta );
		$scanMeta = \json_decode( $decodedMeta, true );
		$this->assertIsArray( $scanMeta );
		$this->assertSame( 'value', $scanMeta[ 'scan_meta' ] ?? null );
		$this->assertArrayHasKey( 'coverage_families', $scanMeta );
		$this->assertSame( $this->coverageFamilies(), $scanMeta[ 'coverage_families' ] );
		$this->assertSame( 'full', $scanMeta[ 'scope_type' ] ?? null );
		$this->assertSame( '', $scanMeta[ 'scope_key' ] ?? null );
		$this->assertArrayNotHasKey( 'progress_callback', $scanMeta );
		$this->assertArrayNotHasKey( 'items', $scanMeta );
		$this->assertIsCallable( $scanController->lastAction->progress_callback );
	}

	/**
	 * @dataProvider provideEmptyCompletionPersistenceFailures
	 */
	public function test_empty_scan_completion_fails_closed_on_write_or_raw_metadata_mismatch(
		int $writeResult,
		string $readbackMeta
	) :void {
		$scanUpdates = [];
		$itemInsertCount = 0;
		$itemInserts = [];
		$wpdbQueries = [];
		$this->installHeartbeatDb( $wpdbQueries, $writeResult );
		$this->installController(
			$scanUpdates,
			$itemInsertCount,
			true,
			$itemInserts,
			null,
			$readbackMeta
		);
		$scanRecord = new ScansDB\Record();
		$scanRecord->id = 25;
		$scanRecord->scan = 'wpv';
		$scanRecord->scope_type = 'full';
		$scanRecord->scope_key = '';
		$scanRecord->run_trigger = 'manual';

		$this->expectException( \RuntimeException::class );
		( new PopulateScanItems() )
			->setRecord( $scanRecord )
			->setScanController( $this->buildScanController( [] ) )
			->run();
	}

	public function provideEmptyCompletionPersistenceFailures() :array {
		return [
			'completion update failure' => [ 0, \base64_encode( '[]' ) ],
			'raw metadata mismatch'     => [ 1, \base64_encode( '{"different":true}' ) ],
		];
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
				$action = new class extends ScanActionVO {
					public int $progressTicks = 0;

					public function tickProgress() :void {
						$this->progressTicks++;
						parent::tickProgress();
					}
				};
				$action->scan_meta = 'value';
				$action->coverage_families = [
					ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
					ScanActionVO::COVERAGE_FAMILY_MALWARE,
				];
				return $action;
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

	private function installHeartbeatDb( array &$queries, int $doSqlResult = 1 ) :void {
		ServicesState::mergeItems( [
			'service_wpdb' => new class( $queries, $doSqlResult ) extends Db {
				public array $queries;
				private int $doSqlResult;

				public function __construct( array &$queries, int $doSqlResult ) {
					$this->queries = &$queries;
					$this->doSqlResult = $doSqlResult;
				}

				public function doSql( $sql ) {
					$this->queries[] = (string)$sql;
					return $this->doSqlResult;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $query, $format );
					return [];
				}
			},
		] );
	}

	private function installController(
		array &$scanUpdates,
		int &$itemInsertCount,
		bool $itemInsertSuccess,
		array &$itemInserts = [],
		?string $readbackStatus = null,
		?string $readbackMeta = null,
		bool $persistScanUpdates = true
	) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class(
				$scanUpdates,
				$readbackStatus,
				$readbackMeta,
				$persistScanUpdates
			) {
				public array $updates;
				private ?string $readbackStatus;
				private ?string $readbackMeta;
				private bool $persistScanUpdates;

				public function __construct(
					array &$updates,
					?string $readbackStatus,
					?string $readbackMeta,
					bool $persistScanUpdates
				) {
					$this->updates = &$updates;
					$this->readbackStatus = $readbackStatus;
					$this->readbackMeta = $readbackMeta;
					$this->persistScanUpdates = $persistScanUpdates;
				}

				public function getQueryUpdater() :object {
					return new class( $this->updates, $this->persistScanUpdates ) {
						public array $updates;
						private bool $persistScanUpdates;

						public function __construct( array &$updates, bool $persistScanUpdates ) {
							$this->updates = &$updates;
							$this->persistScanUpdates = $persistScanUpdates;
						}

						public function updateById( int $scanID, array $data ) :bool {
							if ( $this->persistScanUpdates ) {
								$this->updates[] = [ 'scan_id' => $scanID, 'data' => $data ];
							}
							return $this->persistScanUpdates;
						}
					};
				}

				public function getTable() :string {
					return 'shield_scans';
				}

				public function getQuerySelector() :object {
					return new class( $this->updates, $this->readbackStatus, $this->readbackMeta ) {
						private array $updates;
						private ?string $readbackStatus;
						private ?string $readbackMeta;

						public function __construct(
							array &$updates,
							?string $readbackStatus,
							?string $readbackMeta
						) {
							$this->updates = &$updates;
							$this->readbackStatus = $readbackStatus;
							$this->readbackMeta = $readbackMeta;
						}

						public function byId( int $scanID ) :ScansDB\Record {
							$status = ScanStatus::BUILDING;
							$rawMeta = \base64_encode( '[]' );
							foreach ( \array_reverse( $this->updates ) as $update ) {
								if ( $update[ 'scan_id' ] !== $scanID ) {
									continue;
								}
								$status = (string)( $update[ 'data' ][ 'status' ] ?? $status );
								$rawMeta = (string)( $update[ 'data' ][ 'meta' ] ?? $rawMeta );
								break;
							}
							$status = $this->readbackStatus ?? $status;
							$rawMeta = $this->readbackMeta ?? $rawMeta;

							$record = new ScansDB\Record();
							$record->applyFromArray( [ 'meta' => $rawMeta ] );
							$record->id = $scanID;
							$record->scan = 'wpv';
							$record->status = $status;
							$record->scope_type = 'full';
							$record->scope_key = '';
							$record->run_trigger = 'manual';
							$record->finished_at = 0;
							return $record;
						}
					};
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
			'asset_coordinator' => $this->assetCoordinator,
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

	/**
	 * @return list<string>
	 */
	private function coverageFamilies() :array {
		return [
			ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY,
			ScanActionVO::COVERAGE_FAMILY_MALWARE,
		];
	}

	private function encodedScanMeta( string $scopeType, string $scopeKey ) :string {
		$record = new ScansDB\Record();
		$record->meta = [
			'scan_meta'         => 'value',
			'coverage_families' => $this->coverageFamilies(),
			'scope_type'        => $scopeType,
			'scope_key'         => $scopeKey,
		];
		return (string)( $record->getRawData()[ 'meta' ] ?? '' );
	}
}

class PopulateScanItemsAssetCoordinator {

	public int $calls = 0;
	public array $inventories = [];
	public array $result = [
		'plugin' => [],
		'theme'  => [],
	];

	public function prepareFullScanSnapshotEligibility( array $assets, callable $heartbeat ) :array {
		$this->calls++;
		$this->inventories[] = $assets;
		foreach ( $assets as $asset ) {
			unset( $asset );
			$heartbeat();
		}
		return $this->result;
	}
}

class PopulateScanItemsPlugins extends Plugins {

	public int $calls = 0;
	private array $assets;

	public function __construct( array $assets = [] ) {
		$this->assets = $assets;
	}

	public function getPluginsAsVo() :array {
		$this->calls++;
		return $this->assets;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		foreach ( $this->assets as $asset ) {
			if ( $asset instanceof WpPluginVo && $asset->file === $file ) {
				return $asset;
			}
		}
		return null;
	}
}

class PopulateScanItemsThemes extends Themes {

	public int $calls = 0;
	private array $assets;

	public function __construct( array $assets = [] ) {
		$this->assets = $assets;
	}

	public function getThemesAsVo() :array {
		$this->calls++;
		return $this->assets;
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $reload );
		foreach ( $this->assets as $asset ) {
			if ( $asset instanceof WpThemeVo && $asset->stylesheet === $stylesheet ) {
				return $asset;
			}
		}
		return null;
	}
}
