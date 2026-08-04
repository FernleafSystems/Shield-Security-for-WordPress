<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	CompleteQueue,
	ProcessQueueItem,
	QueueItems,
	RunState
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\FileScanOptimiser;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	WrittenFixtureFiles
};
use FernleafSystems\Wordpress\Services\Services;

class AfsHashQueueCompositionTest extends BaseUnitTest {

	use TempDirLifecycleTrait;
	use WrittenFixtureFiles;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		AssetTrustResolver::resetMemoization();
		Retrieve::resetMemoization();
		$this->resetHashesStorageDir();
	}

	protected function tearDown() :void {
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->removeWrittenFixtureFiles();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_hash_read_failure_stores_checksum_finding_and_completes_queue_item() :void {
		$pluginFile = 'queue-hash-read-failure/plugin.php';
		$path = $this->writePluginFile( $pluginFile, "<?php\n// valid plugin fixture\n" );
		$cacheRoot = $this->createTrackedTempDir( 'shield-afs-hash-queue-' );
		$harness = $this->newAfsHarness( $cacheRoot, $pluginFile );
		$this->writeHashStore( $cacheRoot, $pluginFile, [
			'plugin.php' => \md5_file( $path ),
		] );

		$this->assertTrue( @\unlink( $path ) );
		$harness->forceAfsIsFileFor( $path );

		$scanID = $this->insertReadyAfsWork( $harness, $path );
		$item = ( new QueueItems() )->next();
		$this->assertNotNull( $item );
		$this->assertSame( 1, $item->attempts );

		( new ProcessQueueItem() )->run( $item );
		( new CompleteQueue() )->complete();

		$records = $harness->resultItemRecords();
		$this->assertCount( 1, $records, \json_encode( $this->scanMeta( $harness->scanRow( $scanID ) ) ) ?: '' );
		$result = $records[ 0 ];
		$this->assertSame( 'afs', $result->scan );
		$this->assertSame( 'plugin', $result->asset_type );
		$this->assertSame( $pluginFile, $result->asset_key );
		$this->assertSame( 0, $result->auto_filtered_at );
		$this->assertSame( '1.0.0', $result->meta[ 'asset_version' ] ?? null );
		$this->assertTrue( $result->meta[ 'is_checksumfail' ] ?? false );
		$this->assertTrue( $result->meta[ 'is_in_plugin' ] ?? false );
		$this->assertFalse( $result->meta[ 'is_in_theme' ] ?? false );
		$this->assertArrayNotHasKey( 'checksum_sha256', $result->meta );

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'completed', $scan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
		$this->assertTrue( $this->queryLogContains( $harness->sql->queryLog(), 'UPDATE `scan_items` SET `finished_at`=' ) );
	}

	public function test_invalid_hash_source_produces_no_comparison_and_completes_queue_item() :void {
		$pluginFile = 'queue-invalid-hash-source/plugin.php';
		$path = $this->writePluginFile( $pluginFile, "<?php\n// valid plugin fixture\n" );
		$cacheRoot = $this->createTrackedTempDir( 'shield-afs-invalid-hash-' );
		$harness = $this->newAfsHarness( $cacheRoot, $pluginFile );
		$this->writeHashStore( $cacheRoot, $pluginFile, [
			'plugin.php' => 'unsupported-hash',
		] );

		$scanID = $this->insertReadyAfsWork( $harness, $path );
		$item = ( new QueueItems() )->next();
		$this->assertNotNull( $item );
		$itemID = $item->qitem_id;
		$this->assertSame( 1, $item->attempts );

		( new ProcessQueueItem() )->run( $item );
		( new CompleteQueue() )->complete();

		$completedScan = $harness->scanRow( $scanID );
		$this->assertSame( 'completed', $completedScan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$completedScan[ 'finished_at' ] );
		$this->assertSame( [], $harness->resultItemRecords() );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $completedScan ) );
		$this->assertSame( [], $harness->scanItemRow( $itemID ) );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
	}

	/**
	 * @dataProvider providePersistedAfsActionConfig
	 */
	public function test_persisted_afs_action_config_is_canonical_before_processing(
		$fileExts,
		bool $expectTrustedFileRecord,
		$maxFileSize = ScanActionVO::DEFAULT_MAX_FILE_SIZE
	) :void {
		$pluginFile = 'queue-file-exts/plugin.php';
		$path = $this->writePluginFile( $pluginFile, "<?php\n// valid plugin fixture\n" );
		$cacheRoot = $this->createTrackedTempDir( 'shield-afs-file-exts-' );
		$harness = $this->newAfsHarness( $cacheRoot, $pluginFile );
		$this->writeHashStore( $cacheRoot, $pluginFile, [
			'plugin.php' => \md5_file( $path ),
		] );

		$scanID = $this->insertReadyAfsWork( $harness, $path, $fileExts, $maxFileSize );
		$item = ( new QueueItems() )->next();
		$this->assertNotNull( $item );
		$this->assertSame( 1, $item->attempts );

		( new ProcessQueueItem() )->run( $item );
		( new CompleteQueue() )->complete();

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'completed', $scan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
		$this->assertSame( [], $harness->resultItemRecords() );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
		$this->assertSame( $expectTrustedFileRecord, ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
	}

	public function providePersistedAfsActionConfig() :array {
		return [
			'null safely disables matching'   => [ null, false ],
			'scalar safely disables matching' => [ ' PHP ', false ],
			'associative preserves member'    => [ [ 'primary' => ' PHP ' ], true ],
			'mixed preserves valid member'    => [ [ 12, ' PHP ', false, null ], true ],
			'null max size uses default'       => [ [ 'php' ], true, null ],
			'array max size uses default'      => [ [ 'php' ], true, [ 1 ] ],
			'positive max size is preserved'   => [ [ 'php' ], false, 1 ],
		];
	}

	public function test_queue_row_scope_overrides_conflicting_persisted_scope_metadata() :void {
		$pluginFile = 'queue-authoritative-scope/plugin.php';
		$path = $this->writePluginFile( $pluginFile, "<?php\n// changed plugin fixture\n" );
		$cacheRoot = $this->createTrackedTempDir( 'shield-afs-authoritative-scope-' );
		$harness = $this->newAfsHarness( $cacheRoot, $pluginFile );
		$this->writeHashStore( $cacheRoot, $pluginFile, [
			'plugin.php' => \str_repeat( 'a', 32 ),
		] );

		$scanID = $this->insertReadyAfsWork(
			$harness,
			$path,
			[ 'php' ],
			ScanActionVO::DEFAULT_MAX_FILE_SIZE,
			[
				'scope_type'                => 'plugin',
				'scope_key'                 => $pluginFile,
				'asset_snapshot_eligibility' => [
					'plugin' => [
						$pluginFile => [
							'version'             => '1.0.0',
							'comparison_eligible' => false,
						],
					],
					'theme' => [],
				],
			]
		);
		$item = ( new QueueItems() )->next();
		$this->assertNotNull( $item );
		$this->assertSame( 'full', $item->scope_type );
		$this->assertSame( 'plugin', $item->meta[ 'scope_type' ] );

		( new ProcessQueueItem() )->run( $item );
		( new CompleteQueue() )->complete();

		$this->assertSame( [], $harness->resultItemRecords() );
		$this->assertSame( 'completed', $harness->scanRow( $scanID )[ 'status' ] );
	}

	public function test_frozen_false_eligibility_is_immutable_across_successive_queue_items() :void {
		$pluginFile = 'queue-frozen-false/plugin.php';
		$path = $this->writePluginFile( $pluginFile, "<?php\n// frozen false fixture\n" );
		$cacheRoot = $this->createTrackedTempDir( 'shield-afs-frozen-false-' );
		$harness = $this->newAfsHarness( $cacheRoot, $pluginFile );
		$frozenEligibility = [
			'plugin' => [
				$pluginFile => [
					'version'             => '1.0.0',
					'comparison_eligible' => false,
				],
			],
			'theme' => [],
		];
		$scanID = $this->insertReadyAfsWork(
			$harness,
			$path,
			[ 'php' ],
			ScanActionVO::DEFAULT_MAX_FILE_SIZE,
			[ 'asset_snapshot_eligibility' => $frozenEligibility ]
		);
		$harness->insertScanItem( $scanID, [ \base64_encode( $path ) ] );

		$first = ( new QueueItems() )->next();
		$this->assertNotNull( $first );
		( new ProcessQueueItem() )->run( $first );
		$this->assertSame( [], $harness->resultItemRecords() );
		$this->assertSame(
			$frozenEligibility,
			$this->scanMeta( $harness->scanRow( $scanID ) )[ 'asset_snapshot_eligibility' ] ?? null
		);

		$this->writeHashStore( $cacheRoot, $pluginFile, [
			'plugin.php' => \str_repeat( 'a', 32 ),
		] );
		$second = ( new QueueItems() )->next();
		$this->assertNotNull( $second );
		( new ProcessQueueItem() )->run( $second );
		( new CompleteQueue() )->complete();

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'completed', $scan[ 'status' ] );
		$this->assertSame( $frozenEligibility, $this->scanMeta( $scan )[ 'asset_snapshot_eligibility' ] ?? null );
		$this->assertSame( [], $harness->resultItemRecords() );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
	}

	private function newAfsHarness( string $cacheRoot, string $pluginFile ) :ScanQueueLifecycleHarness {
		return ( new ScanQueueLifecycleHarness() )
			->install()
			->installAfsWorkerEnvironment( $cacheRoot )
			->setInstalledPluginFiles( [ $pluginFile ] )
			->useRealAfsController();
	}

	private function insertReadyAfsWork(
		ScanQueueLifecycleHarness $harness,
		string $path,
		$fileExts = [ 'php' ],
		$maxFileSize = ScanActionVO::DEFAULT_MAX_FILE_SIZE,
		array $metaOverrides = []
	) :int {
		$normalizedPath = \str_replace( '\\', '/', $path );
		$pluginRoot = \rtrim( \str_replace( '\\', '/', WP_PLUGIN_DIR ), '/' ).'/';
		$pluginFile = \substr( $normalizedPath, \strlen( $pluginRoot ) );
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'meta'            => \base64_encode( \json_encode( \array_merge( [
				'coverage_families' => [ ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY ],
				'file_exts'         => $fileExts,
				'max_file_size'     => $maxFileSize,
				'paths_whitelisted' => [],
				'scan_root_dirs'    => [],
				'usleep'            => 0,
				'valid_files'       => [],
				'asset_snapshot_eligibility' => [
					'plugin' => [
						$pluginFile => [
							'version'             => '1.0.0',
							'comparison_eligible' => true,
						],
					],
					'theme' => [],
				],
			], $metaOverrides ) ) ?: '[]' ),
		] );
		$harness->insertScanItem( $scanID, [ \base64_encode( $path ) ] );
		return $scanID;
	}

	private function writeHashStore( string $cacheRoot, string $pluginFile, array $hashes ) :void {
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		if ( !\is_dir( $hashDir ) && !@\mkdir( $hashDir, 0777, true ) && !\is_dir( $hashDir ) ) {
			throw new \RuntimeException( 'Failed to create hash fixture directory.' );
		}
		$asset = Services::WpPlugins()->getPluginAsVo( $pluginFile, true );
		$this->assertNotNull( $asset );
		( new Store( $asset, true ) )
			->setWorkingDir( $hashDir )
			->setSnapData( $hashes )
			->setSnapMeta( [
				'version'     => '1.0.0',
				'unique_id'   => $pluginFile,
				'live_hashes' => true,
			] )
			->save();
		Retrieve::resetMemoization();
	}

	private function writePluginFile( string $pluginFile, string $contents ) :string {
		$path = \str_replace( '\\', '/', WP_PLUGIN_DIR.'/'.$pluginFile );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) && !@\mkdir( $dir, 0777, true ) && !\is_dir( $dir ) ) {
			throw new \RuntimeException( 'Failed to create plugin fixture directory.' );
		}
		if ( \file_put_contents( $path, $contents ) === false ) {
			throw new \RuntimeException( 'Failed to write plugin fixture.' );
		}
		return $this->trackWrittenFixtureFile( $path );
	}

	private function scanMeta( array $scan ) :array {
		return \json_decode( \base64_decode( (string)( $scan[ 'meta' ] ?? '' ) ), true ) ?: [];
	}

	private function queryLogContains( array $queries, string $needle ) :bool {
		foreach ( $queries as $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private function resetHashesStorageDir() :void {
		$reflection = new \ReflectionClass( HashesStorageDir::class );
		foreach ( [ 'dir', 'rootDir' ] as $propertyName ) {
			if ( $reflection->hasProperty( $propertyName ) ) {
				$property = $reflection->getProperty( $propertyName );
				$property->setAccessible( true );
				$property->setValue( null, null );
			}
		}
	}
}
