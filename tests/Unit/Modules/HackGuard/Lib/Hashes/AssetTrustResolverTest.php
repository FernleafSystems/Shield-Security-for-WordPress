<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

if ( !\function_exists( __NAMESPACE__.'\\error_log' ) ) {
	function error_log( string $message ) :bool {
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes\AssetTrustResolverTest::$capturedErrorLogs[] = $message;
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	HashVerificationResult,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\{
	AmbiguousAssetFileException,
	NonAssetFileException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\SnapshotFs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\CacheStoreTestCacheDir;
use FernleafSystems\Wordpress\Services\Core\{
	Plugins,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class AssetTrustResolverTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private const PLUGIN_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const FIRST_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
	private const SECOND_HASH = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';
	private const THEME_HASH = 'dddddddddddddddddddddddddddddddd';

	public static array $capturedErrorLogs = [];

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		AssetTrustResolver::resetMemoization();
		Retrieve::resetMemoization();
		$this->resetHashesStorageDir();
		ResolverFs::$isAbsPathCalls = 0;
		ResolverPlugins::$installedPluginFilesCalls = 0;
		ResolverPlugins::$getPluginAsVoCalls = 0;
		ResolverThemes::$getThemesCalls = 0;
		ResolverThemes::$getThemeAsVoCalls = 0;
		self::$capturedErrorLogs = [];
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalisePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalisePath( $path ) );
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalisePath( $path ), '/' ) );
		Functions\when( 'get_theme_root' )->alias( fn() :string => $this->normalisePath( WP_CONTENT_DIR.'/themes' ) );
	}

	protected function tearDown() :void {
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_repeated_same_plugin_path_reuses_full_path_context() :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ] );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();

		$first = $resolver->resolveContext( $path );
		$callsAfterFirst = [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		];
		$second = $resolver->resolveContext( $path );

		$this->assertSame( 'plugin', $first->assetType );
		$this->assertSame( 'alpha/alpha.php', $first->assetKey );
		$this->assertSame( 'src/File.php', $first->relativePath );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );
		$this->assertSame( $callsAfterFirst, [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		] );
	}

	public function test_plugin_context_uses_reloaded_asset_version() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '0.9.0', '1.0.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );

		$context = ( new AssetTrustResolver() )->resolveContext( $path );

		$this->assertSame( '1.0.0', $context->assetVersion );
		$this->assertSame( 1, ResolverPlugins::$getPluginAsVoCalls );
	}

	public function test_repeated_plugin_directory_contexts_load_asset_once() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '1.0.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$resolver = new AssetTrustResolver();

		$first = $resolver->resolveContext( $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/One.php' ) );
		$second = $resolver->resolveContext( $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/Two.php' ) );

		$this->assertSame( 'alpha/alpha.php', $first->assetKey );
		$this->assertSame( 'alpha/alpha.php', $second->assetKey );
		$this->assertSame( 1, ResolverPlugins::$installedPluginFilesCalls );
		$this->assertSame( 1, ResolverPlugins::$getPluginAsVoCalls );
	}

	public function test_distinct_plugin_directories_share_one_deduplicated_inventory() :void {
		$this->installEnvironment( [
			'gamma/gamma.php',
			'alpha/alpha.php',
			'alpha/alpha.php',
			'beta/beta.php',
		] );
		$resolver = new AssetTrustResolver();

		$contexts = [
			$resolver->resolveContext( $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/One.php' ) ),
			$resolver->resolveContext( $this->normalisePath( WP_PLUGIN_DIR.'/beta/src/Two.php' ) ),
			$resolver->resolveContext( $this->normalisePath( WP_PLUGIN_DIR.'/gamma/src/Three.php' ) ),
		];

		$this->assertSame( [
			'alpha/alpha.php',
			'beta/beta.php',
			'gamma/gamma.php',
		], \array_map( static fn( $context ) :string => $context->assetKey, $contexts ) );
		$this->assertSame( 1, ResolverPlugins::$installedPluginFilesCalls );
		$this->assertSame( 3, ResolverPlugins::$getPluginAsVoCalls );
		$this->assertCount( 0, self::$capturedErrorLogs );
	}

	public function test_ambiguous_plugin_directory_is_not_guessed_and_logs_once() :void {
		$this->installEnvironment( [ 'alpha/alpha.php', 'alpha/alternate.php' ] );
		$resolver = new AssetTrustResolver();
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );

		foreach ( [ 'first', 'memoized' ] as $attempt ) {
			try {
				$resolver->resolveContext( $path );
				$this->fail( \sprintf( 'Expected ambiguous ownership on %s attempt.', $attempt ) );
			}
			catch ( AmbiguousAssetFileException $e ) {
				$this->assertStringContainsString( 'alpha', $e->getMessage() );
			}
		}

		$this->assertCount( 1, self::$capturedErrorLogs );
		$this->assertStringContainsString( 'candidate_count=2', self::$capturedErrorLogs[ 0 ] );
		$this->assertSame( 1, ResolverPlugins::$installedPluginFilesCalls );
		$this->assertSame( 0, ResolverPlugins::$getPluginAsVoCalls );
	}

	public function test_plugin_inventory_changes_are_visible_only_after_reset() :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ] );
		$resolver = new AssetTrustResolver();
		$resolver->resolveContext( $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' ) );

		ServicesState::mergeItems( [
			'service_wpplugins' => new ResolverPlugins( [
				'alpha/alpha.php',
				'beta/beta.php',
				'beta/alternate.php',
			] ),
		] );
		$betaPath = $this->normalisePath( WP_PLUGIN_DIR.'/beta/src/File.php' );
		$this->assertResolveContextMiss( $resolver, $betaPath );
		$this->assertSame( 1, ResolverPlugins::$installedPluginFilesCalls );
		$this->assertCount( 0, self::$capturedErrorLogs );

		AssetTrustResolver::resetMemoization();
		try {
			$resolver->resolveContext( $betaPath );
			$this->fail( 'Expected refreshed inventory to expose ambiguous beta ownership.' );
		}
		catch ( AmbiguousAssetFileException $e ) {
			$this->assertStringContainsString( 'beta', $e->getMessage() );
		}

		$this->assertSame( 2, ResolverPlugins::$installedPluginFilesCalls );
		$this->assertCount( 1, self::$capturedErrorLogs );
		$this->assertStringContainsString( 'candidate_count=2', self::$capturedErrorLogs[ 0 ] );
	}

	public function test_cached_plugin_context_does_not_refresh_asset_version_until_reset() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '1.0.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();
		$first = $resolver->resolveContext( $path );
		$this->assertSame( '1.0.0', $first->assetVersion );

		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '0.9.0', '1.1.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$second = $resolver->resolveContext( $path );

		$this->assertSame( '1.0.0', $second->assetVersion );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );

		AssetTrustResolver::resetMemoization();
		$third = $resolver->resolveContext( $path );

		$this->assertSame( '1.1.0', $third->assetVersion );
		$this->assertSame( $first->assetType, $third->assetType );
		$this->assertSame( $first->assetKey, $third->assetKey );
		$this->assertSame( $first->relativePath, $third->relativePath );
	}

	public function test_plugin_hash_data_for_cached_context_uses_cached_asset_version() :void {
		$cacheRoot = $this->createTrackedTempDir( 'shield-resolver-test-resolver-store-' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$this->installHashStoreEnvironment(
			new ResolverPlugins( [ 'alpha/alpha.php' ], '1.0.0' ),
			new ResolverThemes( [] ),
			$cacheRoot
		);
		$this->writeStore( new ResolverPluginVo( 'alpha/alpha.php', '1.0.0' ), [
			'src/File.php' => self::PLUGIN_HASH,
		], $hashDir );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveContext( $path );

		ServicesState::mergeItems( [
			'service_wpplugins' => new ResolverPlugins( [ 'alpha/alpha.php' ], '0.9.0', '1.1.0' ),
			'service_wpthemes'  => new ResolverThemes( [] ),
		] );
		$hashData = $resolver->getHashDataForContext( $path, $context );

		$this->assertSame( '1.0.0', $hashData[ 'asset_version' ] );
		$this->assertSame( [ self::PLUGIN_HASH ], $hashData[ 'hashes' ] );
		$this->assertSame( 1, ResolverPlugins::$getPluginAsVoCalls );
	}

	public function test_root_plugins_resolve_and_load_exact_independent_hash_contexts() :void {
		$cacheRoot = $this->createTrackedTempDir( 'shield-resolver-root-plugin-' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$plugins = new ResolverPlugins( [ 'First.php', 'Second.php' ], '1.0.0' );
		$this->installHashStoreEnvironment( $plugins, new ResolverThemes( [] ), $cacheRoot );
		$this->writeStore( new ResolverPluginVo( 'First.php', '1.0.0' ), [
			'First.php' => self::FIRST_HASH,
		], $hashDir );
		$this->writeStore( new ResolverPluginVo( 'Second.php', '1.0.0' ), [
			'second.php' => self::SECOND_HASH,
		], $hashDir );
		$resolver = new AssetTrustResolver();
		$firstPath = $this->normalisePath( WP_PLUGIN_DIR.'/First.php' );
		$secondPath = $this->normalisePath( WP_PLUGIN_DIR.'/Second.php' );

		$first = $resolver->resolveContext( $firstPath );
		$second = $resolver->resolveContext( $secondPath );
		$firstHashes = $resolver->getHashDataForContext( $firstPath, $first );
		$secondHashes = $resolver->getHashDataForContext( $secondPath, $second );

		$this->assertSame( [ 'plugin', 'First.php', '1.0.0', 'First.php' ], [
			$first->assetType,
			$first->assetKey,
			$first->assetVersion,
			$first->relativePath,
		] );
		$this->assertSame( [ 'plugin', 'Second.php', '1.0.0', 'Second.php' ], [
			$second->assetType,
			$second->assetKey,
			$second->assetVersion,
			$second->relativePath,
		] );
		$this->assertSame( [ self::FIRST_HASH ], $firstHashes[ 'hashes' ] );
		$this->assertSame( [ self::SECOND_HASH ], $secondHashes[ 'hashes' ] );
		$this->assertFalse( $firstHashes[ 'trusted_source' ] );
		$this->assertFalse( $secondHashes[ 'trusted_source' ] );
		$this->assertSame( 0, ResolverPlugins::$installedPluginFilesCalls );
		$this->assertSame( 2, ResolverPlugins::$getPluginAsVoCalls );

		$resolver->resolveContext( $firstPath );
		$resolver->getHashDataForContext( $firstPath, $first );
		$this->assertSame( 2, ResolverPlugins::$getPluginAsVoCalls );
	}

	public function test_stored_verification_reports_unrecognised_file_with_local_basis() :void {
		$cacheRoot = $this->createTrackedTempDir( 'shield-resolver-local-store-' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$this->installHashStoreEnvironment(
			new ResolverPlugins( [ 'alpha/alpha.php' ], '1.0.0' ),
			new ResolverThemes( [] ),
			$cacheRoot
		);
		$this->writeStore( new ResolverPluginVo( 'alpha/alpha.php', '1.0.0' ), [
			'other.php' => self::PLUGIN_HASH,
		], $hashDir );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveContext( $path );

		$result = $resolver->verifyStoredContext( $path, $context );

		$this->assertInstanceOf( HashVerificationResult::class, $result );
		$this->assertFalse( $result->recognisedInSnapshot );
		$this->assertFalse( $result->verified );
		$this->assertFalse( $result->trustedSource );
		$this->assertSame( HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE, $result->comparisonBasis );
	}

	public function test_missing_stored_snapshot_returns_no_comparison() :void {
		$cacheRoot = $this->createTrackedTempDir( 'shield-resolver-missing-store-' );
		$this->installHashStoreEnvironment(
			new ResolverPlugins( [ 'alpha/alpha.php' ], '1.0.0' ),
			new ResolverThemes( [] ),
			$cacheRoot
		);
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveContext( $path );

		$this->assertNull( $resolver->verifyStoredContext( $path, $context ) );
	}

	public function test_stored_verification_fails_closed_when_cached_snapshot_is_deleted() :void {
		$cacheRoot = $this->createTrackedTempDir( 'shield-resolver-fresh-store-' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$asset = new ResolverPluginVo( 'alpha/alpha.php', '1.0.0' );
		$this->installHashStoreEnvironment(
			new ResolverPlugins( [ 'alpha/alpha.php' ], '1.0.0' ),
			new ResolverThemes( [] ),
			$cacheRoot
		);
		$this->writeStore( $asset, [ 'other.php' => self::PLUGIN_HASH ], $hashDir );
		$path = $this->normalisePath( WP_PLUGIN_DIR.'/alpha/src/File.php' );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveCurrentContext( $path );
		$store = ( new Store( $asset, true ) )->setWorkingDir( $hashDir );

		$this->assertInstanceOf( HashVerificationResult::class, $resolver->verifyStoredContext( $path, $context ) );
		\unlink( $store->getSnapStorePath() );
		$this->assertNull( $resolver->verifyStoredContext( $path, $context ) );
	}

	public function test_repeated_same_theme_path_reuses_full_path_context() :void {
		$this->installEnvironment( [], [ 'clean' ] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/File.php' );
		$resolver = new AssetTrustResolver();

		$first = $resolver->resolveContext( $path );
		$callsAfterFirst = [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		];
		$second = $resolver->resolveContext( $path );

		$this->assertSame( 'theme', $first->assetType );
		$this->assertSame( 'clean', $first->assetKey );
		$this->assertSame( 'inc/File.php', $first->relativePath );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );
		$this->assertSame( $callsAfterFirst, [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		] );
	}

	public function test_theme_context_uses_reloaded_asset_version() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '0.9.0', '1.0.0' ),
		] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/File.php' );

		$context = ( new AssetTrustResolver() )->resolveContext( $path );

		$this->assertSame( '1.0.0', $context->assetVersion );
		$this->assertSame( 1, ResolverThemes::$getThemeAsVoCalls );
	}

	public function test_repeated_theme_directory_contexts_load_asset_once() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '1.0.0' ),
		] );
		$resolver = new AssetTrustResolver();

		$first = $resolver->resolveContext( $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/One.php' ) );
		$second = $resolver->resolveContext( $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/Two.php' ) );

		$this->assertSame( 'clean', $first->assetKey );
		$this->assertSame( 'clean', $second->assetKey );
		$this->assertSame( 1, ResolverThemes::$getThemesCalls );
		$this->assertSame( 1, ResolverThemes::$getThemeAsVoCalls );
	}

	public function test_cached_theme_context_does_not_refresh_asset_version_until_reset() :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '1.0.0' ),
		] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/File.php' );
		$resolver = new AssetTrustResolver();
		$first = $resolver->resolveContext( $path );
		$this->assertSame( '1.0.0', $first->assetVersion );

		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '0.9.0', '1.1.0' ),
		] );
		$second = $resolver->resolveContext( $path );

		$this->assertSame( '1.0.0', $second->assetVersion );
		$this->assertSame( $first->assetType, $second->assetType );
		$this->assertSame( $first->assetKey, $second->assetKey );
		$this->assertSame( $first->relativePath, $second->relativePath );

		AssetTrustResolver::resetMemoization();
		$third = $resolver->resolveContext( $path );

		$this->assertSame( '1.1.0', $third->assetVersion );
		$this->assertSame( $first->assetType, $third->assetType );
		$this->assertSame( $first->assetKey, $third->assetKey );
		$this->assertSame( $first->relativePath, $third->relativePath );
	}

	public function test_theme_hash_data_for_cached_context_uses_cached_asset_version() :void {
		$cacheRoot = $this->createTrackedTempDir( 'shield-resolver-test-resolver-store-' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$this->installHashStoreEnvironment(
			new ResolverPlugins( [] ),
			new ResolverThemes( [ 'clean' ], '1.0.0' ),
			$cacheRoot
		);
		$this->writeStore( new ResolverThemeVo( 'clean', '1.0.0' ), [
			'inc/File.php' => self::THEME_HASH,
		], $hashDir );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/themes/clean/inc/File.php' );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveContext( $path );

		ServicesState::mergeItems( [
			'service_wpplugins' => new ResolverPlugins( [] ),
			'service_wpthemes'  => new ResolverThemes( [ 'clean' ], '0.9.0', '1.1.0' ),
		] );
		$hashData = $resolver->getHashDataForContext( $path, $context );

		$this->assertSame( '1.0.0', $hashData[ 'asset_version' ] );
		$this->assertSame( [ self::THEME_HASH ], $hashData[ 'hashes' ] );
		$this->assertSame( 1, ResolverThemes::$getThemeAsVoCalls );
	}

	public function test_outside_asset_roots_is_rejected() :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ], [ 'clean' ] );
		$path = $this->normalisePath( WP_CONTENT_DIR.'/uploads/outside.php' );
		$resolver = new AssetTrustResolver();

		$this->assertResolveContextMiss( $resolver, $path );
	}

	/**
	 * @dataProvider unknownAssetDirectoryProvider
	 */
	public function test_repeated_unknown_asset_directory_miss_is_memoized( string $path ) :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ], [ 'clean' ] );
		$resolver = new AssetTrustResolver();
		$path = $this->normalisePath( $path );

		$this->assertResolveContextMiss( $resolver, $path );
		$callsAfterFirst = [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		];
		$this->assertGreaterThan( 0, ResolverPlugins::$installedPluginFilesCalls + ResolverThemes::$getThemesCalls );
		$this->assertResolveContextMiss( $resolver, $path );
		$this->assertSame( $callsAfterFirst, [
			ResolverFs::$isAbsPathCalls,
			ResolverPlugins::$installedPluginFilesCalls,
			ResolverThemes::$getThemesCalls,
		] );
	}

	public static function unknownAssetDirectoryProvider() :array {
		return [
			'unknown plugin directory' => [ WP_PLUGIN_DIR.'/missing/file.php' ],
			'unknown theme directory'  => [ WP_CONTENT_DIR.'/themes/missing/file.php' ],
		];
	}

	/**
	 * @dataProvider siblingPathProvider
	 */
	public function test_root_sibling_paths_are_rejected( string $path ) :void {
		$this->installEnvironment( [ 'alpha/alpha.php' ], [ 'clean' ] );

		$this->assertResolveContextMiss( new AssetTrustResolver(), $this->normalisePath( $path ) );
	}

	public static function siblingPathProvider() :array {
		return [
			'plugin root sibling prefix'    => [ WP_PLUGIN_DIR.'alpha/file.php' ],
			'plugin root sibling backslash' => [ \str_replace( '/', '\\', WP_PLUGIN_DIR.'alpha/file.php' ) ],
			'theme root sibling prefix'     => [ WP_CONTENT_DIR.'/themesclean/file.php' ],
			'theme root sibling backslash'  => [ \str_replace( '/', '\\', WP_CONTENT_DIR.'/themesclean/file.php' ) ],
		];
	}

	private function installEnvironment( array $pluginFiles = [], array $themes = [] ) :void {
		ServicesState::installItems( [
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => new ResolverPlugins( $pluginFiles ),
			'service_wpthemes'  => new ResolverThemes( $themes ),
		] );
	}

	private function installHashStoreEnvironment( Plugins $plugins, Themes $themes, string $cacheRoot ) :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpfs'      => new ResolverFs(),
			'service_wpplugins' => $plugins,
			'service_wpthemes'  => $themes,
		] );
		$this->installController( $cacheRoot );
	}

	private function installController( string $cacheRoot ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->caps = new class {
			public function canScanPluginsThemesRemote() :bool {
				return false;
			}
		};
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $cacheRoot );

		PluginControllerInstaller::install( $controller );
	}

	private function writeStore( $asset, array $hashes, string $hashDir, array $sourceMeta = [] ) :void {
		( new Store( $asset, true ) )
			->setWorkingDir( $hashDir )
			->setSnapData( $hashes )
			->setSnapMeta( \array_merge( [
				'version'   => $asset->Version,
				'unique_id' => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			], $sourceMeta ) )
			->save();
	}

	private function assertResolveContextMiss( AssetTrustResolver $resolver, string $path ) :void {
		try {
			$resolver->resolveContext( $path );
		}
		catch ( NonAssetFileException $e ) {
			$this->assertInstanceOf( NonAssetFileException::class, $e );
			return;
		}
		$this->fail( 'Expected asset context resolution to miss.' );
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

class ResolverFs extends SnapshotFs {
	public static int $isAbsPathCalls = 0;

	public function isAbsPath( $path ) {
		self::$isAbsPathCalls++;
		return \preg_match( '#^([A-Z]:)?/#i', \str_replace( '\\', '/', (string)$path ) ) === 1;
	}
}

class ResolverPlugins extends Plugins {
	public static int $installedPluginFilesCalls = 0;

	public static int $getPluginAsVoCalls = 0;

	private array $pluginFiles;

	private string $version;

	private ?string $reloadVersion;

	public function __construct( array $pluginFiles, string $version = '1.0.0', ?string $reloadVersion = null ) {
		$this->pluginFiles = $pluginFiles;
		$this->version = $version;
		$this->reloadVersion = $reloadVersion;
	}

	public function getInstalledPluginFiles() :array {
		self::$installedPluginFilesCalls++;
		return $this->pluginFiles;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		self::$getPluginAsVoCalls++;
		return \in_array( $file, $this->pluginFiles, true )
			? new ResolverPluginVo( $file, $reload && $this->reloadVersion !== null ? $this->reloadVersion : $this->version )
			: null;
	}
}

class ResolverThemes extends Themes {
	public static int $getThemesCalls = 0;

	public static int $getThemeAsVoCalls = 0;

	private array $themes;

	private string $version;

	private ?string $reloadVersion;

	public function __construct( array $themes, string $version = '1.0.0', ?string $reloadVersion = null ) {
		$this->themes = $themes;
		$this->version = $version;
		$this->reloadVersion = $reloadVersion;
	}

	public function getThemes() :array {
		self::$getThemesCalls++;
		return \array_map(
			static fn( string $stylesheet ) => new class( $stylesheet ) {
				private string $stylesheet;

				public function __construct( string $stylesheet ) {
					$this->stylesheet = $stylesheet;
				}

				public function get_stylesheet() :string {
					return $this->stylesheet;
				}
			},
			$this->themes
		);
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		self::$getThemeAsVoCalls++;
		return \in_array( $stylesheet, $this->themes, true )
			? new ResolverThemeVo( $stylesheet, $reload && $this->reloadVersion !== null ? $this->reloadVersion : $this->version )
			: null;
	}
}

class ResolverPluginVo extends WpPluginVo {
	public string $file;
	public string $Version;

	public function __construct( string $file, string $version = '1.0.0' ) {
		$this->file = $file;
		$this->Version = $version;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
				return 'plugin';
			case 'unique_id':
				return $this->file;
			case 'slug':
				return \dirname( $this->file );
			default:
				return $this->{$key} ?? null;
		}
	}

	public function getInstallDir() :string {
		return '';
	}

	public function isWpOrg() :bool {
		return false;
	}
}

class ResolverThemeVo extends WpThemeVo {
	public string $stylesheet;
	public string $Version;

	public function __construct( string $stylesheet, string $version = '1.0.0' ) {
		$this->stylesheet = $stylesheet;
		$this->Version = $version;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
			case 'unique_id':
			case 'slug':
				return $key === 'asset_type' ? 'theme' : $this->stylesheet;
			case 'is_child':
				return false;
			default:
				return $this->{$key} ?? null;
		}
	}

	public function getInstallDir() :string {
		return '';
	}

	public function isWpOrg() :bool {
		return false;
	}
}
