<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Exceptions\AssetHashesNotFound,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\Load;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange\Cleanup;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest,
	WrittenFixtureFiles
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\{
	SnapshotFs,
	SnapshotPlugins,
	SnapshotPluginVo,
	SnapshotThemes,
	SnapshotWpTheme,
	SnapshotThemeVo,
	SnapshotWpGeneral
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestCacheDir,
	CacheStoreTestFs,
	CacheStoreTestRequest
};
use FernleafSystems\Wordpress\Services\Core\{
	CoreFileHashes,
	Db,
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class AssetChangeCleanupTest extends BaseUnitTest {

	use WrittenFixtureFiles;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_sql' )->alias( static fn( string $value ) :string => \str_replace( "'", "\\'", $value ) );
		Functions\when( 'is_wp_error' )->alias( static fn( $maybeError ) :bool => $maybeError instanceof \WP_Error );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ) :string {
				return empty( $args ) ? $url : $url.'?'.\http_build_query( $args );
			}
		);
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalizePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'get_theme_root' )->alias( fn() :string => $this->normalizePath( WP_CONTENT_DIR.'/themes' ) );
		Functions\when( 'trailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ).'/' );
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ) );
		Functions\when( 'wp_http_validate_url' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_generate_password' )->alias(
			static fn( int $length, bool $specialChars = true ) :string => \substr( \str_repeat( 'a', $length ), 0, $length )
		);
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalizePath( $path ) );
		Functions\when( 'wp_remote_request' )->alias(
			static fn() :array => [
				'body'     => \json_encode( [ 'routes_regex' => '' ] ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
			]
		);
	}

	protected function tearDown() :void {
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->removeWrittenFixtureFiles();
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_cleanup_starts_scan_without_pre_resolving_findings() :void {
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		ServicesState::installItems( [
			'service_corefilehashes' => new AssetChangeCleanupCoreHashes( true ),
			'service_request'        => new UnitTestRequest( [], '127.0.0.1', 1700000100 ),
			'service_wpdb'           => $wpDb,
		] );

		( new Cleanup() )->run( 'core', 'core' );

		$this->assertSame( [ [ 'core', 'core' ] ], $scans->startedAssets );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
	}

	public function test_cleanup_reschedules_once_and_does_not_scan_when_readiness_fails() :void {
		$scheduled = [];
		$this->installCronMocks( $scheduled );
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		ServicesState::installItems( [
			'service_corefilehashes' => new AssetChangeCleanupCoreHashes( false ),
			'service_request'        => new UnitTestRequest( [], '127.0.0.1', 1700000200 ),
			'service_wpdb'           => $wpDb,
		] );

		( new Cleanup() )->run( 'core', 'core' );

		$this->assertSame( [], $scans->startedAssets );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
		$this->assertSame( [
			[
				'timestamp' => 1700000260,
				'hook'      => 'icwp-wpsf-afs_asset_change_cleanup',
				'args'      => [ 'core', 'core', 1 ],
			],
		], $scheduled );
	}

	public function test_cleanup_does_not_reschedule_after_retry_limit_when_readiness_fails() :void {
		$scheduled = [];
		$this->installCronMocks( $scheduled );
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		ServicesState::installItems( [
			'service_corefilehashes' => new AssetChangeCleanupCoreHashes( false ),
			'service_request'        => new UnitTestRequest( [], '127.0.0.1', 1700000200 ),
			'service_wpdb'           => $wpDb,
		] );

		( new Cleanup() )->run( 'core', 'core', 1 );

		$this->assertSame( [], $scans->startedAssets );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
		$this->assertSame( [], $scheduled );
	}

	/**
	 * @dataProvider providePresentAssetReadinessFailures
	 */
	public function test_present_plugin_or_theme_readiness_failure_retries_once_without_scanning(
		string $assetType,
		string $assetKey,
		string $version,
		int $retry,
		array $expectedSchedule
	) :void {
		$scheduled = [];
		$this->installCronMocks( $scheduled );
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		$plugins = [];
		$themes = [];
		if ( $assetType === 'plugin' ) {
			$plugins[] = new SnapshotPluginVo( $assetKey, $version );
		}
		else {
			$themes[] = new SnapshotThemeVo( $assetKey, $version );
		}
		$this->installSnapshotEnvironment(
			new SnapshotPlugins( $plugins ),
			new SnapshotThemes( $themes )
		);
		ServicesState::mergeItems( [
			'service_wpdb' => $wpDb,
		] );

		( new Cleanup() )->run( $assetType, $assetKey, $retry );

		$this->assertSame( [], $scans->startedAssets );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
		$this->assertSame( $expectedSchedule, $scheduled );
	}

	public function test_plugin_cleanup_builds_current_local_snapshot_then_starts_scoped_scan() :void {
		$plugin = new SnapshotPluginVo( 'cleanup-plugin/cleanup-plugin.php', '2.0.0' );
		$this->writeFile( WP_PLUGIN_DIR.'/'.$plugin->file, "<?php\n" );

		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		$this->installSnapshotEnvironment(
			new SnapshotPlugins( [ $plugin ] ),
			new SnapshotThemes( [] )
		);
		$this->writeSnapshotStore( $plugin, [
			'cleanup-plugin.php' => \md5( 'old-same-version-content' ),
		], [
			'version'   => '2.0.0',
			'unique_id' => $plugin->file,
		] );
		ServicesState::mergeItems( [
			'service_wpdb' => $wpDb,
		] );

		( new Cleanup() )->run( 'plugin', $plugin->file );

		$this->assertSame( [ [ 'plugin', $plugin->file ] ], $scans->startedAssets );
		$store = ( new Load() )
			->setAsset( $plugin )
			->run();
		$this->assertTrue( $store->verify() );
		$snapData = $store->getSnapData();
		$this->assertNotEmpty( $snapData );
		$this->assertArrayHasKey( 'cleanup-plugin.php', $snapData );
		$this->assertSame( \md5_file( WP_PLUGIN_DIR.'/'.$plugin->file ), $snapData[ 'cleanup-plugin.php' ] );
		$this->assertSame( '2.0.0', $store->getSnapMeta()[ 'version' ] );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
	}

	public function test_plugin_cleanup_resets_same_request_hash_miss_after_snapshot_build() :void {
		$plugin = new SnapshotPluginVo( 'cleanup-reset-plugin/cleanup-reset.php', '2.0.0' );
		$path = WP_PLUGIN_DIR.'/'.$plugin->file;
		$this->writeFile( $path, "<?php\n" );

		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		$this->installSnapshotEnvironment(
			new SnapshotPlugins( [ $plugin ] ),
			new SnapshotThemes( [] )
		);
		ServicesState::mergeItems( [
			'service_wpdb' => new AssetChangeCleanupWpDb(),
		] );

		try {
			( new Retrieve() )->byVO( $plugin );
			$this->fail( 'Expected hash lookup to miss before cleanup builds the snapshot.' );
		}
		catch ( AssetHashesNotFound $e ) {
			$this->assertInstanceOf( AssetHashesNotFound::class, $e );
		}

		$scanCallbackRan = false;
		$scans->beforeStart = function ( string $assetType, string $assetKey ) use ( &$scanCallbackRan, $plugin, $path ) :void {
			$scanCallbackRan = true;
			$this->assertSame( 'plugin', $assetType );
			$this->assertSame( $plugin->file, $assetKey );
			$this->assertSame( [
				'cleanup-reset.php' => \md5_file( $path ),
			], ( new Retrieve() )->byVO( $plugin ) );
		};

		( new Cleanup() )->run( 'plugin', $plugin->file );

		$this->assertSame( [ [ 'plugin', $plugin->file ] ], $scans->startedAssets );
		$this->assertTrue( $scanCallbackRan );
	}

	public function test_plugin_cleanup_resets_cached_asset_context_before_scoped_scan() :void {
		$versionOne = new SnapshotPluginVo( 'cleanup-context-plugin/context.php', '1.0.0' );
		$versionTwo = new SnapshotPluginVo( 'cleanup-context-plugin/context.php', '2.0.0' );
		$path = WP_PLUGIN_DIR.'/'.$versionOne->file;
		$this->writeFile( $path, "<?php\n" );

		$plugins = new AssetChangeCleanupMutablePlugins( [ $versionOne ] );
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		$this->installSnapshotEnvironment(
			$plugins,
			new SnapshotThemes( [] )
		);
		ServicesState::mergeItems( [
			'service_wpdb' => new AssetChangeCleanupWpDb(),
		] );

		$resolver = new AssetTrustResolver();
		$this->assertSame( '1.0.0', $resolver->resolveContext( $path )->assetVersion );
		$plugins->setPlugins( [ $versionTwo ] );

		$scanCallbackRan = false;
		$scans->beforeStart = function ( string $assetType, string $assetKey ) use ( &$scanCallbackRan, $path, $versionTwo ) :void {
			$scanCallbackRan = true;
			$this->assertSame( 'plugin', $assetType );
			$this->assertSame( $versionTwo->file, $assetKey );
			$this->assertSame( '2.0.0', ( new AssetTrustResolver() )->resolveContext( $path )->assetVersion );
		};

		( new Cleanup() )->run( 'plugin', $versionTwo->file );

		$this->assertSame( [ [ 'plugin', $versionTwo->file ] ], $scans->startedAssets );
		$this->assertTrue( $scanCallbackRan );
	}

	public function test_plugin_cleanup_uses_selected_snapshot_root() :void {
		$plugin = new SnapshotPluginVo( 'cleanup-root-plugin/cleanup-root-plugin.php', '2.1.0' );
		$this->writeFile( WP_PLUGIN_DIR.'/'.$plugin->file, "<?php\n" );
		$uploadsRoot = $this->makeTempDir( 'uploads-root' );
		$cacheRoot = $this->makeTempDir( 'cache-root' );
		$this->makeDir( $cacheRoot.'/ptguard-cccccccccccccccc' );

		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		$this->installSnapshotEnvironmentWithCacheRoot(
			new SnapshotPlugins( [ $plugin ] ),
			new SnapshotThemes( [] ),
			$uploadsRoot
		);
		ServicesState::mergeItems( [
			'service_wpdb' => new AssetChangeCleanupWpDb(),
		] );

		( new Cleanup() )->run( 'plugin', $plugin->file );

		$this->assertSame( [ [ 'plugin', $plugin->file ] ], $scans->startedAssets );
		$this->assertNotSame( [], \glob( $uploadsRoot.'/ptguard-*/plugins/cleanup-root-plugin-2.1.0.txt' ) ?: [] );
		$this->assertSame( [], \glob( $cacheRoot.'/ptguard-*/plugins/cleanup-root-plugin-2.1.0.txt' ) ?: [] );
	}

	public function test_theme_cleanup_builds_current_local_snapshot_then_starts_scoped_scan() :void {
		$theme = new SnapshotThemeVo( 'cleanup-theme', '3.1.0' );
		$this->writeFile( WP_CONTENT_DIR.'/themes/'.$theme->stylesheet.'/style.php', "<?php\n" );

		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		$this->installSnapshotEnvironment(
			new SnapshotPlugins( [] ),
			new SnapshotThemes( [ $theme ] )
		);
		$this->writeSnapshotStore( $theme, [
			'style.php' => \md5( 'old-same-version-content' ),
		], [
			'version'   => '3.1.0',
			'unique_id' => $theme->stylesheet,
		] );
		ServicesState::mergeItems( [
			'service_wpdb' => new AssetChangeCleanupWpDb(),
		] );

		( new Cleanup() )->run( 'theme', $theme->stylesheet );

		$this->assertSame( [ [ 'theme', $theme->stylesheet ] ], $scans->startedAssets );
		$store = ( new Load() )
			->setAsset( $theme )
			->run();
		$this->assertTrue( $store->verify() );
		$snapData = $store->getSnapData();
		$this->assertNotEmpty( $snapData );
		$this->assertArrayHasKey( 'style.php', $snapData );
		$this->assertSame( \md5_file( WP_CONTENT_DIR.'/themes/'.$theme->stylesheet.'/style.php' ), $snapData[ 'style.php' ] );
		$this->assertSame( '3.1.0', $store->getSnapMeta()[ 'version' ] );
	}

	public function test_missing_plugin_or_theme_asset_still_starts_scoped_scan_after_cleanup() :void {
		$scans = new AssetChangeCleanupScans();
		$plugin = new SnapshotPluginVo( 'deleted-plugin/deleted.php', '1.0.0' );
		$theme = new SnapshotThemeVo( 'deleted-theme', '1.0.0' );
		$plugins = new AssetChangeCleanupMutablePlugins( [ $plugin ] );
		$themes = new AssetChangeCleanupMutableThemes( [ $theme ] );
		$pluginPath = WP_PLUGIN_DIR.'/'.$plugin->file;
		$themePath = WP_CONTENT_DIR.'/themes/'.$theme->stylesheet.'/style.php';
		$this->installController( $scans );
		$this->installSnapshotEnvironment(
			$plugins,
			$themes
		);
		ServicesState::mergeItems( [
			'service_wpdb' => new AssetChangeCleanupWpDb(),
		] );

		$resolver = new AssetTrustResolver();
		$this->assertSame( '1.0.0', $resolver->resolveContext( $pluginPath )->assetVersion );
		$this->assertSame( '1.0.0', $resolver->resolveContext( $themePath )->assetVersion );
		$plugins->setPlugins( [] );
		$themes->setThemes( [] );

		$callbackChecks = [];
		$scans->beforeStart = function ( string $assetType ) use ( &$callbackChecks, $pluginPath, $themePath ) :void {
			if ( $assetType === 'plugin' ) {
				$this->assertSame( '1.0.0', ( new AssetTrustResolver() )->resolveContext( $pluginPath )->assetVersion );
				$callbackChecks[] = 'plugin';
			}
			if ( $assetType === 'theme' ) {
				$this->assertSame( '1.0.0', ( new AssetTrustResolver() )->resolveContext( $themePath )->assetVersion );
				$callbackChecks[] = 'theme';
			}
		};

		( new Cleanup() )->run( 'plugin', 'deleted-plugin/deleted.php' );
		( new Cleanup() )->run( 'theme', 'deleted-theme' );

		$this->assertSame( [
			[ 'plugin', 'deleted-plugin/deleted.php' ],
			[ 'theme', 'deleted-theme' ],
		], $scans->startedAssets );
		$this->assertSame( [ 'plugin', 'theme' ], $callbackChecks );
	}

	public function test_schedule_preserves_plugin_snapshot_before_pending_cleanup_check() :void {
		$plugin = new SnapshotPluginVo( 'pending-plugin/pending.php', '1.0.0' );
		$scheduled = [
			[
				'timestamp' => 1700000460,
				'hook'      => 'icwp-wpsf-afs_asset_change_cleanup',
				'args'      => [ 'plugin', $plugin->file, 0 ],
			],
		];
		$this->installCronMocks( $scheduled );
		$this->installController( new AssetChangeCleanupScans() );
		$this->installSnapshotEnvironment(
			new SnapshotPlugins( [ $plugin ] ),
			new SnapshotThemes( [] )
		);
		$expectedData = [
			'pending.php' => \md5( 'old-same-version-content' ),
		];
		$expectedMeta = [
			'version'   => '1.0.0',
			'unique_id' => $plugin->file,
		];
		$this->writeSnapshotStore( $plugin, $expectedData, $expectedMeta );

		$this->assertTrue( ( new Cleanup() )->schedule( 'plugin', $plugin->file ) );

		$this->assertSame( [
			[
				'timestamp' => 1700000460,
				'hook'      => 'icwp-wpsf-afs_asset_change_cleanup',
				'args'      => [ 'plugin', $plugin->file, 0 ],
			],
		], $scheduled );
		$this->assertSnapshotStorePreserved( $plugin, $expectedData, $expectedMeta );
	}

	public function test_schedule_preserves_theme_snapshot_before_pending_cleanup_check() :void {
		$theme = new SnapshotThemeVo( 'pending-theme', '1.0.0' );
		$scheduled = [
			[
				'timestamp' => 1700000460,
				'hook'      => 'icwp-wpsf-afs_asset_change_cleanup',
				'args'      => [ 'theme', $theme->stylesheet, 0 ],
			],
		];
		$this->installCronMocks( $scheduled );
		$this->installController( new AssetChangeCleanupScans() );
		$this->installSnapshotEnvironment(
			new SnapshotPlugins( [] ),
			new SnapshotThemes( [ $theme ] )
		);
		$expectedData = [
			'style.php' => \md5( 'old-same-version-content' ),
		];
		$expectedMeta = [
			'version'   => '1.0.0',
			'unique_id' => $theme->stylesheet,
		];
		$this->writeSnapshotStore( $theme, $expectedData, $expectedMeta );

		$this->assertTrue( ( new Cleanup() )->schedule( 'theme', $theme->stylesheet ) );

		$this->assertSame( [
			[
				'timestamp' => 1700000460,
				'hook'      => 'icwp-wpsf-afs_asset_change_cleanup',
				'args'      => [ 'theme', $theme->stylesheet, 0 ],
			],
		], $scheduled );
		$this->assertSnapshotStorePreserved( $theme, $expectedData, $expectedMeta );
	}

	public function test_schedule_coalesces_only_matching_pending_asset_cleanup() :void {
		$scheduled = [];
		$this->installController( new AssetChangeCleanupScans() );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000300 ),
		] );
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( string $hook, array $args = [] ) {
				unset( $hook );
				return \in_array(
					$args,
					[
						[ 'plugin', 'akismet/akismet.php', 0 ],
						[ 'theme', 'twentytwentyfour', 1 ],
					],
					true
				) ? 1700000360 : false;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			static function ( int $timestamp, string $hook, array $args = [] ) use ( &$scheduled ) :bool {
				$scheduled[] = [ $timestamp, $hook, $args ];
				return true;
			}
		);

		$this->assertTrue( ( new Cleanup() )->schedule( 'plugin', 'akismet/akismet.php' ) );
		$this->assertTrue( ( new Cleanup() )->schedule( 'theme', 'twentytwentyfour' ) );
		$this->assertTrue( ( new Cleanup() )->schedule( 'plugin', 'hello-dolly/hello.php' ) );
		$this->assertSame( [
			[
				1700000360,
				'icwp-wpsf-afs_asset_change_cleanup',
				[ 'plugin', 'hello-dolly/hello.php', 0 ],
			],
		], $scheduled );
	}

	public function test_invalid_asset_inputs_do_not_touch_sql_scan_or_cron() :void {
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000300 ),
			'service_wpdb'    => $wpDb,
		] );
		Functions\expect( 'wp_next_scheduled' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		$cleanup = new Cleanup();

		$this->assertFalse( $cleanup->schedule( 'unsupported', 'whatever' ) );
		$this->assertFalse( $cleanup->schedule( 'plugin', '' ) );
		$this->assertFalse( $cleanup->schedule( 'theme', '' ) );
		$cleanup->run( 'unsupported', 'whatever' );
		$cleanup->run( 'plugin', '' );
		$cleanup->run( 'theme', '' );

		$this->assertSame( [], $wpDb->queries );
		$this->assertSame( [], $scans->startedAssets );
		$this->assertSame( 0, $scans->memoizationResets );
	}

	public function providePresentAssetReadinessFailures() :array {
		return [
			'plugin retry 0' => [
				'plugin',
				'cleanup-unready-plugin/cleanup-unready.php',
				'9.9.0',
				0,
				[
					[
						'timestamp' => 1700000460,
						'hook'      => 'icwp-wpsf-afs_asset_change_cleanup',
						'args'      => [ 'plugin', 'cleanup-unready-plugin/cleanup-unready.php', 1 ],
					],
				],
			],
			'plugin retry 1' => [
				'plugin',
				'cleanup-unready-plugin/cleanup-unready.php',
				'9.9.0',
				1,
				[],
			],
			'theme retry 0'  => [
				'theme',
				'cleanup-unready-theme',
				'9.9.0',
				0,
				[
					[
						'timestamp' => 1700000460,
						'hook'      => 'icwp-wpsf-afs_asset_change_cleanup',
						'args'      => [ 'theme', 'cleanup-unready-theme', 1 ],
					],
				],
			],
			'theme retry 1'  => [
				'theme',
				'cleanup-unready-theme',
				'9.9.0',
				1,
				[],
			],
		];
	}

	private function installController( AssetChangeCleanupScans $scans ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = new class {
			public array $properties = [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
			];

			public function version() :string {
				return '20.0.0';
			}
		};
		$controller->comps = (object)[
			'scans' => $scans,
		];
		$controller->caps = new class {
			public function canScanPluginsThemesRemote() :bool {
				return false;
			}
		};
		$controller->db_con = (object)[
			'scan_result_items'     => new AssetChangeCleanupTable( 'shield_scan_result_items' ),
			'scan_result_item_meta' => new AssetChangeCleanupTable( 'shield_scan_result_item_meta' ),
		];

		PluginControllerInstaller::install( $controller );
	}

	private function installSnapshotEnvironment( Plugins $plugins, Themes $themes ) :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		ServicesState::mergeItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000400 ),
			'service_wpfs'      => new AssetChangeCleanupSnapshotFs(),
			'service_wpgeneral' => new SnapshotWpGeneral(),
			'service_wpplugins' => $plugins,
			'service_wpthemes'  => $themes,
		] );
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin
			->getController()
			->cache_dir_handler = new CacheStoreTestCacheDir( $cacheRoot );
	}

	private function installSnapshotEnvironmentWithCacheRoot( Plugins $plugins, Themes $themes, string $cacheRoot ) :void {
		$this->resetHashesStorageDir();
		ServicesState::mergeItems( [
			'service_request'   => new CacheStoreTestRequest( 1700000400 ),
			'service_wpfs'      => new CacheStoreTestFs(),
			'service_wpgeneral' => new SnapshotWpGeneral(),
			'service_wpplugins' => $plugins,
			'service_wpthemes'  => $themes,
		] );
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin
			->getController()
			->cache_dir_handler = new CacheStoreTestCacheDir( $cacheRoot );
	}

	private function installCronMocks( array &$scheduled ) :void {
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( string $hook, array $args = [] ) use ( &$scheduled ) {
				foreach ( $scheduled as $event ) {
					if ( $event[ 'hook' ] === $hook && $event[ 'args' ] === $args ) {
						return $event[ 'timestamp' ];
					}
				}
				return false;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			static function ( int $timestamp, string $hook, array $args = [] ) use ( &$scheduled ) :bool {
				$scheduled[] = [
					'timestamp' => $timestamp,
					'hook'      => $hook,
					'args'      => $args,
				];
				return true;
			}
		);
	}

	private function assertSnapshotStorePreserved( $asset, array $expectedData, array $expectedMeta ) :void {
		$store = ( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir( false ) );
		foreach ( [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ] as $path ) {
			$this->assertFileExists( $path );
		}
		$this->assertTrue( $store->verify() );
		$this->assertSame( $expectedData, $store->getSnapData() );
		$this->assertSame( $expectedMeta, $store->getSnapMeta() );
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

	private function writeFile( string $path, string $content ) :void {
		$path = $this->normalizePath( $path );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true );
		}
		\file_put_contents( $path, $content );
		$this->trackWrittenFixtureFile( $path );
	}

	private function writeSnapshotStore( $asset, array $hashes, array $meta ) :void {
		( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( $hashes )
			->setSnapMeta( $meta )
			->save();
	}

	private function makeDir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normalizePath( \sys_get_temp_dir().'/shield-asset-cleanup-'.$suffix.'-'.\uniqid() );
		@mkdir( $dir, 0777, true );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
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

class AssetChangeCleanupScans {

	public array $startedAssets = [];
	public int $memoizationResets = 0;
	public $beforeStart = null;

	public function startAfsAssetScan( string $assetType, string $assetKey, bool $resetIgnored = false ) :bool {
		unset( $resetIgnored );
		if ( \is_callable( $this->beforeStart ) ) {
			( $this->beforeStart )( $assetType, $assetKey );
		}
		$this->startedAssets[] = [ $assetType, $assetKey ];
		return true;
	}

	public function resetScanResultsCountMemoization() :void {
		$this->memoizationResets++;
	}
}

class AssetChangeCleanupTable {

	private string $table;

	public function __construct( string $table ) {
		$this->table = $table;
	}

	public function getTable() :string {
		return $this->table;
	}
}

class AssetChangeCleanupWpDb extends Db {

	public array $queries = [];

	public function doSql( $sql ) :bool {
		$this->queries[] = $sql;
		return true;
	}
}

class AssetChangeCleanupCoreHashes extends CoreFileHashes {

	private bool $ready;

	public function __construct( bool $ready ) {
		$this->ready = $ready;
	}

	public function isReady() :bool {
		return $this->ready;
	}
}

class AssetChangeCleanupSnapshotFs extends SnapshotFs {

	public function isAbsPath( $path ) {
		return \preg_match( '#^(?:[A-Z]:)?/#i', \str_replace( '\\', '/', (string)$path ) ) === 1;
	}
}

class AssetChangeCleanupMutablePlugins extends Plugins {

	/**
	 * @var SnapshotPluginVo[]
	 */
	private array $plugins;

	/**
	 * @param SnapshotPluginVo[] $plugins
	 */
	public function __construct( array $plugins ) {
		$this->plugins = $plugins;
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 */
	public function setPlugins( array $plugins ) :void {
		$this->plugins = $plugins;
	}

	/**
	 * @return SnapshotPluginVo[]
	 */
	public function getPluginsAsVo() :array {
		return $this->plugins;
	}

	public function getInstalledPluginFiles() :array {
		return \array_map(
			static fn( SnapshotPluginVo $plugin ) :string => $plugin->file,
			$this->plugins
		);
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		foreach ( $this->plugins as $plugin ) {
			if ( $plugin->file === $file ) {
				return $plugin;
			}
		}
		return null;
	}
}

class AssetChangeCleanupMutableThemes extends Themes {

	/**
	 * @var SnapshotThemeVo[]
	 */
	private array $themes;

	/**
	 * @param SnapshotThemeVo[] $themes
	 */
	public function __construct( array $themes ) {
		$this->themes = $themes;
	}

	/**
	 * @param SnapshotThemeVo[] $themes
	 */
	public function setThemes( array $themes ) :void {
		$this->themes = $themes;
	}

	public function getThemes() :array {
		return \array_map(
			static fn( SnapshotThemeVo $theme ) :SnapshotWpTheme => new SnapshotWpTheme( $theme ),
			$this->themes
		);
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $reload );
		foreach ( $this->themes as $theme ) {
			if ( $theme->stylesheet === $stylesheet ) {
				return $theme;
			}
		}
		return null;
	}

	public function getCurrent() {
		return new SnapshotWpTheme( $this->themes[ 0 ] ?? new SnapshotThemeVo( 'missing-current-theme', '0.0.0' ) );
	}

	public function isActiveThemeAChild() :bool {
		return false;
	}
}
