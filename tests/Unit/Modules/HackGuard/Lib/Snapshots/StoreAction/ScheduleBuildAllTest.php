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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\ScheduleBuildAll;
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
	CacheStoreTestFs,
	CacheStoreTestOptions,
	CacheStoreTestRequest,
	CacheStoreWordPressFunctions
};

function error_log( string $message ) :bool {
	ScheduleBuildAllTest::$capturedErrorLogs[] = $message;
	return true;
}

class ScheduleBuildAllTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	public static array $capturedErrorLogs = [];

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	private array $fixtureFiles = [];

	protected function setUp() :void {
		parent::setUp();
		self::$capturedErrorLogs = [];
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalizePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalizePath( $path ) );
		Functions\when( 'wp_generate_password' )->alias(
			static fn( int $length, bool $specialChars = true ) :string => \substr( \str_repeat( 'a', $length ), 0, $length )
		);
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ) );
		Functions\when( 'trailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ).'/' );
	}

	protected function tearDown() :void {
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		foreach ( \array_reverse( $this->fixtureFiles ) as $file ) {
			@\unlink( $file );
			@\rmdir( \dirname( $file ) );
		}
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_verified_current_snapshot_excludes_asset_from_build_list() :void {
		$asset = new SnapshotPluginVo( 'snapshot-current/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $asset ] );
		$this->writeStore( $asset, [
			'plugin.php' => 'current-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-current/plugin.php',
		] );

		$this->assertSame( [], $this->assetKeysThatNeedBuilt() );
	}

	public function test_missing_snapshot_includes_asset_in_build_list() :void {
		$asset = new SnapshotPluginVo( 'snapshot-missing/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $asset ] );

		$this->assertSame( [ 'snapshot-missing/plugin.php' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_loadable_snapshot_with_mismatched_version_meta_includes_asset_in_build_list() :void {
		$asset = new SnapshotPluginVo( 'snapshot-stale/plugin.php', '2.0.0' );
		$this->installEnvironment( [ $asset ] );
		$this->writeStore( $asset, [
			'plugin.php' => 'old-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-stale/plugin.php',
		] );

		$this->assertSame( [ 'snapshot-stale/plugin.php' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_verified_current_theme_snapshot_excludes_asset_from_build_list() :void {
		$asset = new SnapshotThemeVo( 'snapshot-current-theme', '1.0.0' );
		$this->installEnvironment( [], [ $asset ] );
		$this->writeStore( $asset, [
			'style.css' => 'current-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-current-theme',
		] );

		$this->assertSame( [], $this->assetKeysThatNeedBuilt() );
	}

	public function test_missing_current_theme_snapshot_includes_asset_in_build_list() :void {
		$asset = new SnapshotThemeVo( 'snapshot-missing-theme', '1.0.0' );
		$this->installEnvironment( [], [ $asset ] );

		$this->assertSame( [ 'snapshot-missing-theme' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_loadable_current_theme_snapshot_with_mismatched_version_meta_includes_asset_in_build_list() :void {
		$asset = new SnapshotThemeVo( 'snapshot-stale-theme', '2.0.0' );
		$this->installEnvironment( [], [ $asset ] );
		$this->writeStore( $asset, [
			'style.css' => 'old-hash',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-stale-theme',
		] );

		$this->assertSame( [ 'snapshot-stale-theme' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_discovery_does_not_log_missing_snapshot_errors() :void {
		$asset = new SnapshotPluginVo( 'snapshot-missing-no-log/plugin.php', '1.0.0' );
		$this->installEnvironment( [ $asset ] );

		$this->assertSame( [ 'snapshot-missing-no-log/plugin.php' ], $this->assetKeysThatNeedBuilt() );
		$this->assertSame( [], self::$capturedErrorLogs );
	}

	public function test_discovery_does_not_create_hash_dir_for_missing_snapshot() :void {
		$asset = new SnapshotPluginVo( 'snapshot-missing-no-create/plugin.php', '1.0.0' );
		$root = $this->makeTempDir( 'root' );
		$this->installBuildEnvironment( [ $asset ], $root );

		$this->assertSame( [ 'snapshot-missing-no-create/plugin.php' ], $this->assetKeysThatNeedBuilt() );
		$this->assertSame( [], \glob( $root.'/ptguard-*' ) ?: [] );
		$this->assertFileDoesNotExist( $root.'/ptguard-active.txt' );
	}

	public function test_build_writes_and_loads_under_selected_uploads_root_only() :void {
		$asset = new SnapshotPluginVo( 'snapshot-build-root/plugin.php', '1.0.0' );
		$uploadsRoot = $this->makeTempDir( 'uploads-root' );
		$cacheRoot = $this->makeTempDir( 'cache-root' );
		$this->installBuildEnvironment( [ $asset ], $uploadsRoot );
		$this->writeFile( WP_PLUGIN_DIR.'/'.$asset->file, "<?php\n// snapshot build root\n" );
		$this->mkdir( $cacheRoot.'/ptguard-cccccccccccccccc' );

		$this->invokeBuild();

		$this->assertNotSame( [], \glob( $uploadsRoot.'/ptguard-*/plugins/snapshot-build-root-1.0.0.txt' ) ?: [] );
		$this->assertSame( [], \glob( $cacheRoot.'/ptguard-*/plugins/snapshot-build-root-1.0.0.txt' ) ?: [] );
		$this->assertSame( [], $this->assetKeysThatNeedBuilt() );
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 * @param SnapshotThemeVo[]  $themes
	 */
	private function installEnvironment( array $plugins, array $themes = [] ) :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000500 ),
			'service_wpfs'      => new SnapshotFs(),
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
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 */
	private function installBuildEnvironment( array $plugins, string $cacheRoot ) :void {
		$this->resetHashesStorageDir();
		$fs = new CacheStoreTestFs();
		$this->registerCacheStoreWordPressFunctions( $fs, $this->makeTempDir( 'tmp' ) );
		ServicesState::installItems( [
			'service_request'   => new CacheStoreTestRequest( 1700000500 ),
			'service_wpfs'      => $fs,
			'service_wpplugins' => new SnapshotPlugins( $plugins ),
			'service_wpthemes'  => new SnapshotThemes( [] ),
		] );
		$controller = CacheStoreTestController::install(
			new CacheStoreTestOptions(),
			new class {
				public array $properties = [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				];

				public array $paths = [
					'cache' => 'shield',
				];

				public function version() :string {
					return '20.0.0';
				}
			}
		);
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $cacheRoot );
		$controller->comps = (object)[
			'license' => new class {
				public function hasValidWorkingLicense() :bool {
					return false;
				}
			},
		];
	}

	/**
	 * @param SnapshotPluginVo|SnapshotThemeVo $asset
	 */
	private function writeStore( $asset, array $hashes, array $meta ) :void {
		( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( $hashes )
			->setSnapMeta( $meta )
			->save();
	}

	/**
	 * @return string[]
	 */
	private function assetKeysThatNeedBuilt() :array {
		$method = new \ReflectionMethod( ScheduleBuildAll::class, 'getAssetsThatNeedBuilt' );
		$method->setAccessible( true );

		return \array_values( \array_map(
			static fn( $asset ) :string => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			$method->invoke( new ScheduleBuildAll() )
		) );
	}

	private function invokeBuild() :void {
		$method = new \ReflectionMethod( ScheduleBuildAll::class, 'build' );
		$method->setAccessible( true );
		$method->invoke( new ScheduleBuildAll() );
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

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normalizePath( \sys_get_temp_dir().'/shield-schedule-build-'.$suffix.'-'.\uniqid() );
		@mkdir( $dir, 0777, true );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}

	private function mkdir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
	}

	private function writeFile( string $path, string $content ) :void {
		$path = $this->normalizePath( $path );
		$this->mkdir( \dirname( $path ) );
		\file_put_contents( $path, $content );
		$this->fixtureFiles[] = $path;
	}

	private function removeDir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}
		@rmdir( $dir );
	}
}
