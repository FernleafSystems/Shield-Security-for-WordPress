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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\TouchAll;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\{
	SnapshotFs,
	SnapshotPlugins,
	SnapshotPluginVo,
	SnapshotThemes,
	SnapshotThemeVo
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestCacheDir,
	CacheStoreTestController,
	CacheStoreTestOptions
};

class TouchAllTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'is_main_network' )->justReturn( true );
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

		( new Afs() )->runHourlyCron();

		foreach ( [ 'active', 'inactive' ] as $installed ) {
			$this->assertFileExists( $stores[ $installed ]->getSnapStorePath() );
			$this->assertFileExists( $stores[ $installed ]->getSnapStoreMetaPath() );
			$this->assertGreaterThan( $oldTime, \filemtime( $stores[ $installed ]->getSnapStorePath() ) );
			$this->assertGreaterThan( $oldTime, \filemtime( $stores[ $installed ]->getSnapStoreMetaPath() ) );
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

		( new TouchAll() )->execute();

		\clearstatcache( true, $store->getSnapStorePath() );
		\clearstatcache( true, $store->getSnapStoreMetaPath() );
		$this->assertSame( $before[ $store->getSnapStorePath() ], \filemtime( $store->getSnapStorePath() ) );
		$this->assertSame( $before[ $store->getSnapStoreMetaPath() ], \filemtime( $store->getSnapStoreMetaPath() ) );
	}

	public function test_subnetwork_hourly_maintenance_does_not_touch_or_clean_shared_snapshots() :void {
		Functions\when( 'is_main_network' )->justReturn( false );
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

		( new Afs() )->runHourlyCron();

		foreach ( $stores as $store ) {
			foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
				\clearstatcache( true, $path );
				$this->assertFileExists( $path );
				$this->assertSame( $before[ $path ], \filemtime( $path ) );
			}
		}
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 * @param SnapshotThemeVo[]  $themes
	 */
	private function installEnvironment( array $plugins, array $themes ) :void {
		$root = \str_replace( '\\', '/', $this->createTrackedTempDir( 'shield-touch-all-root-' ) );
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest(),
			'service_wpfs'      => new SnapshotFs(),
			'service_wpplugins' => new SnapshotPlugins( $plugins ),
			'service_wpthemes'  => new SnapshotThemes( $themes ),
		] );
		$controller = CacheStoreTestController::install( new CacheStoreTestOptions() );
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $root );
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
}
