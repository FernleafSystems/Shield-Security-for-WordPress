<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots\StoreAction;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\TouchAll;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\{
	SnapshotPlugins,
	SnapshotPluginVo,
	SnapshotThemes,
	SnapshotThemeVo
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestCacheDir,
	CacheStoreTestController,
	CacheStoreTestFs,
	CacheStoreTestOptions
};
use FernleafSystems\Wordpress\Services\Core\Cron;

class TouchAllTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];
	private bool $isMainNetwork = true;
	private bool $isMainSite = true;
	private CacheStoreTestFs $fs;
	private array $options = [];
	private array $scheduled = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_main_network' )->alias( fn() :bool => $this->isMainNetwork );
		Functions\when( 'is_main_site' )->alias( fn() :bool => $this->isMainSite );
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			return \array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default;
		} );
		Functions\when( 'update_option' )->alias( function ( string $key, $value ) :bool {
			$this->options[ $key ] = $value;
			return true;
		} );
		Functions\when( 'wp_schedule_single_event' )->alias( function (
			int $timestamp,
			string $hook,
			array $args = []
		) :bool {
			$this->scheduled[] = \compact( 'timestamp', 'hook', 'args' );
			return true;
		} );
		Functions\when( 'path_join' )->alias(
			static fn( string $a, string $b ) :string => \str_replace(
				'\\',
				'/',
				\rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' )
			)
		);
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );
		Functions\when( 'wp_generate_password' )->alias(
			static fn( int $length, bool $specialChars = true ) :string => \substr( \str_repeat( 'a', $length ), 0, $length )
		);
		Functions\when( 'untrailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( \str_replace( '\\', '/', $path ), '/' )
		);
	}

	protected function tearDown() :void {
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_hourly_maintenance_touches_installed_snapshots_before_cleaning_stale_files() :void {
		$active = new SnapshotPluginVo( 'active-retained/plugin.php', '1.0.0' );
		$inactive = new SnapshotThemeVo( 'inactive-retained-theme', '2.0.0' );
		$inactive->active = false;
		$uninstalled = new SnapshotPluginVo( 'uninstalled-stale/plugin.php', '3.0.0' );
		$this->installEnvironment( [ $active ], [ $inactive ] );

		$stores = [
			'active'      => $this->writeStore( $active ),
			'inactive'    => $this->writeStore( $inactive ),
			'uninstalled' => $this->writeStore( $uninstalled ),
		];
		$oldTime = \time() - ( 2*\DAY_IN_SECONDS );
		foreach ( $stores as $store ) {
			foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
				$this->assertTrue( \touch( $path, $oldTime ) );
			}
		}

		$this->fs->compressedReadCounts = [];
		$this->fs->touchCounts = [];

		( new AssetCoordinator() )->runSnapshotMaintenance();

		foreach ( [ 'active', 'inactive' ] as $installed ) {
			$this->assertFileExists( $stores[ $installed ]->getSnapStorePath() );
			$this->assertFileExists( $stores[ $installed ]->getSnapStoreMetaPath() );
			$this->assertGreaterThan( $oldTime, \filemtime( $stores[ $installed ]->getSnapStorePath() ) );
			$this->assertGreaterThan( $oldTime, \filemtime( $stores[ $installed ]->getSnapStoreMetaPath() ) );
			$this->assertSame( 1, $this->compressedReads( $stores[ $installed ]->getSnapStoreMetaPath() ) );
			$this->assertSame( 1, $this->compressedReads( $stores[ $installed ]->getSnapStorePath() ) );
			$this->assertSame( 1, $this->touches( $stores[ $installed ]->getSnapStorePath() ) );
			$this->assertSame( 1, $this->touches( $stores[ $installed ]->getSnapStoreMetaPath() ) );
		}
		$this->assertFileDoesNotExist( $stores[ 'uninstalled' ]->getSnapStorePath() );
		$this->assertFileDoesNotExist( $stores[ 'uninstalled' ]->getSnapStoreMetaPath() );
	}

	public function test_touch_all_does_not_refresh_unusable_installed_snapshot() :void {
		$asset = new SnapshotPluginVo( 'unusable-installed/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $asset ], [] );
		$store = $this->writeStore( $asset, 'unsupported-hash' );
		$oldTime = \time() - 300;
		$before = [];
		foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
			$this->assertTrue( \touch( $path, $oldTime ) );
			\clearstatcache( true, $path );
			$before[ $path ] = \filemtime( $path );
		}

		$this->assertSame( [
			'has_unusable'      => true,
			'touches_succeeded' => true,
		], ( new TouchAll() )->run() );

		\clearstatcache( true, $store->getSnapStorePath() );
		\clearstatcache( true, $store->getSnapStoreMetaPath() );
		$this->assertSame( $before[ $store->getSnapStorePath() ], \filemtime( $store->getSnapStorePath() ) );
		$this->assertSame( $before[ $store->getSnapStoreMetaPath() ], \filemtime( $store->getSnapStoreMetaPath() ) );
	}

	/**
	 * @dataProvider provideNonOwnerTopologies
	 */
	public function test_non_owner_hourly_maintenance_does_not_touch_or_clean_shared_snapshots(
		bool $isMainNetwork,
		bool $isMainSite
	) :void {
		$this->isMainNetwork = $isMainNetwork;
		$this->isMainSite = $isMainSite;
		$installed = new SnapshotPluginVo( 'subnetwork-installed/plugin.php', '1.0.0' );
		$uninstalled = new SnapshotThemeVo( 'subnetwork-uninstalled', '2.0.0' );
		$this->installEnvironment( [ $installed ], [] );
		$stores = [
			$this->writeStore( $installed ),
			$this->writeStore( $uninstalled ),
		];
		$oldTime = \time() - ( 2*\DAY_IN_SECONDS );
		$before = [];
		foreach ( $stores as $store ) {
			foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
				$this->assertTrue( \touch( $path, $oldTime ) );
				\clearstatcache( true, $path );
				$before[ $path ] = \filemtime( $path );
			}
		}

		$this->fs->compressedReadCounts = [];
		$this->fs->touchCounts = [];

		( new AssetCoordinator() )->runSnapshotMaintenance();

		foreach ( $stores as $store ) {
			foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
				\clearstatcache( true, $path );
				$this->assertFileExists( $path );
				$this->assertSame( $before[ $path ], \filemtime( $path ) );
				$this->assertSame( 0, $this->compressedReads( $path ) );
				$this->assertSame( 0, $this->touches( $path ) );
			}
		}
	}

	public function provideNonOwnerTopologies() :array {
		return [
			'main network, secondary site' => [ true, false ],
			'secondary network, main site' => [ false, true ],
			'secondary network and site'   => [ false, false ],
		];
	}

	public function test_touch_failure_finishes_siblings_queues_unusable_and_prevents_stale_cleanup() :void {
		$first = new SnapshotPluginVo( 'first-installed/plugin.php', '1.0.0' );
		$missing = new SnapshotPluginVo( 'missing-installed/plugin.php', '1.5.0' );
		$second = new SnapshotThemeVo( 'second-installed', '2.0.0' );
		$uninstalled = new SnapshotPluginVo( 'uninstalled-stale/plugin.php', '3.0.0' );
		$this->installEnvironment( [ $first, $missing ], [ $second ] );
		$firstStore = $this->writeStore( $first );
		$secondStore = $this->writeStore( $second );
		$uninstalledStore = $this->writeStore( $uninstalled );
		$oldTime = \time() - ( 2*\DAY_IN_SECONDS );
		foreach ( [ $uninstalledStore->getSnapStorePath(), $uninstalledStore->getSnapStoreMetaPath() ] as $path ) {
			$this->assertTrue( \touch( $path, $oldTime ) );
		}
		$this->fs->failTouch( $firstStore->getSnapStorePath() );
		$this->fs->touchCounts = [];

		( new AssetCoordinator() )->runSnapshotMaintenance();

		$this->assertSame( 1, $this->touches( $firstStore->getSnapStorePath() ) );
		$this->assertSame( 1, $this->touches( $firstStore->getSnapStoreMetaPath() ) );
		$this->assertSame( 1, $this->touches( $secondStore->getSnapStorePath() ) );
		$this->assertSame( 1, $this->touches( $secondStore->getSnapStoreMetaPath() ) );
		$this->assertTrue(
			$this->options[ 'icwp-wpsf-asset_coordinator_state' ][ 'build_missing_snapshots' ] ?? false
		);
		$this->assertCount( 1, $this->scheduled );
		$this->assertSame( 'icwp-wpsf-asset_coordinator', $this->scheduled[ 0 ][ 'hook' ] );
		$this->assertFileExists( $uninstalledStore->getSnapStorePath() );
		$this->assertFileExists( $uninstalledStore->getSnapStoreMetaPath() );
	}

	public function test_completed_pass_queues_unusable_snapshot_and_cleans_uninstalled_stale_snapshot() :void {
		$unusable = new SnapshotPluginVo( 'unusable-installed/plugin.php', '1.0.0' );
		$uninstalled = new SnapshotThemeVo( 'uninstalled-stale', '2.0.0' );
		$this->installEnvironment( [ $unusable ], [] );
		$this->writeStore( $unusable, 'unsupported-hash' );
		$uninstalledStore = $this->writeStore( $uninstalled );
		$oldTime = \time() - ( 2*\DAY_IN_SECONDS );
		foreach ( [ $uninstalledStore->getSnapStorePath(), $uninstalledStore->getSnapStoreMetaPath() ] as $path ) {
			$this->assertTrue( \touch( $path, $oldTime ) );
		}

		( new AssetCoordinator() )->runSnapshotMaintenance();

		$this->assertTrue(
			$this->options[ 'icwp-wpsf-asset_coordinator_state' ][ 'build_missing_snapshots' ] ?? false
		);
		$this->assertFileDoesNotExist( $uninstalledStore->getSnapStorePath() );
		$this->assertFileDoesNotExist( $uninstalledStore->getSnapStoreMetaPath() );
	}

	public function test_existing_build_intent_does_not_skip_hourly_validation_and_touching() :void {
		$installed = new SnapshotPluginVo( 'pending-installed/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $installed ], [] );
		$store = $this->writeStore( $installed );
		$this->options[ 'icwp-wpsf-asset_coordinator_state' ] = [
			'build_missing_snapshots' => true,
		];
		$this->fs->compressedReadCounts = [];
		$this->fs->touchCounts = [];

		( new AssetCoordinator() )->runSnapshotMaintenance();

		$this->assertTrue(
			$this->options[ 'icwp-wpsf-asset_coordinator_state' ][ 'build_missing_snapshots' ]
		);
		$this->assertSame( 1, $this->compressedReads( $store->getSnapStoreMetaPath() ) );
		$this->assertSame( 1, $this->compressedReads( $store->getSnapStorePath() ) );
		$this->assertSame( 1, $this->touches( $store->getSnapStorePath() ) );
		$this->assertSame( 1, $this->touches( $store->getSnapStoreMetaPath() ) );
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 * @param SnapshotThemeVo[]  $themes
	 */
	private function installEnvironment( array $plugins, array $themes ) :void {
		$root = \str_replace( '\\', '/', $this->createTrackedTempDir( 'shield-touch-all-root-' ) );
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest(),
			'service_wpfs'      => $this->fs = new CacheStoreTestFs(),
			'service_wpcron'    => new TouchAllTestCron(),
			'service_wpplugins' => new SnapshotPlugins( $plugins ),
			'service_wpthemes'  => new SnapshotThemes( $themes ),
		] );
		$controller = CacheStoreTestController::install( new CacheStoreTestOptions() );
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $root );
		$controller->is_my_upgrade = false;
		$controller->plugin_deleting = false;
	}

	/**
	 * @param SnapshotPluginVo|SnapshotThemeVo $asset
	 */
	private function writeStore( $asset, string $hash = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ) :Store {
		return ( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( [
				'file.php' => $hash,
			] )
			->setSnapMeta( [
				'version'   => $asset->Version,
				'unique_id' => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			] )
			->save();
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

	private function compressedReads( string $path ) :int {
		return $this->fs->compressedReadCounts[ $this->fs->normalise( $path ) ] ?? 0;
	}

	private function touches( string $path ) :int {
		return $this->fs->touchCounts[ $this->fs->normalise( $path ) ] ?? 0;
	}
}

class TouchAllTestCron extends Cron {

	public function getCrons( bool $onlyReadyToRunNow = false ) :array {
		unset( $onlyReadyToRunNow );
		return [];
	}
}
