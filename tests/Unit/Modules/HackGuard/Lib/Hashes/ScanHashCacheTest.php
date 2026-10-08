<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator\AssetCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetFileContext,
	AssetTrustResolver,
	HashVerificationResult,
	Retrieve,
	ScanHashCache
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\AssetTrustState;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
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

class ScanHashCacheTest extends BaseUnitTest {

	use TempDirLifecycleTrait;
	use CacheStoreWordPressFunctions;

	private array $servicesSnapshot;
	private CacheStoreTestFs $fs;
	private SnapshotPluginVo $plugin;
	private SnapshotThemeVo $theme;
	private string $root;
	private array $requests = [];
	private array $remoteHashes = [];
	private bool $remoteThrows = false;
	private int $remoteStatus = 200;
	private ?\Closure $httpDebug = null;
	private bool $mainNetwork = true;
	private bool $mainSite = true;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetMemoization();
		$this->root = $this->createTrackedTempDir( 'shield-scan-hashes-' );
		$pluginDir = $this->createTrackedTempDir( 'shield-scan-plugin-', WP_PLUGIN_DIR );
		$this->plugin = new SnapshotPluginVo( \basename( $pluginDir ).'/postmark.php', '1.20.0' );
		$this->theme = new SnapshotThemeVo( 'scan-hashes-theme', '2.0.0' );
		$this->fs = new CacheStoreTestFs();
		$this->registerCacheStoreWordPressFunctions( $this->fs, $this->root );
		Functions\when( 'is_main_network' )->alias( fn() :bool => $this->mainNetwork );
		Functions\when( 'is_main_site' )->alias( fn() :bool => $this->mainSite );
		Functions\when( 'get_theme_root' )->justReturn( WP_CONTENT_DIR.'/themes' );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_http_validate_url' )->justReturn( true );
		Functions\when( 'add_query_arg' )->alias( static fn( array $data, string $url ) :string => $url );
		Functions\when( 'is_wp_error' )->alias( static fn( $response ) :bool => $response instanceof \WP_Error );
		Functions\when( 'add_action' )->alias( function ( string $hook, \Closure $callback ) :bool {
			if ( $hook === 'http_api_debug' ) {
				$this->httpDebug = $callback;
			}
			return true;
		} );
		Functions\when( 'remove_action' )->alias( function ( string $hook ) :bool {
			if ( $hook === 'http_api_debug' ) {
				$this->httpDebug = null;
			}
			return true;
		} );
		Functions\when( 'wp_remote_request' )->alias( function ( string $url, array $args ) {
			$this->requests[] = [ 'url' => $url, 'timeout' => $args[ 'timeout' ] ];
			$response = $this->remoteThrows ? new \WP_Error( 'http_request_failed', 'Simulated API failure.' ) : [
				'body' => \json_encode( [ 'hashes' => $this->remoteHashes ] ),
				'headers' => [], 'cookies' => [], 'filename' => null,
				'response' => [ 'code' => $this->remoteStatus, 'message' => 'Fixture response' ],
			];
			( $this->httpDebug )( $response, 'response', 'Fixture transport', $args, $url );
			return $response;
		} );
		$general = new SnapshotWpGeneral();
		$general->setTransient( 'apto-wphashes-api-available-routes', '#^cshashes$#' );
		$plugins = new class( [ $this->plugin ] ) extends SnapshotPlugins {
			public function getInstalledPluginFiles() :array {
				return \array_map( static fn( $asset ) :string => $asset->file, $this->getPluginsAsVo() );
			}
		};
		ServicesState::installItems( [
			'service_wpfs' => $this->fs,
			'service_request' => new CacheStoreTestRequest( 1791385228 ),
			'service_wpgeneral' => $general,
			'service_wpplugins' => $plugins,
			'service_wpthemes' => new SnapshotThemes( [ $this->theme ] ),
		] );
		$controller = CacheStoreTestController::install( new CacheStoreTestOptions() );
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $this->root );
		$controller->caps = new class {
			public function canScanPluginsThemesLocal() :bool {
				return true;
			}
			public function canScanPluginsThemesRemote() :bool {
				throw new \LogicException( 'A remote-plan gate must not be consulted.' );
			}
		};
		\file_put_contents( $this->path(), '<?php original_postmark_copy();' );
	}

	protected function tearDown() :void {
		$this->resetMemoization();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_full_preflight_fills_each_eligible_asset_once_and_batches_make_no_requests() :void {
		$this->remoteHashes = [ 'postmark.php' => [ \str_repeat( 'a', 40 ), \sha1_file( $this->path() ) ] ];
		$ineligible = new SnapshotPluginVo( 'unavailable/plugin.php', '1.0.0' );
		$coordinator = new class extends AssetCoordinator {
			protected function hasUsableSnapshot( $asset ) :bool {
				return $asset->unique_id !== 'unavailable/plugin.php';
			}
			protected function buildSnapshot( $asset ) :void {
				throw new \RuntimeException( 'Unavailable fixture snapshot.' );
			}
		};
		$ticks = 0;
		$eligibility = $coordinator->prepareFullScanSnapshotEligibility(
			[ $this->plugin, $this->theme, $ineligible ],
			static function () use ( &$ticks ) :void { $ticks++; }
		);
		$this->assertCount( 2, $this->requests );
		$this->assertNull( $this->httpDebug );
		$this->assertSame( [ 10, 10 ], \array_column( $this->requests, 'timeout' ) );
		$this->assertStringContainsString( '/p/'.\dirname( $this->plugin->file ).'/1.20.0', $this->requests[ 0 ][ 'url' ] );
		$this->assertStringContainsString( '/t/'.$this->theme->stylesheet.'/2.0.0', $this->requests[ 1 ][ 'url' ] );
		$this->assertCount( 2, \glob( $this->root.'/scan-hashes/*' ) );
		$this->assertFalse( $eligibility[ 'plugin' ][ $ineligible->file ][ 'comparison_eligible' ] );
		$this->assertSame( 3, $ticks );
		$action = new ScanActionVO();
		$action->scope_type = 'full';
		$action->asset_snapshot_eligibility = $eligibility;
		$state = new AssetTrustState( $action );
		$result = $state->verifyAssetContext( $this->path(), $state->resolveAssetContext( $this->path() ) );
		$this->assertTrue( $result->verified );
		$this->assertTrue( $result->trustedSource );
		$this->assertSame( HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE, $result->comparisonBasis );
		$this->assertCount( 2, $this->requests );
	}

	/** @dataProvider provideSnapshotFallbacks */
	public function test_api_failure_copies_snapshot_and_preserves_trust( bool $trusted, bool $throws ) :void {
		$this->writeSnapshot( $trusted );
		$this->remoteThrows = $throws;
		( new ScanHashCache() )->fill( $this->plugin );
		$data = ( new ScanHashCache() )->read( $this->context() );
		$this->assertSame( 'snapshot', $data[ 'meta' ][ 'source' ] );
		$this->assertSame( $trusted, $data[ 'meta' ][ 'trusted' ] );
		$this->assertSame( [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ], $data[ 'hashes' ] );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveCurrentContext( $this->path() );
		$this->assertEquals( $resolver->verifyStoredContext( $this->path(), $context ), $resolver->verifyScanContext( $this->path(), $context ) );
		$this->assertCount( 1, $this->requests );
	}

	public static function provideSnapshotFallbacks() :array {
		return [ [ false, false ], [ true, false ], [ false, true ], [ true, true ] ];
	}

	/** @dataProvider provideRequestFailures */
	public function test_full_preflight_stops_after_two_request_failures_and_next_preflight_gets_a_fresh_budget( bool $transportFailure ) :void {
		$assets = $this->budgetAssets();
		$this->remoteThrows = $transportFailure;
		$this->remoteStatus = $transportFailure ? 200 : 503;
		$coordinator = new class extends AssetCoordinator {
			protected function hasUsableSnapshot( $asset ) :bool {
				return true;
			}
		};
		$eligibility = $coordinator->prepareFullScanSnapshotEligibility( $assets, static function () :void {} );
		$this->assertCount( 2, $this->requests );
		foreach ( $assets as $asset ) {
			$this->assertTrue( $eligibility[ 'plugin' ][ $asset->file ][ 'comparison_eligible' ] );
			$this->assertSame( 'snapshot', ( new ScanHashCache() )->read( $this->assetContext( $asset ) )[ 'meta' ][ 'source' ] );
		}
		$this->remoteThrows = false;
		$this->remoteStatus = 200;
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		$coordinator->prepareFullScanSnapshotEligibility( $assets, static function () :void {} );
		$this->assertCount( 2 + \count( $assets ), $this->requests );
		foreach ( $assets as $asset ) {
			$this->assertSame( 'crowd_sourced', ( new ScanHashCache() )->read( $this->assetContext( $asset ) )[ 'meta' ][ 'source' ] );
		}
	}

	public static function provideRequestFailures() :array {
		return [ 'transport error' => [ true ], 'HTTP error' => [ false ] ];
	}

	public function test_cumulative_request_budget_uses_snapshot_copies_for_remaining_assets() :void {
		$assets = $this->budgetAssets();
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		$cache = new class extends ScanHashCache {
			private array $times = [ 0.0, 10.0, 10.0, 20.0, 20.0, 30.0 ];
			protected function requestTime() :float {
				return \array_shift( $this->times );
			}
		};
		foreach ( $assets as $index => $asset ) {
			$cache->fill( $asset );
			$data = $cache->read( $this->assetContext( $asset ) );
			$this->assertSame( $index < 3 ? 'crowd_sourced' : 'snapshot', $data[ 'meta' ][ 'source' ] );
			$this->assertSame( $index < 3, $data[ 'meta' ][ 'trusted' ] );
		}
		$this->assertCount( 3, $this->requests );
	}

	public function test_successful_empty_response_resets_consecutive_request_failures() :void {
		$assets = $this->budgetAssets();
		$cache = new ScanHashCache();
		foreach ( $assets as $index => $asset ) {
			$this->remoteThrows = $index !== 1;
			$cache->fill( $asset );
			$this->assertSame( 'snapshot', $cache->read( $this->assetContext( $asset ) )[ 'meta' ][ 'source' ] );
		}
		$this->assertCount( 4, $this->requests );
	}

	/** @dataProvider provideInvalidCaches */
	public function test_cache_miss_or_nonmatch_returns_stored_result_unchanged( string $case ) :void {
		$this->writeSnapshot( false );
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		( new ScanHashCache() )->fill( $this->plugin );
		$file = $this->cacheFile();
		$data = \json_decode( \file_get_contents( $file ), true );
		switch ( $case ) {
			case 'missing':
				\unlink( $file );
				break;
			case 'corrupt':
				\file_put_contents( $file, '{broken' );
				break;
			case 'absent path':
				$data[ 'hashes' ] = [ 'other.php' => [ \sha1_file( $this->path() ) ] ];
				break;
			case 'nonmatch':
				$data[ 'hashes' ] = [ 'postmark.php' => [ \str_repeat( 'a', 40 ) ] ];
				break;
			case 'invalid digest':
				$data[ 'hashes' ][ 'other.php' ] = [ 'invalid' ];
				break;
			case 'scalar digest':
				$data[ 'hashes' ][ 'postmark.php' ] = \sha1_file( $this->path() );
				break;
			case 'wrong type':
				$data[ 'meta' ][ 'type' ] = 'theme';
				break;
			case 'wrong key':
				$data[ 'meta' ][ 'key' ] = 'another/postmark.php';
				break;
			case 'wrong version':
				$data[ 'meta' ][ 'version' ] = '1.20.1';
				break;
			case 'invalid trust':
				$data[ 'meta' ][ 'trusted' ] = 'true';
				break;
			case 'unsafe path':
				$data[ 'hashes' ][ '../postmark.php' ] = [ \sha1_file( $this->path() ) ];
				break;
		}
		if ( !\in_array( $case, [ 'missing', 'corrupt' ], true ) ) {
			\file_put_contents( $file, \json_encode( $data ) );
		}
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveCurrentContext( $this->path() );
		$this->assertEquals( $resolver->verifyStoredContext( $this->path(), $context ), $resolver->verifyScanContext( $this->path(), $context ) );
		$this->assertCount( 1, $this->requests );
	}

	public static function provideInvalidCaches() :array {
		return \array_map( static fn( string $case ) :array => [ $case ], [
			'missing', 'corrupt', 'absent path', 'nonmatch', 'invalid digest', 'scalar digest',
			'wrong type', 'wrong key', 'wrong version', 'invalid trust', 'unsafe path',
		] );
	}

	public function test_unusable_snapshot_and_api_miss_write_nothing_and_preserve_null() :void {
		( new ScanHashCache() )->fill( $this->plugin );
		$this->assertSame( [], \glob( $this->root.'/scan-hashes/*' ) );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveCurrentContext( $this->path() );
		$this->assertNull( $resolver->verifyScanContext( $this->path(), $context ) );
		$this->assertCount( 1, $this->requests );
	}

	public function test_unavailable_directory_does_not_fetch_and_batch_falls_back_without_writing() :void {
		$this->writeSnapshot( true );
		$controller = ScanHashCache::con();
		$controller->cache_dir_handler = new class( $this->root ) extends CacheStoreTestCacheDir {
			public function buildSubDir( string $subDir ) :string {
				return '';
			}
		};
		( new ScanHashCache() )->fill( $this->plugin );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveCurrentContext( $this->path() );
		$this->assertEquals( $resolver->verifyStoredContext( $this->path(), $context ), $resolver->verifyScanContext( $this->path(), $context ) );
		$this->assertDirectoryDoesNotExist( $this->root.'/scan-hashes' );
		$this->assertSame( [], $this->requests );
	}

	public function test_failed_atomic_write_leaves_no_temporary_or_partial_cache_file() :void {
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		ServicesState::mergeItems( [ 'service_wpfs' => new class extends CacheStoreTestFs {
			public function putFileContent( $path, $contents, $compress = false ) :bool {
				return false;
			}
		} ] );
		( new ScanHashCache() )->fill( $this->plugin );
		$this->assertSame( [], \glob( $this->root.'/scan-hashes/*' ) );
		$this->assertCount( 1, $this->requests );
	}

	public function test_normalisation_lowercase_lookup_and_request_memoization() :void {
		$this->remoteHashes = [ 'src\\file.php' => [ \sha1_file( $this->path() ) ], '../bad' => [ \str_repeat( 'b', 40 ) ] ];
		( new ScanHashCache() )->fill( $this->plugin );
		$context = $this->context();
		$context->relativePath = 'src/File.php';
		$resolver = new AssetTrustResolver();
		$this->assertTrue( $resolver->verifyScanContext( $this->path(), $context )->trustedSource );
		$file = $this->cacheFile();
		$this->assertSame( [ 'src/file.php' => [ \sha1_file( $this->path() ) ] ], ( new ScanHashCache() )->read( $context )[ 'hashes' ] );
		\file_put_contents( $file, '{corrupt after first read' );
		$this->assertTrue( ( new AssetTrustResolver() )->verifyScanContext( $this->path(), $context )->verified );
		$this->assertSame( 1, $this->fs->fileReadCounts[ $file ] );
		$this->assertCount( 1, $this->requests );
	}

	public function test_filename_sanitizes_version_but_metadata_and_identity_remain_exact() :void {
		$this->plugin->Version = '1.20 rc+1';
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		( new ScanHashCache() )->fill( $this->plugin );
		$files = \glob( $this->root.'/scan-hashes/*' );
		$this->assertSame( [ $this->root.'/scan-hashes/plugin-'.\substr( \sha1( $this->plugin->file ), 0, 16 ).'-1.20_rc_1.json' ], $files );
		$this->assertSame( $this->plugin->Version, ( new ScanHashCache() )->read( $this->context() )[ 'meta' ][ 'version' ] );
		$collision = $this->context();
		$collision->assetVersion = '1.20_rc_1';
		$this->assertNull( ( new ScanHashCache() )->read( $collision ) );
	}

	public function test_later_fill_replaces_a_memoized_miss_and_previous_reference() :void {
		$cache = new ScanHashCache();
		\mkdir( $this->root.'/scan-hashes' );
		$this->assertNull( $cache->read( $this->context() ) );
		$this->remoteHashes = [ 'postmark.php' => [ \str_repeat( 'a', 40 ) ] ];
		$cache->fill( $this->plugin );
		$this->assertSame( $this->remoteHashes, $cache->read( $this->context() )[ 'hashes' ] );
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		$cache->fill( $this->plugin );
		$this->assertTrue( ( new AssetTrustResolver() )->verifyScanContext( $this->path(), $this->context() )->verified );
		$this->assertCount( 2, $this->requests );
	}

	public function test_resolver_reset_and_cache_cleanup_discard_memoized_references() :void {
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		$cache = new ScanHashCache();
		$cache->fill( $this->plugin );
		$this->assertNotNull( $cache->read( $this->context() ) );
		$file = $this->cacheFile();
		\file_put_contents( $file, '{corrupt' );
		AssetTrustResolver::resetMemoization();
		$this->assertNull( $cache->read( $this->context() ) );
		$cache->fill( $this->plugin );
		$this->assertNotNull( $cache->read( $this->context() ) );
		$cache->deleteDirectory();
		\mkdir( $this->root.'/scan-hashes' );
		$this->assertNull( $cache->read( $this->context() ) );
	}

	/** @dataProvider provideNonOwnerSites */
	public function test_subnetwork_has_no_fill_read_or_deletes( bool $mainNetwork, bool $mainSite ) :void {
		$this->remoteHashes = [ 'postmark.php' => [ \sha1_file( $this->path() ) ] ];
		$cache = new ScanHashCache();
		$cache->fill( $this->plugin );
		$before = \file_get_contents( $this->cacheFile() );
		$this->mainNetwork = $mainNetwork;
		$this->mainSite = $mainSite;
		$cache->fill( $this->plugin );
		$cache->emptyDirectory( 1 );
		$cache->deleteDirectory();
		$this->assertNull( $cache->read( $this->context() ) );
		$this->assertSame( $before, \file_get_contents( $this->cacheFile() ) );
		$this->assertCount( 1, $this->requests );
	}

	public static function provideNonOwnerSites() :array {
		return [ [ true, false ], [ false, true ], [ false, false ] ];
	}

	private function path() :string {
		return WP_PLUGIN_DIR.'/'.$this->plugin->file;
	}

	private function context() :AssetFileContext {
		return new AssetFileContext( 'plugin', $this->plugin->file, $this->plugin->Version, 'postmark.php' );
	}

	private function cacheFile() :string {
		$files = \glob( $this->root.'/scan-hashes/*.json' );
		$this->assertCount( 1, $files );
		return $files[ 0 ];
	}

	/** @return list<SnapshotPluginVo> */
	private function budgetAssets() :array {
		$assets = [ $this->plugin ];
		foreach ( \range( 1, 4 ) as $index ) {
			$assets[] = new SnapshotPluginVo( 'budget-'.$index.'/plugin.php', '1.0.0' );
		}
		foreach ( $assets as $asset ) {
			$this->writeSnapshot( false, $asset );
		}
		return $assets;
	}

	private function assetContext( SnapshotPluginVo $asset ) :AssetFileContext {
		return new AssetFileContext( 'plugin', $asset->file, $asset->Version, 'postmark.php' );
	}

	private function writeSnapshot( bool $trusted, ?SnapshotPluginVo $asset = null ) :void {
		$asset = $asset ?? $this->plugin;
		( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( [ 'postmark.php' => \sha1_file( $this->path() ) ] )
			->setSnapMeta( [ 'unique_id' => $asset->file, 'version' => $asset->Version, 'live_hashes' => $trusted ] )
			->save();
	}

	private function resetMemoization() :void {
		ScanHashCache::resetMemoization();
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		foreach ( [ 'dir', 'rootDir' ] as $propertyName ) {
			$property = new \ReflectionProperty( HashesStorageDir::class, $propertyName );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
	}
}
