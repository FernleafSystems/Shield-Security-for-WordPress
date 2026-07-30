<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\AssetCoordinator;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\Load;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	WrittenFixtureFiles
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\{
	SnapshotPluginVo,
	SnapshotPlugins,
	SnapshotThemeVo,
	SnapshotThemes,
	SnapshotWpGeneral
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestCacheDir,
	CacheStoreTestController,
	CacheStoreTestFs,
	CacheStoreTestOptions,
	CacheStoreTestRequest,
	CacheStoreWordPressFunctions
};

class FullScanSnapshotReadinessTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;
	use TempDirLifecycleTrait;
	use WrittenFixtureFiles;

	private array $servicesSnapshot = [];
	private bool $isMainNetwork = true;
	private bool $isMainSite = true;
	private CacheStoreTestFs $fs;
	private int $remoteRequestCount = 0;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		Functions\when( 'is_main_network' )->alias( fn() :bool => $this->isMainNetwork );
		Functions\when( 'is_main_site' )->alias( fn() :bool => $this->isMainSite );
		Functions\when( 'wp_http_validate_url' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_request' )->alias( function () :array {
			$this->remoteRequestCount++;
			return [
				'body'     => '{}',
				'headers'  => [],
				'cookies'  => [],
				'filename' => null,
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
			];
		} );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => (string)\json_encode( $data ) );
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

	public function test_main_owner_loads_builds_and_reloads_missing_local_baseline() :void {
		$asset = new SnapshotPluginVo( 'readiness-owner/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $asset ] );
		$this->writePluginFile( $asset, "<?php\n// local snapshot source\n" );
		$heartbeats = 0;

		$eligibility = ( new AssetCoordinator() )->prepareFullScanSnapshotEligibility(
			[ $asset ],
			static function () use ( &$heartbeats ) :void {
				$heartbeats++;
			}
		);

		$this->assertSame( [
			'plugin' => [
				$asset->file => [
					'version'             => $asset->Version,
					'comparison_eligible' => true,
				],
			],
			'theme' => [],
		], $eligibility );
		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame(
			[ 'plugin.php' => \md5_file( WP_PLUGIN_DIR.'/'.$asset->file ) ],
			$store->getSnapData()
		);
		$this->assertSame( $asset->Version, $store->getSnapMeta()[ 'version' ] ?? null );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] ?? true );
		$this->assertSame( 1, $heartbeats );
	}

	public function test_existing_usable_snapshot_does_not_build_or_reset_memoization() :void {
		$asset = new SnapshotPluginVo( 'existing/plugin.php', '1.0.0' );
		$coordinator = ( new FullScanSnapshotReadinessHarness() )
			->setLoadOutcomes( $asset->file, [ true ] );
		$this->seedMemoizationSentinels();

		$eligibility = $coordinator->prepareFullScanSnapshotEligibility( [ $asset ], static function () :void {} );

		$this->assertTrue( $eligibility[ 'plugin' ][ $asset->file ][ 'comparison_eligible' ] );
		$this->assertSame( [ 'load:'.$asset->file ], $coordinator->events );
		$this->assertMemoizationSentinelsPresent();
	}

	/**
	 * @dataProvider provideNonOwnerTopologies
	 */
	public function test_non_owner_is_read_only_for_missing_snapshot(
		bool $isMainNetwork,
		bool $isMainSite
	) :void {
		$this->isMainNetwork = $isMainNetwork;
		$this->isMainSite = $isMainSite;
		$existing = new SnapshotPluginVo( 'existing/plugin.php', '1.0.0' );
		$missing = new SnapshotThemeVo( 'missing-theme', '2.0.0' );
		$cacheRoot = $this->installEnvironment( [ $existing ], [ $missing ] );
		$this->writeStore( $existing, [ 'plugin.php' => \str_repeat( 'a', 32 ) ] );
		$this->fs->fileWriteCounts = [];
		$this->fs->touchCounts = [];
		$treeBefore = $this->snapshotTree( $cacheRoot );
		$heartbeats = 0;

		$eligibility = ( new AssetCoordinator() )->prepareFullScanSnapshotEligibility(
			[ $existing, $missing ],
			static function () use ( &$heartbeats ) :void {
				$heartbeats++;
			}
		);

		$this->assertTrue( $eligibility[ 'plugin' ][ $existing->file ][ 'comparison_eligible' ] );
		$this->assertFalse( $eligibility[ 'theme' ][ $missing->stylesheet ][ 'comparison_eligible' ] );
		$this->assertSame( 2, $heartbeats );
		$this->assertSame( [], $this->fs->fileWriteCounts );
		$this->assertSame( [], $this->fs->touchCounts );
		$this->assertSame( $treeBefore, $this->snapshotTree( $cacheRoot ) );
		$this->assertSame( 0, $this->remoteRequestCount );
	}

	public function provideNonOwnerTopologies() :array {
		return [
			'main network secondary site' => [ true, false ],
			'secondary network main site' => [ false, true ],
			'secondary network and site'  => [ false, false ],
		];
	}

	public function test_invalid_conflicting_and_failing_assets_do_not_skip_valid_sibling() :void {
		$duplicateA = new SnapshotPluginVo( 'duplicate/plugin.php', '1.0.0' );
		$duplicateB = new SnapshotPluginVo( 'duplicate/plugin.php', '2.0.0' );
		$failing = new SnapshotPluginVo( 'failing/plugin.php', '1.0.0' );
		$sibling = new SnapshotThemeVo( 'valid-theme', '3.0.0' );
		$coordinator = ( new FullScanSnapshotReadinessHarness() )
			->setLoadOutcomes( $failing->file, [ false ] )
			->setBuildFailure( $failing->file )
			->setLoadOutcomes( $sibling->stylesheet, [ true ] );
		$heartbeats = 0;

		$eligibility = $coordinator->prepareFullScanSnapshotEligibility(
			[ new \stdClass(), $duplicateA, $duplicateB, $failing, $sibling ],
			static function () use ( &$heartbeats ) :void {
				$heartbeats++;
			}
		);

		$this->assertSame( 5, $heartbeats );
		$this->assertSame( [
			'version'             => $duplicateA->Version,
			'comparison_eligible' => false,
		], $eligibility[ 'plugin' ][ $duplicateA->file ] );
		$this->assertFalse( $eligibility[ 'plugin' ][ $failing->file ][ 'comparison_eligible' ] );
		$this->assertTrue( $eligibility[ 'theme' ][ $sibling->stylesheet ][ 'comparison_eligible' ] );
		$this->assertSame( [
			'load:'.$failing->file,
			'build:'.$failing->file,
			'load:'.$sibling->stylesheet,
		], $coordinator->events );
	}

	public function test_successful_build_resets_hash_and_asset_memoization_after_reload() :void {
		$asset = new SnapshotPluginVo( 'memo/plugin.php', '1.0.0' );
		$coordinator = ( new FullScanSnapshotReadinessHarness() )
			->setLoadOutcomes( $asset->file, [ false, true ] );
		$this->seedMemoizationSentinels();

		$eligibility = $coordinator->prepareFullScanSnapshotEligibility( [ $asset ], static function () :void {} );

		$this->assertTrue( $eligibility[ 'plugin' ][ $asset->file ][ 'comparison_eligible' ] );
		$this->assertSame( [
			'load:'.$asset->file,
			'build:'.$asset->file,
			'load:'.$asset->file,
		], $coordinator->events );
		$this->assertSame( [], $this->staticProperty( Retrieve::class, 'sources' ) );
		$this->assertSame( [], $this->staticProperty( AssetTrustResolver::class, 'plugins' ) );
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 * @param SnapshotThemeVo[]  $themes
	 */
	private function installEnvironment( array $plugins, array $themes = [] ) :string {
		$cacheRoot = $this->normalisePath( $this->createTrackedTempDir( 'shield-full-readiness-cache-' ) );
		$tmpDir = $this->normalisePath( $this->createTrackedTempDir( 'shield-full-readiness-tmp-' ) );
		$this->fs = new CacheStoreTestFs();
		$this->registerCacheStoreWordPressFunctions( $this->fs, $tmpDir );
		$general = new SnapshotWpGeneral();
		$general->setTransient( 'apto-wphashes-api-available-routes', '' );
		ServicesState::installItems( [
			'service_request'   => new CacheStoreTestRequest( 1700000500 ),
			'service_wpfs'      => $this->fs,
			'service_wpgeneral' => $general,
			'service_wpplugins' => new SnapshotPlugins( $plugins ),
			'service_wpthemes'  => new SnapshotThemes( $themes ),
		] );
		$controller = CacheStoreTestController::install(
			new CacheStoreTestOptions(),
			new class {
				public array $properties = [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				];

				public function version() :string {
					return '20.0.0';
				}
			}
		);
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $cacheRoot );
		return $cacheRoot;
	}

	private function writePluginFile( SnapshotPluginVo $asset, string $contents ) :void {
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/'.$asset->file );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) && !@\mkdir( $dir, 0777, true ) && !\is_dir( $dir ) ) {
			throw new \RuntimeException( 'Failed to create plugin fixture directory.' );
		}
		if ( \file_put_contents( $path, $contents ) === false ) {
			throw new \RuntimeException( 'Failed to write plugin fixture.' );
		}
		$this->trackWrittenFixtureFile( $path );
	}

	private function loadStore( SnapshotPluginVo $asset ) :Store {
		return ( new Load() )
			->setAsset( $asset )
			->run();
	}

	private function writeStore( SnapshotPluginVo $asset, array $hashes ) :void {
		( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( $hashes )
			->setSnapMeta( [
				'unique_id'   => $asset->file,
				'version'     => $asset->Version,
				'live_hashes' => false,
			] )
			->save();
	}

	private function snapshotTree( string $root ) :array {
		$tree = [];
		if ( !\is_dir( $root ) ) {
			return $tree;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $item ) {
			$path = $this->normalisePath( $item->getPathname() );
			\clearstatcache( true, $path );
			$tree[ $path ] = $item->isDir()
				? [ 'type' => 'dir', 'mtime' => \filemtime( $path ) ]
				: [ 'type' => 'file', 'mtime' => \filemtime( $path ), 'contents' => \file_get_contents( $path ) ];
		}
		\ksort( $tree );
		return $tree;
	}

	private function seedMemoizationSentinels() :void {
		$this->setStaticProperty( Retrieve::class, 'sources', [ 'sentinel' => null ] );
		$this->setStaticProperty( AssetTrustResolver::class, 'plugins', [ 'sentinel' => null ] );
	}

	private function assertMemoizationSentinelsPresent() :void {
		$this->assertArrayHasKey( 'sentinel', $this->staticProperty( Retrieve::class, 'sources' ) );
		$this->assertArrayHasKey( 'sentinel', $this->staticProperty( AssetTrustResolver::class, 'plugins' ) );
	}

	private function setStaticProperty( string $class, string $property, array $value ) :void {
		$reflection = new \ReflectionProperty( $class, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $value );
	}

	private function staticProperty( string $class, string $property ) :array {
		$reflection = new \ReflectionProperty( $class, $property );
		$reflection->setAccessible( true );
		return $reflection->getValue();
	}

	private function normalisePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
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

class FullScanSnapshotReadinessHarness extends AssetCoordinator {

	public array $events = [];
	private array $loadOutcomes = [];
	private array $buildFailures = [];

	public function setLoadOutcomes( string $assetKey, array $outcomes ) :self {
		$this->loadOutcomes[ $assetKey ] = $outcomes;
		return $this;
	}

	public function setBuildFailure( string $assetKey ) :self {
		$this->buildFailures[ $assetKey ] = true;
		return $this;
	}

	protected function hasUsableSnapshot( $asset ) :bool {
		$key = $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet;
		$this->events[] = 'load:'.$key;
		$outcome = \array_shift( $this->loadOutcomes[ $key ] );
		if ( $outcome instanceof \Throwable ) {
			throw $outcome;
		}
		return $outcome === true;
	}

	protected function buildSnapshot( $asset ) :void {
		$key = $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet;
		$this->events[] = 'build:'.$key;
		if ( isset( $this->buildFailures[ $key ] ) ) {
			throw new \RuntimeException( 'Synthetic snapshot build failure.' );
		}
	}
}
