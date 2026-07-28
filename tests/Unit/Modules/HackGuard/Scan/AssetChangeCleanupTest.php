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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
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

	use TempDirLifecycleTrait;
	use WrittenFixtureFiles;

	private array $servicesSnapshot = [];

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
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_cleanup_starts_core_scan_without_resolving_findings_before_scan_completion() :void {
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

	public function test_cleanup_reports_failure_and_does_not_scan_when_readiness_fails() :void {
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		ServicesState::installItems( [
			'service_corefilehashes' => new AssetChangeCleanupCoreHashes( false ),
			'service_request'        => new UnitTestRequest( [], '127.0.0.1', 1700000200 ),
			'service_wpdb'           => $wpDb,
		] );

		$this->assertFalse( ( new Cleanup() )->process( 'core', 'core' ) );

		$this->assertSame( [], $scans->startedAssets );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
	}

	public function test_legacy_cleanup_adapter_does_not_own_retries() :void {
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
	}

	/**
	 * @dataProvider providePresentAssetReadinessFailures
	 */
	public function test_present_plugin_or_theme_readiness_failure_is_reported_without_scanning(
		string $assetType,
		string $assetKey,
		string $version
	) :void {
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

		$this->assertFalse( ( new Cleanup() )->process( $assetType, $assetKey ) );

		$this->assertSame( [], $scans->startedAssets );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
	}

	public function test_plugin_cleanup_uses_existing_verified_hashes_without_local_rebuild() :void {
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
		$this->assertSame( \md5( 'old-same-version-content' ), $snapData[ 'cleanup-plugin.php' ] );
		$this->assertSame( '2.0.0', $store->getSnapMeta()[ 'version' ] );
		$this->assertSame( 0, $scans->memoizationResets );
		$this->assertSame( [], $wpDb->queries );
	}

	public function test_same_version_root_plugins_keep_isolated_local_snapshots_when_hashes_already_exist() :void {
		$first = new SnapshotPluginVo( 'first.php', '1.0.0' );
		$second = new SnapshotPluginVo( 'second.php', '1.0.0' );
		$firstPath = WP_PLUGIN_DIR.'/'.$first->file;
		$secondPath = WP_PLUGIN_DIR.'/'.$second->file;
		$this->writeFile( $firstPath, 'first-content' );
		$this->writeFile( $secondPath, 'second-content' );

		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		$this->installSnapshotEnvironment(
			new SnapshotPlugins( [ $first, $second ] ),
			new SnapshotThemes( [] )
		);
		ServicesState::mergeItems( [ 'service_wpdb' => new AssetChangeCleanupWpDb() ] );

		( new Cleanup() )->run( 'plugin', $first->file );
		( new Cleanup() )->run( 'plugin', $second->file );

		$firstStore = ( new Load() )->setAsset( $first )->run();
		$secondStore = ( new Load() )->setAsset( $second )->run();
		$this->assertNotSame( $firstStore->getSnapStorePath(), $secondStore->getSnapStorePath() );
		$this->assertNotSame( $firstStore->getSnapStoreMetaPath(), $secondStore->getSnapStoreMetaPath() );
		$this->assertSame( [ 'first.php' => \md5_file( $firstPath ) ], $firstStore->getSnapData() );
		$this->assertSame( [ 'second.php' => \md5_file( $secondPath ) ], $secondStore->getSnapData() );
		$this->assertSame( 'first.php', $firstStore->getSnapMeta()[ 'unique_id' ] );
		$this->assertSame( 'second.php', $secondStore->getSnapMeta()[ 'unique_id' ] );

		$secondDataBefore = $secondStore->getSnapData();
		$secondMetaBefore = $secondStore->getSnapMeta();
		$this->writeFile( $firstPath, 'first-rebuilt-content' );
		( new Cleanup() )->run( 'plugin', $first->file );

		$rebuiltFirst = ( new Load() )->setAsset( $first )->run();
		$untouchedSecond = ( new Load() )->setAsset( $second )->run();
		$this->assertSame( [ 'first.php' => \md5( 'first-content' ) ], $rebuiltFirst->getSnapData() );
		$this->assertSame( $secondDataBefore, $untouchedSecond->getSnapData() );
		$this->assertSame( $secondMetaBefore, $untouchedSecond->getSnapMeta() );
		$this->assertTrue( $rebuiltFirst->verify() );
		$this->assertTrue( $untouchedSecond->verify() );
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
				'cleanup-reset.php' => [ \md5_file( $path ) ],
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

	public function test_theme_cleanup_uses_existing_verified_hashes_without_local_rebuild() :void {
		$theme = new SnapshotThemeVo( 'cleanup-theme', '3.1.0' );
		$this->writeFile( WP_CONTENT_DIR.'/themes/'.$theme->stylesheet.'/style.php', "<?php\n" );

		$wpDb = new AssetChangeCleanupWpDb();
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
			'service_wpdb' => $wpDb,
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
		$this->assertSame( \md5( 'old-same-version-content' ), $snapData[ 'style.php' ] );
		$this->assertSame( '3.1.0', $store->getSnapMeta()[ 'version' ] );
		$this->assertSame( [], $wpDb->queries );
	}

	/**
	 * @dataProvider providePublishedSnapshotAssets
	 */
	public function test_cleanup_persists_usable_published_snapshot_before_starting_scoped_scan(
		string $assetType,
		string $assetKey,
		string $version,
		string $relativePath
	) :void {
		$asset = $assetType === 'plugin'
			? new SnapshotPluginVo( $assetKey, $version )
			: new SnapshotThemeVo( $assetKey, $version );
		$asset->wpOrg = true;
		$path = $assetType === 'plugin'
			? WP_PLUGIN_DIR.'/'.$assetKey
			: WP_CONTENT_DIR.'/themes/'.$assetKey.'/'.$relativePath;
		$this->writeFile( $path, "<?php\n// remote snapshot fallback\n" );
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans, true );
		$this->installSnapshotEnvironment(
			$assetType === 'plugin' ? new SnapshotPlugins( [ $asset ] ) : new SnapshotPlugins( [] ),
			$assetType === 'theme' ? new SnapshotThemes( [ $asset ] ) : new SnapshotThemes( [] )
		);
		$wpGeneral = new SnapshotWpGeneral();
		$wpGeneral->setTransient( 'apto-wphashes-api-available-routes', '#^(?:cshashes|hashes)$#' );
		ServicesState::mergeItems( [
			'service_wpgeneral' => $wpGeneral,
			'service_wpdb'      => new AssetChangeCleanupWpDb(),
		] );
		$hash = \str_repeat( 'a', 32 );
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url ) use ( $hash, $relativePath ) :array {
				return \strpos( $url, '/availability' ) !== false
					? AssetChangeCleanupTest::httpResponse( [ 'routes_regex' => '#^(?:cshashes|hashes)$#' ] )
					: AssetChangeCleanupTest::httpResponse( [ 'hashes' => [ $relativePath => $hash ] ] );
			}
		);

		$scans->beforeStart = function ( string $startedType, string $startedKey ) use ( $asset, $assetType, $assetKey, $relativePath, $hash ) :void {
			$this->assertSame( $assetType, $startedType );
			$this->assertSame( $assetKey, $startedKey );
			$store = ( new Load() )->setAsset( $asset )->run();
			$this->assertTrue( $store->isUsable() );
			$this->assertTrue( $store->getSnapMeta()[ 'live_hashes' ] );
			$this->assertSame( [
				$relativePath => [ $hash ],
			], ( new Retrieve() )->byVOFromStoredSnapshot( $asset )[ 'hashes' ] );
		};

		$this->assertTrue( ( new Cleanup() )->process( $assetType, $assetKey ) );
		$this->assertSame( [ [ $assetType, $assetKey ] ], $scans->startedAssets );
	}

	public function providePublishedSnapshotAssets() :array {
		return [
			'plugin' => [ 'plugin', 'remote-plugin/remote.php', '2.0.0', 'remote.php' ],
			'theme'  => [ 'theme', 'remote-theme', '3.0.0', 'style.php' ],
		];
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

	public function test_schedule_delegates_plugin_without_touching_snapshot() :void {
		$plugin = new SnapshotPluginVo( 'pending-plugin/pending.php', '1.0.0' );
		$coordinator = $this->installController( new AssetChangeCleanupScans() );
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

		$this->assertSame( [ [ 'plugin', $plugin->file, Cleanup::CRON_DELAY ] ], $coordinator->assets );
		$this->assertSnapshotStorePreserved( $plugin, $expectedData, $expectedMeta );
	}

	public function test_schedule_delegates_theme_without_touching_snapshot() :void {
		$theme = new SnapshotThemeVo( 'pending-theme', '1.0.0' );
		$coordinator = $this->installController( new AssetChangeCleanupScans() );
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

		$this->assertSame( [ [ 'theme', $theme->stylesheet, Cleanup::CRON_DELAY ] ], $coordinator->assets );
		$this->assertSnapshotStorePreserved( $theme, $expectedData, $expectedMeta );
	}

	public function test_schedule_delegates_each_valid_asset_to_coordinator() :void {
		$coordinator = $this->installController( new AssetChangeCleanupScans() );

		$this->assertTrue( ( new Cleanup() )->schedule( 'plugin', 'akismet/akismet.php' ) );
		$this->assertTrue( ( new Cleanup() )->schedule( 'theme', 'twentytwentyfour' ) );
		$this->assertTrue( ( new Cleanup() )->schedule( 'plugin', 'hello-dolly/hello.php' ) );
		$this->assertSame( [
			[ 'plugin', 'akismet/akismet.php', Cleanup::CRON_DELAY ],
			[ 'theme', 'twentytwentyfour', Cleanup::CRON_DELAY ],
			[ 'plugin', 'hello-dolly/hello.php', Cleanup::CRON_DELAY ],
		], $coordinator->assets );
	}

	public function test_invalid_asset_inputs_do_not_touch_sql_scan_or_cron() :void {
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000300 ),
			'service_wpdb'    => $wpDb,
		] );
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

	public function test_cron_adapter_ignores_hostile_argument_types() :void {
		$wpDb = new AssetChangeCleanupWpDb();
		$scans = new AssetChangeCleanupScans();
		$this->installController( $scans );
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', 1700000300 ),
			'service_wpdb'    => $wpDb,
		] );
		$cleanup = new Cleanup();

		foreach ( [
			[ null, 'asset', 0 ],
			[ 'plugin', [], 0 ],
			[ 'theme', (object)[], 0 ],
			[ 'core', 'core', '0' ],
			[ 'plugin', 'valid/plugin.php', -1 ],
		] as $args ) {
			$cleanup->run( $args[ 0 ], $args[ 1 ], $args[ 2 ] );
		}

		$this->assertSame( [], $wpDb->queries );
		$this->assertSame( [], $scans->startedAssets );
	}

	public function providePresentAssetReadinessFailures() :array {
		return [
			'plugin' => [
				'plugin',
				'cleanup-unready-plugin/cleanup-unready.php',
				'9.9.0',
			],
			'theme'  => [
				'theme',
				'cleanup-unready-theme',
				'9.9.0',
			],
		];
	}

	private function installController( AssetChangeCleanupScans $scans, bool $canScanRemote = false ) :AssetChangeCleanupCoordinator {
		$coordinator = new AssetChangeCleanupCoordinator();
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
			'asset_coordinator' => $coordinator,
			'scans'             => $scans,
		];
		$controller->caps = new class( $canScanRemote ) {
			private bool $canScanRemote;

			public function __construct( bool $canScanRemote ) {
				$this->canScanRemote = $canScanRemote;
			}

			public function canScanPluginsThemesRemote() :bool {
				return $this->canScanRemote;
			}
		};
		$controller->db_con = (object)[
			'scan_result_items'     => new AssetChangeCleanupTable( 'shield_scan_result_items' ),
			'scan_result_item_meta' => new AssetChangeCleanupTable( 'shield_scan_result_item_meta' ),
		];

		PluginControllerInstaller::install( $controller );
		return $coordinator;
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
		return $this->normalizePath( $this->createTrackedTempDir( 'shield-asset-cleanup-'.$suffix.'-' ) );
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}

	public static function httpResponse( array $body ) :array {
		return [
			'body'     => \json_encode( $body ),
			'headers'  => [],
			'cookies'  => [],
			'filename' => null,
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		];
	}
}

class AssetChangeCleanupCoordinator {

	public array $assets = [];

	public function enqueueAsset( string $assetType, string $assetKey, int $delay ) :bool {
		$this->assets[] = [ $assetType, $assetKey, $delay ];
		return true;
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
