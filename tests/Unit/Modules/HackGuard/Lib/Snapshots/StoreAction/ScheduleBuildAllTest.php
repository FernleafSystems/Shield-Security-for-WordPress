<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots\StoreAction;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\ScheduleBuildAll;
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
	SnapshotThemeVo,
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

function error_log( string $message ) :bool {
	ScheduleBuildAllTest::$capturedErrorLogs[] = $message;
	return true;
}

class ScheduleBuildAllTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;
	use TempDirLifecycleTrait;
	use WrittenFixtureFiles;

	private const MD5 = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	public static array $capturedErrorLogs = [];

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		self::$capturedErrorLogs = [];
		$this->servicesSnapshot = ServicesState::snapshot();
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ) :string {
				return empty( $args ) ? $url : $url.'?'.\http_build_query( $args );
			}
		);
		Functions\when( 'wp_http_validate_url' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_request' )->alias(
			static fn() :array => self::httpResponse( [
				'routes_regex' => '#^hashes$#',
			] )
		);
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
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->removeWrittenFixtureFiles();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_usable_current_snapshot_excludes_asset_from_build_list() :void {
		$asset = new SnapshotPluginVo( 'snapshot-current/plugin.php', '1.0.0' );
		$asset->active = false;
		$this->installEnvironment( [ $asset ] );
		$this->writeStore( $asset, [
			'plugin.php' => self::MD5,
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
			'plugin.php' => self::MD5,
		], [
			'version'   => '1.0.0',
			'unique_id' => 'snapshot-stale/plugin.php',
		] );

		$this->assertSame( [ 'snapshot-stale/plugin.php' ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_usable_current_theme_snapshot_excludes_asset_from_build_list() :void {
		$asset = new SnapshotThemeVo( 'snapshot-current-theme', '1.0.0' );
		$asset->active = false;
		$this->installEnvironment( [], [ $asset ] );
		$this->writeStore( $asset, [
			'style.css' => self::MD5,
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
			'style.css' => self::MD5,
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
		$this->assertFileDoesNotExist( $root.'/.ptguard-active.txt' );
	}

	public function test_legacy_entry_point_delegates_discovery_to_asset_coordinator() :void {
		$this->installEnvironment( [] );
		$coordinator = new ScheduleBuildAllCoordinator();
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin
			->getController()
			->comps->asset_coordinator = $coordinator;

		( new ScheduleBuildAll() )->execute();

		$this->assertSame( 1, $coordinator->discoveries );
	}

	public function test_build_writes_and_loads_under_selected_uploads_root_only() :void {
		$asset = new SnapshotPluginVo( 'snapshot-build-root/plugin.php', '1.0.0' );
		$uploadsRoot = $this->makeTempDir( 'uploads-root' );
		$cacheRoot = $this->makeTempDir( 'cache-root' );
		$this->installBuildEnvironment( [ $asset ], $uploadsRoot );
		$this->writeFile( WP_PLUGIN_DIR.'/'.$asset->file, "<?php\n// snapshot build root\n" );
		$this->mkdir( $cacheRoot.'/ptguard-cccccccccccccccc' );

		$this->invokeBuild();

		$this->assertNotSame(
			[],
			\glob( $uploadsRoot.'/ptguard-*/plugins/snapshot-build-root-1.0.0.txt' ) ?: [],
			\implode( "\n", self::$capturedErrorLogs )
		);
		$this->assertSame( [], \glob( $cacheRoot.'/ptguard-*/plugins/snapshot-build-root-1.0.0.txt' ) ?: [] );
		$this->assertSame( [], $this->assetKeysThatNeedBuilt() );
	}

	public function test_published_plugin_request_reaches_canonical_api_path_and_persists_live_hashes() :void {
		$asset = new SnapshotPluginVo( 'published-plugin/plugin.php', '1.2.3' );
		$asset->wpOrg = true;
		$root = $this->makeTempDir( 'published-plugin' );
		$this->installBuildEnvironment( [ $asset ], $root );
		$urls = [];
		$this->mockPublishedResponse( [
			'src\\Plugin.php' => self::MD5,
		], $urls );

		$this->invokeBuild();

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			'src/Plugin.php' => self::MD5,
		], $store->getSnapData() );
		$this->assertTrue( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertCount( 1, $urls );
		$this->assertStringContainsString( '/hashes/p/published-plugin/1.2.3/md5', $urls[ 0 ] );
	}

	public function test_published_theme_request_reaches_canonical_api_path_and_persists_live_hashes() :void {
		$asset = new SnapshotThemeVo( 'published-theme', '4.5.6' );
		$asset->wpOrg = true;
		$root = $this->makeTempDir( 'published-theme' );
		$this->installBuildEnvironment( [], $root, [ $asset ] );
		$urls = [];
		$this->mockPublishedResponse( [
			'style.css' => self::MD5,
		], $urls );

		$this->invokeBuild();

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			'style.css' => self::MD5,
		], $store->getSnapData() );
		$this->assertTrue( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertCount( 1, $urls );
		$this->assertStringContainsString( '/hashes/t/published-theme/4.5.6/md5', $urls[ 0 ] );
	}

	/**
	 * @dataProvider provideUnusablePublishedMaps
	 */
	public function test_unusable_published_map_falls_back_to_complete_local_baseline(
		string $slug,
		array $published
	) :void {
		$asset = new SnapshotPluginVo( $slug.'/plugin.php', '2.0.0' );
		$asset->wpOrg = true;
		$root = $this->makeTempDir( $slug );
		$path = WP_PLUGIN_DIR.'/'.$asset->file;
		$this->installBuildEnvironment( [ $asset ], $root );
		$this->writeFile( $path, "<?php\n// local fallback\n" );
		$urls = [];
		$this->mockPublishedResponse( $published, $urls );

		$this->invokeBuild();

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			'plugin.php' => \md5_file( $path ),
		], $store->getSnapData() );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertCount( 1, $urls );
	}

	public function provideUnusablePublishedMaps() :array {
		return [
			'empty' => [
				'empty-published',
				[],
			],
			'partially invalid' => [
				'partial-published',
				[
					'valid.php' => self::MD5,
					'bad.php'   => 'unsupported-hash',
				],
			],
			'normalised collision' => [
				'colliding-published',
				[
					'src\\File.php' => self::MD5,
					'src/File.php'  => \str_repeat( 'b', 32 ),
				],
			],
		];
	}

	public function test_published_api_exception_falls_back_to_usable_local_baseline() :void {
		$asset = new SnapshotPluginVo( 'published-exception/plugin.php', '2.0.0' );
		$asset->wpOrg = true;
		$root = $this->makeTempDir( 'published-exception' );
		$path = WP_PLUGIN_DIR.'/'.$asset->file;
		$this->installBuildEnvironment( [ $asset ], $root );
		$this->writeFile( $path, "<?php\n// local exception fallback\n" );
		Functions\when( 'wp_remote_request' )->alias(
			static function () :array {
				throw new \Exception( 'Synthetic published source failure.' );
			}
		);

		$this->invokeBuild();

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			'plugin.php' => \md5_file( $path ),
		], $store->getSnapData() );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
	}

	public function test_missing_inactive_root_plugin_hashes_only_its_file_and_skips_crowdsource() :void {
		$asset = new ScheduleBuildAllRootPluginVo( 'inactive-root.php', '2.0.0' );
		$asset->active = false;
		$root = $this->makeTempDir( 'inactive-root' );
		$path = WP_PLUGIN_DIR.'/'.$asset->file;
		$this->installBuildEnvironment( [ $asset ], $root, [], true );
		$this->writeFile( $path, "<?php\n// inactive root plugin\n" );
		$this->writeFile( WP_PLUGIN_DIR.'/sibling-root.php', "<?php\n// sibling root plugin\n" );
		$this->writeFile( WP_PLUGIN_DIR.'/sibling-plugin/sibling.php', "<?php\n// sibling directory plugin\n" );
		$urls = [];
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url, array $args ) use ( &$urls ) :array {
				unset( $args );
				$urls[] = $url;
				if ( \strpos( $url, '/hashes/info' ) !== false ) {
					return self::httpResponse( [
						'info' => [
							'supported_premium' => [
								'plugins' => [],
								'themes'  => [],
							],
						],
					] );
				}
				return self::httpResponse( [
					'hashes' => [
						'submit_required' => true,
					],
				] );
			}
		);

		$this->invokeBuild();

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			$asset->file => \md5_file( $path ),
		], $store->getSnapData() );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertSame( 0, $store->getSnapMeta()[ 'cs_hashes_at' ] );
		$this->assertFalse(
			(bool)\array_filter(
				$urls,
				static fn( string $url ) :bool => \strpos( $url, '/cshashes/submit' ) !== false
			),
			\implode( "\n", $urls )
		);
		$this->assertSame( [], $this->assetKeysThatNeedBuilt() );
	}

	/**
	 * @dataProvider provideChildThemeFlags
	 */
	public function test_child_theme_uses_local_baseline_without_published_or_crowdsource_work(
		bool $activeChild,
		bool $inactiveChild
	) :void {
		$asset = new SnapshotThemeVo(
			$activeChild ? 'active-child-theme' : 'inactive-child-theme',
			'3.0.0'
		);
		$asset->child = $activeChild;
		$asset->inactiveChild = $inactiveChild;
		$asset->wpOrg = true;
		$root = $this->makeTempDir( $asset->stylesheet );
		$path = $asset->getInstallDir().'style.css';
		$this->installBuildEnvironment( [], $root, [ $asset ], true );
		$this->writeFile( $path, "/* local child theme */\n" );
		$requests = 0;
		Functions\when( 'wp_remote_request' )->alias(
			static function () use ( &$requests ) {
				$requests++;
				return [];
			}
		);

		$this->invokeBuild();

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			'style.css' => \md5_file( $path ),
		], $store->getSnapData() );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertSame( 0, $store->getSnapMeta()[ 'cs_hashes_at' ] );
		$this->assertSame( 0, $requests );
	}

	public function provideChildThemeFlags() :array {
		return [
			'active child'   => [ true, false ],
			'inactive child' => [ false, true ],
		];
	}

	public function test_successful_replacement_resets_hash_and_asset_context_memoization() :void {
		$asset = new SnapshotPluginVo( 'memo-reset/plugin.php', '1.0.0' );
		$root = $this->makeTempDir( 'memo-reset' );
		$this->installBuildEnvironment( [ $asset ], $root );
		$this->writeFile( WP_PLUGIN_DIR.'/'.$asset->file, "<?php\n// replacement\n" );
		$this->seedMemoization();

		$this->invokeBuild();

		$this->assertMemoizationEmpty();
		$this->assertTrue( $this->loadStore( $asset )->isUsable() );
	}

	public function test_failed_preparation_does_not_claim_success_or_reset_memoization() :void {
		$asset = new SnapshotPluginVo( 'memo-preserved/missing.php', '1.0.0' );
		$root = $this->makeTempDir( 'memo-preserved' );
		$this->installBuildEnvironment( [ $asset ], $root );
		$this->seedMemoization();

		$this->invokeBuild();

		$this->assertMemoizationSeeded();
		$this->assertSame( [ $asset->file ], $this->assetKeysThatNeedBuilt() );
	}

	public function test_one_asset_throwable_does_not_prevent_a_sibling_build() :void {
		$failing = new ScheduleBuildAllThrowingPluginVo( 'failing/plugin.php', '1.0.0' );
		$sibling = new SnapshotPluginVo( 'sibling/plugin.php', '1.0.0' );
		$root = $this->makeTempDir( 'isolated-failure' );
		$siblingPath = WP_PLUGIN_DIR.'/'.$sibling->file;
		$this->installBuildEnvironment( [ $failing, $sibling ], $root );
		$this->writeFile( $siblingPath, "<?php\n// sibling plugin\n" );

		$this->invokeBuild();

		$store = ( new Store( $sibling, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			'plugin.php' => \md5_file( $siblingPath ),
		], $store->getSnapData() );
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
	private function installBuildEnvironment(
		array $plugins,
		string $cacheRoot,
		array $themes = [],
		bool $premium = false
	) :void {
		$this->resetHashesStorageDir();
		$fs = new CacheStoreTestFs();
		$wpGeneral = new SnapshotWpGeneral();
		$wpGeneral->setTransient( 'apto-wphashes-api-available-routes', '#^(?:hashes|cshashes/submit)$#' );
		$this->registerCacheStoreWordPressFunctions( $fs, $this->makeTempDir( 'tmp' ) );
		ServicesState::installItems( [
			'service_request'   => new CacheStoreTestRequest( 1700000500 ),
			'service_wpfs'      => $fs,
			'service_wpgeneral' => $wpGeneral,
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

				public array $paths = [
					'cache' => 'shield',
				];

				public object $configuration;

				public function __construct() {
					$this->configuration = new class {
						public function def( string $key ) :array {
							return $key === 'file_scan_extensions' ? [ 'php' ] : [];
						}
					};
				}

				public function version() :string {
					return '20.0.0';
				}
			}
		);
		$controller->is_mode_live = true;
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $cacheRoot );
		$controller->comps = (object)[
			'license' => new class( $premium ) {
				private bool $premium;

				public function __construct( bool $premium ) {
					$this->premium = $premium;
				}

				public function hasValidWorkingLicense() :bool {
					return $this->premium;
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
		return \array_values( \array_map(
			static fn( $asset ) :string => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			( new ScheduleBuildAll() )->getAssetsThatNeedBuilt()
		) );
	}

	private function invokeBuild() :void {
		( new ScheduleBuildAll() )->build();
	}

	/**
	 * @param SnapshotPluginVo|SnapshotThemeVo $asset
	 */
	private function loadStore( $asset ) :Store {
		return ( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() );
	}

	private function mockPublishedResponse( array $hashes, array &$urls ) :void {
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url, array $args ) use ( $hashes, &$urls ) :array {
				unset( $args );
				if ( \strpos( $url, '/availability' ) !== false ) {
					return self::httpResponse( [
						'routes_regex' => '#^hashes$#',
					] );
				}
				$urls[] = $url;
				return self::httpResponse( [ 'hashes' => $hashes ] );
			}
		);
	}

	private static function httpResponse( array $body ) :array {
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

	private function seedMemoization() :void {
		$this->setStaticProperty( Retrieve::class, 'hashes', [ 'seed' => [ 'hash' ] ] );
		$this->setStaticProperty( Retrieve::class, 'trustedSources', [ 'seed' => true ] );
		foreach ( [
			'plugins',
			'themesByDir',
			'contextsByPath',
			'nonAssetMissesByPath',
			'relativePathsByPath',
		] as $property ) {
			$this->setStaticProperty( AssetTrustResolver::class, $property, [ 'seed' => true ] );
		}
	}

	private function assertMemoizationEmpty() :void {
		$this->assertSame( [], $this->getStaticProperty( Retrieve::class, 'hashes' ) );
		$this->assertSame( [], $this->getStaticProperty( Retrieve::class, 'trustedSources' ) );
		foreach ( [
			'plugins',
			'themesByDir',
			'contextsByPath',
			'nonAssetMissesByPath',
			'relativePathsByPath',
		] as $property ) {
			$this->assertSame( [], $this->getStaticProperty( AssetTrustResolver::class, $property ) );
		}
	}

	private function assertMemoizationSeeded() :void {
		$this->assertNotEmpty( $this->getStaticProperty( Retrieve::class, 'hashes' ) );
		$this->assertNotEmpty( $this->getStaticProperty( Retrieve::class, 'trustedSources' ) );
		$this->assertNotEmpty( $this->getStaticProperty( AssetTrustResolver::class, 'plugins' ) );
	}

	private function setStaticProperty( string $class, string $property, array $value ) :void {
		$reflection = new \ReflectionProperty( $class, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $value );
	}

	private function getStaticProperty( string $class, string $property ) :array {
		$reflection = new \ReflectionProperty( $class, $property );
		$reflection->setAccessible( true );
		return $reflection->getValue();
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
		return $this->normalizePath( $this->createTrackedTempDir( 'shield-schedule-build-'.$suffix.'-' ) );
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
		$this->trackWrittenFixtureFile( $path );
	}
}

class ScheduleBuildAllCoordinator {

	public int $discoveries = 0;

	public function discoverMissingSnapshots() :bool {
		$this->discoveries++;
		return true;
	}
}

class ScheduleBuildAllThrowingPluginVo extends SnapshotPluginVo {

	public function isWpOrg() :bool {
		throw new \TypeError( 'Synthetic source failure.' );
	}
}

class ScheduleBuildAllRootPluginVo extends SnapshotPluginVo {

	public function __get( string $key ) {
		return $key === 'slug' ? 'inactive-root' : parent::__get( $key );
	}
}
