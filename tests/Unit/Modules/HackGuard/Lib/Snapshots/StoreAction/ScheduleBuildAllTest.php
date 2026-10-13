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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\{Build, ScheduleBuildAll};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\BuildHashesFromApi;
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
use FernleafSystems\Wordpress\Services\Core\Db;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\{ApiBase, Hashes};

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
	private array $httpQueryCacheSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		self::$capturedErrorLogs = [];
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->httpQueryCacheSnapshot = $this->getStaticProperty( ApiBase::class, 'QueryCache' );
		$this->setStaticProperty( ApiBase::class, 'QueryCache', [] );
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
		Functions\when( 'is_wp_error' )->alias(
			static fn( $value ) :bool => $value instanceof \WP_Error
		);
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
		$this->setStaticProperty( ApiBase::class, 'QueryCache', $this->httpQueryCacheSnapshot );
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->removeWrittenFixtureFiles();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
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
		$this->assertTrue( $this->loadStore( $asset )->isUsable() );
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
		$this->assertSame( 'md5', $store->getSnapMeta()[ 'algo' ] );
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
		$this->assertSame( 'md5', $store->getSnapMeta()[ 'algo' ] );
		$this->assertCount( 1, $urls );
		$this->assertStringContainsString( '/hashes/t/published-theme/4.5.6/md5', $urls[ 0 ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideUnusablePublishedMaps' )]
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
		$this->assertSame( 'md5', $store->getSnapMeta()[ 'algo' ] );
		$this->assertCount( 1, $urls );
	}

	public static function provideUnusablePublishedMaps() :array {
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideSnapshotAlgorithms' )]
	public function test_selected_algorithm_is_requested_and_persisted_for_plugin_and_theme( string $algorithm ) :void {
		$plugin = new SnapshotPluginVo( 'selected-plugin/plugin.php', '1.2.3' );
		$theme = new SnapshotThemeVo( 'selected-theme', '4.5.6' );
		$plugin->wpOrg = $theme->wpOrg = true;
		$this->installBuildEnvironment( [ $plugin ], $this->makeTempDir( 'selected-'.$algorithm ), [ $theme ] );
		$hash = \hash( $algorithm, 'published reference' );
		$urls = [];
		$this->mockPublishedResponse( [ 'reference.php' => $hash ], $urls );

		$this->invokeBuild( $algorithm );

		$this->assertSame( [
			ApiBase::API_URL.'/v1/hashes/p/selected-plugin/1.2.3/'.$algorithm,
			ApiBase::API_URL.'/v1/hashes/t/selected-theme/4.5.6/'.$algorithm,
		], $urls );
		foreach ( [ $plugin, $theme ] as $asset ) {
			$store = $this->loadStore( $asset );
			$this->assertSame( [ 'reference.php' => $hash ], $store->getSnapData() );
			$this->assertSame( $algorithm, $store->getSnapMeta()[ 'algo' ] );
			$this->assertTrue( $store->getSnapMeta()[ 'live_hashes' ] );
		}
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'malformedPremiumCatalogs' )]
	public function test_malformed_premium_catalog_never_confers_support( string $type, $info ) :void {
		$asset = $type === 'plugin'
			? new SnapshotPluginVo( 'premium/plugin.php', '1.0.0' )
			: new SnapshotThemeVo( 'premium', '1.0.0' );
		$this->installBuildEnvironment( [], $this->makeTempDir( 'premium-invalid' ) );
		$urls = [];
		$this->mockPremiumCatalog( $info, $urls );

		$warnings = [];
		\set_error_handler( static function ( int $severity, string $message ) use ( &$warnings ) :bool {
			$warnings[] = [ $severity, $message ];
			return true;
		} );
		$failure = null;
		try {
			( new BuildHashesFromApi() )->build( $asset, 'sha256' );
		}
		catch ( \Exception $e ) {
			$failure = $e;
		}
		finally {
			\restore_error_handler();
		}

		$this->assertSame( [], $warnings );
		$this->assertInstanceOf( \Exception::class, $failure );
		$this->assertSame( [ ApiBase::API_URL.'/v1/hashes/info' ], $urls );
	}

	public static function malformedPremiumCatalogs() :array {
		$cases = [];
		foreach ( [ 'plugin' => 'plugins', 'theme' => 'themes' ] as $type => $group ) {
			foreach ( [
				'null info' => null,
				'scalar info' => true,
				'missing premium' => [],
				'scalar premium' => [ 'supported_premium' => true ],
				'missing group' => [ 'supported_premium' => [] ],
				'scalar group' => [ 'supported_premium' => [ $group => true ] ],
				'invalid rows' => [ 'supported_premium' => [ $group => [
					null, true, 'premium', [],
					[ 'slug' => true, 'file' => true, 'name' => true ],
					[ 'slug' => 0, 'file' => [], 'name' => false ],
					[ 'slug' => '', 'file' => " \t", 'name' => null ],
				] ] ],
			] as $label => $info ) {
				$cases[ $type.' '.$label ] = [ $type, $info ];
			}
		}
		return $cases;
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'independentPremiumIdentifiers' )]
	public function test_valid_premium_identifier_survives_malformed_siblings( string $type, string $field ) :void {
		$asset = $type === 'plugin'
			? new SnapshotPluginVo( 'premium/plugin.php', '1.0.0' )
			: new SnapshotThemeVo( 'premium', '1.0.0' );
		$this->installBuildEnvironment( [], $this->makeTempDir( 'premium-valid' ) );
		$identifier = $field === 'name'
			? $asset->Name
			: ( $field === 'file' && $type === 'plugin' ? $asset->file : 'premium' );
		$urls = [];
		$this->mockPremiumCatalog( [
			'supported_premium' => [
				$type === 'plugin' ? 'plugins' : 'themes' => [
					null, true, [ 'slug' => [], 'file' => false, 'name' => 12 ],
					[ $field => $identifier, 'ignored' => true ],
				],
			],
		], $urls );

		( new Build() )->setAsset( $asset )->run( 'sha256' );

		$snapshot = $this->loadStore( $asset )->getUsableSnapshot();
		$this->assertNotNull( $snapshot );
		$this->assertSame( [ 'reference.php' => \hash( 'sha256', 'published reference' ) ], $snapshot[ 'data' ] );
		$this->assertSame( 'sha256', $snapshot[ 'meta' ][ 'algo' ] );
		$this->assertTrue( $snapshot[ 'meta' ][ 'live_hashes' ] );
		$this->assertSame( [
			ApiBase::API_URL.'/v1/hashes/info',
			ApiBase::API_URL.'/v1/hashes/'.( $type === 'plugin' ? 'p' : 't' ).'/premium/1.0.0/sha256',
		], $urls );
	}

	public static function independentPremiumIdentifiers() :array {
		return [
			[ 'plugin', 'slug' ], [ 'plugin', 'file' ], [ 'plugin', 'name' ],
			[ 'theme', 'slug' ], [ 'theme', 'file' ], [ 'theme', 'name' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalidPremiumSlugs' )]
	public function test_name_match_does_not_adopt_invalid_premium_slug( array $row ) :void {
		$asset = new ScheduleBuildAllEmptySlugPluginVo( 'premium/plugin.php', '1.0.0' );
		$this->installBuildEnvironment( [], $this->makeTempDir( 'premium-slug' ) );
		$row[ 'name' ] = $asset->Name;
		$urls = [];
		$this->mockPremiumCatalog( [ 'supported_premium' => [ 'plugins' => [ $row ] ] ], $urls );

		$this->assertNull( ( new BuildHashesFromApi() )->build( $asset, 'sha256' ) );
		$this->assertSame( '', $asset->slug );
		$this->assertSame( [ ApiBase::API_URL.'/v1/hashes/info' ], $urls );
	}

	public static function invalidPremiumSlugs() :array {
		return [ [ [] ], [ [ 'slug' => true ] ], [ [ 'slug' => " \t" ] ] ];
	}

	public function test_name_match_adopts_valid_premium_slug_for_request() :void {
		$asset = new ScheduleBuildAllEmptySlugPluginVo( 'premium/plugin.php', '1.0.0' );
		$this->installBuildEnvironment( [], $this->makeTempDir( 'premium-adopt' ) );
		$urls = [];
		$this->mockPremiumCatalog( [ 'supported_premium' => [ 'plugins' => [
			[ 'slug' => 'provider-slug', 'name' => $asset->Name ],
		] ] ], $urls );

		$this->assertSame( [ 'reference.php' => \hash( 'sha256', 'published reference' ) ],
			( new BuildHashesFromApi() )->build( $asset, 'sha256' ) );
		$this->assertSame( 'provider-slug', $asset->slug );
		$this->assertSame( [
			ApiBase::API_URL.'/v1/hashes/info',
			ApiBase::API_URL.'/v1/hashes/p/provider-slug/1.0.0/sha256',
		], $urls );
	}

	private function mockPremiumCatalog( $info, array &$urls ) :void {
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url ) use ( $info, &$urls ) :array {
				$urls[] = $url;
				return self::httpResponse( \strpos( $url, '/hashes/info' ) !== false
					? [ 'info' => $info ]
					: [ 'hashes' => [ 'reference.php' => \hash( 'sha256', 'published reference' ) ] ] );
			}
		);
	}

	public static function provideSnapshotAlgorithms() :array {
		return [ 'md5' => [ 'md5' ], 'sha1' => [ 'sha1' ], 'sha256' => [ 'sha256' ] ];
	}

	public function test_selection_can_vary_by_asset_without_changing_default_for_sibling() :void {
		$selected = new SnapshotPluginVo( 'per-asset-selected/plugin.php', '1.0.0' );
		$default = new SnapshotPluginVo( 'per-asset-default/plugin.php', '1.0.0' );
		$selected->wpOrg = $default->wpOrg = true;
		$this->installBuildEnvironment( [ $selected, $default ], $this->makeTempDir( 'per-asset' ) );
		Functions\when( 'wp_remote_request' )->alias(
			static fn( string $url ) :array => self::httpResponse( [
				'hashes' => [ 'plugin.php' => \hash( \basename( $url ), 'reference' ) ],
			] )
		);

		( new Build() )->setAsset( $selected )->run( 'sha256' );
		( new Build() )->setAsset( $default )->run();

		foreach ( [ [ $selected, 'sha256' ], [ $default, 'md5' ] ] as [ $asset, $algorithm ] ) {
			$store = $this->loadStore( $asset );
			$this->assertSame( [ 'plugin.php' => \hash( $algorithm, 'reference' ) ], $store->getSnapData() );
			$this->assertSame( $algorithm, $store->getSnapMeta()[ 'algo' ] );
			$this->assertTrue( $store->getSnapMeta()[ 'live_hashes' ] );
		}
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideInvalidSnapshotSelections' )]
	public function test_invalid_selection_leaves_existing_snapshot_and_never_requests_or_builds( $selection ) :void {
		$asset = new SnapshotPluginVo( 'invalid-selected/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installBuildEnvironment( [ $asset ], $this->makeTempDir( 'invalid-selected' ) );
		$this->writeFile( WP_PLUGIN_DIR.'/'.$asset->file, 'installed content differs from baseline' );
		$this->writeStore( $asset, [ 'plugin.php' => self::MD5 ], [
			'unique_id' => $asset->file, 'version' => $asset->Version, 'algo' => 'md5', 'live_hashes' => false,
		] );
		$store = $this->loadStore( $asset );
		$before = [ \file_get_contents( $store->getSnapStorePath() ), \file_get_contents( $store->getSnapStoreMetaPath() ) ];
		$requests = 0;
		Functions\when( 'wp_remote_request' )->alias( static function () use ( &$requests ) :array {
			$requests++;
			return self::httpResponse( [ 'hashes' => [] ] );
		} );
		foreach ( [
			static fn() => ( new Build() )->setAsset( $asset )->run( $selection ),
			static fn() => ( new ScheduleBuildAll() )->build( $selection ),
		] as $operation ) {
			$failure = null;
			try {
				$operation();
			}
			catch ( \InvalidArgumentException | \TypeError $e ) {
				$failure = $e;
			}

			$this->assertInstanceOf( \is_string( $selection ) ? \InvalidArgumentException::class : \TypeError::class, $failure );
			$this->assertSame( 0, $requests );
			$this->assertSame( $before, [ \file_get_contents( $store->getSnapStorePath() ), \file_get_contents( $store->getSnapStoreMetaPath() ) ] );
		}
	}

	public static function provideInvalidSnapshotSelections() :array {
		return [
			'empty' => [ '' ], 'null' => [ null ], 'boolean' => [ false ], 'array' => [ [] ],
			'object' => [ new \stdClass() ], 'unsupported' => [ 'sha512' ], 'sha384' => [ 'sha384' ],
			'uppercase' => [ 'SHA256' ], 'whitespace' => [ ' sha256 ' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideWrongAlgorithmPublishedMaps' )]
	public function test_wrong_algorithm_published_response_builds_raw_sha256_local_baseline( array $published ) :void {
		$asset = new SnapshotPluginVo( 'wrong-algorithm/plugin.php', '2.0.0' );
		$asset->wpOrg = true;
		$this->installBuildEnvironment( [ $asset ], $this->makeTempDir( 'wrong-algorithm' ) );
		$path = WP_PLUGIN_DIR.'/'.$asset->file;
		$this->writeFile( $path, "<?php\r\n// raw local fallback\r" );
		$urls = [];
		$this->mockPublishedResponse( $published, $urls );

		$this->invokeBuild( 'sha256' );

		$store = $this->loadStore( $asset );
		$this->assertSame( [ 'plugin.php' => \hash_file( 'sha256', $path ) ], $store->getSnapData() );
		$this->assertSame( 'sha256', $store->getSnapMeta()[ 'algo' ] );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertSame( [ ApiBase::API_URL.'/v1/hashes/p/wrong-algorithm/2.0.0/sha256' ], $urls );
	}

	public static function provideWrongAlgorithmPublishedMaps() :array {
		return [
			'wrong length' => [ [ 'plugin.php' => self::MD5 ] ],
			'mixed algorithms' => [ [ 'plugin.php' => \str_repeat( 'b', 64 ), 'other.php' => self::MD5 ] ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideHttpCacheAssetTypes' )]
	public function test_published_http_cache_separates_sequential_algorithms( string $type ) :void {
		$this->installBuildEnvironment( [], $this->makeTempDir( 'http-cache-'.$type ) );
		$requests = [];
		Functions\when( 'wp_remote_request' )->alias( static function ( string $url ) use ( &$requests ) :array {
			$requests[] = $url;
			return self::httpResponse( [ 'hashes' => [ 'file.php' => \hash( \basename( $url ), 'reference bytes' ) ] ] );
		} );
		$wrapper = ( $type === 'p' ? new Hashes\Plugin() : new Hashes\Theme() )->setUseQueryCache( true );

		foreach ( [ 'md5', 'sha256', 'md5', 'sha256' ] as $algorithm ) {
			$this->assertSame( [ 'file.php' => \hash( $algorithm, 'reference bytes' ) ], $wrapper->getHashes( 'cache-algorithm', '1.0.0', $algorithm ) );
		}

		$this->assertSame( [
			ApiBase::API_URL.'/v1/hashes/'.$type.'/cache-algorithm/1.0.0/md5',
			ApiBase::API_URL.'/v1/hashes/'.$type.'/cache-algorithm/1.0.0/sha256',
		], $requests );
	}

	public static function provideHttpCacheAssetTypes() :array {
		return [ 'plugin' => [ 'p' ], 'theme' => [ 't' ] ];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideHttpCacheAssetTypes' )]
	public function test_snapshot_selection_preserves_exact_crowd_map_identity_and_submission( string $type ) :void {
		$asset = $type === 'p'
			? new SnapshotPluginVo( 'crowd-protocol/plugin.php', '1.0.0' )
			: new SnapshotThemeVo( 'crowd-protocol', '1.0.0' );
		$expectedMap = [
			'file2.php'  => 'f5c5dcd4cfb1f9757df6c09711164ebbeb64f826',
			'file10.php' => 'f5c5dcd4cfb1f9757df6c09711164ebbeb64f826',
			'upper.php'  => 'f5c5dcd4cfb1f9757df6c09711164ebbeb64f826',
		];
		$expectedJson = '{"file2.php":"f5c5dcd4cfb1f9757df6c09711164ebbeb64f826","file10.php":"f5c5dcd4cfb1f9757df6c09711164ebbeb64f826","upper.php":"f5c5dcd4cfb1f9757df6c09711164ebbeb64f826"}';
		$collection = 'd0c78a7fca5818c3346d321818ff919ba54f9f5b';
		$expectedUrl = ApiBase::API_URL.'/v2/cshashes/submit/'.$collection;
		$runs = [];
		foreach ( [ 'md5', 'sha256' ] as $algorithm ) {
			$this->setStaticProperty( ApiBase::class, 'QueryCache', [] );
			$this->installBuildEnvironment(
				$type === 'p' ? [ $asset ] : [],
				$this->makeTempDir( 'crowd-'.$type.'-'.$algorithm ),
				$type === 't' ? [ $asset ] : [],
				true
			);
			$dir = $asset->getInstallDir();
			$this->writeFile( $dir.'file10.php', "first\r\nsecond\r\n" );
			$this->writeFile( $dir.'file2.php', "first\nsecond\n" );
			$this->writeFile( $dir.'UPPER.PHP', "first\rsecond\r" );
			$this->writeFile( $dir.'excluded.txt', "not submitted\r\n" );
			$crowdRequests = [];
			Functions\when( 'wp_remote_request' )->alias(
				static function ( string $url, array $args ) use ( &$crowdRequests ) :array {
					if ( \strpos( $url, '/hashes/info' ) !== false ) {
						return self::httpResponse( [ 'info' => [ 'supported_premium' => [ 'plugins' => [], 'themes' => [] ] ] ] );
					}
					$crowdRequests[] = [ $url, $args[ 'method' ], $args[ 'body' ] ?? null ];
					return self::httpResponse( $args[ 'method' ] === 'GET'
						? [ 'hashes' => [ 'submit_required' => true ] ]
						: [ 'error' => false ] );
				}
			);

			$this->invokeBuild( $algorithm );

			$store = $this->loadStore( $asset );
			$this->assertSame( $algorithm, $store->getSnapMeta()[ 'algo' ] );
			$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
			$this->assertSame( 1700000500, $store->getSnapMeta()[ 'cs_hashes_at' ] );
			$this->assertSame( \hash_file( $algorithm, $dir.'file10.php' ), $store->getSnapData()[ 'file10.php' ] );
			$this->assertCount( 2, $crowdRequests );
			$this->assertSame( [ $expectedUrl, 'GET', null ], $crowdRequests[ 0 ] );
			$this->assertSame( [ $expectedUrl, 'POST' ], \array_slice( $crowdRequests[ 1 ], 0, 2 ) );
			$body = $crowdRequests[ 1 ][ 2 ];
			$this->assertSame( [
				'type' => $type, 'slug' => 'crowd-protocol', 'version' => '1.0.0',
				'hash' => $collection, 'hashes' => $expectedMap,
			], $body );
			$this->assertSame( $expectedJson, \json_encode( $body[ 'hashes' ] ) );
			$runs[] = $crowdRequests;
		}
		$this->assertSame( $runs[ 0 ], $runs[ 1 ] );
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

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideSnapshotAlgorithms' )]
	public function test_missing_inactive_root_plugin_hashes_only_its_file_and_skips_crowdsource( string $algorithm ) :void {
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

		$this->invokeBuild( $algorithm );

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			$asset->file => \hash_file( $algorithm, $path ),
		], $store->getSnapData() );
		$this->assertSame( $algorithm, $store->getSnapMeta()[ 'algo' ] );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertSame( 0, $store->getSnapMeta()[ 'cs_hashes_at' ] );
		$this->assertFalse(
			(bool)\array_filter(
				$urls,
				static fn( string $url ) :bool => \strpos( $url, '/cshashes/submit' ) !== false
			),
			\implode( "\n", $urls )
		);
		$this->assertTrue( $store->isUsable() );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideChildThemeFlags' )]
	public function test_child_theme_uses_local_baseline_without_published_or_crowdsource_work(
		bool $activeChild,
		bool $inactiveChild,
		string $algorithm
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

		$this->invokeBuild( $algorithm );

		$store = $this->loadStore( $asset );
		$this->assertTrue( $store->isUsable() );
		$this->assertSame( [
			'style.css' => \hash_file( $algorithm, $path ),
		], $store->getSnapData() );
		$this->assertSame( $algorithm, $store->getSnapMeta()[ 'algo' ] );
		$this->assertFalse( $store->getSnapMeta()[ 'live_hashes' ] );
		$this->assertSame( 0, $store->getSnapMeta()[ 'cs_hashes_at' ] );
		$this->assertSame( 0, $requests );
	}

	public static function provideChildThemeFlags() :array {
		return [
			'active child md5'      => [ true, false, 'md5' ],
			'inactive child md5'    => [ false, true, 'md5' ],
			'active child sha256'   => [ true, false, 'sha256' ],
			'inactive child sha256' => [ false, true, 'sha256' ],
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
		$this->assertNull( $this->loadStore( $asset )->getUsableSnapshot() );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideSnapshotAlgorithms' )]
	public function test_build_classifies_before_mutation_and_promotes_only_preexisting_due_snapshot( string $algorithm ) :void {
		$missing = new SnapshotPluginVo( 'new-local/new-local.php', '1.0.0' );
		$missing->wpOrg = true;
		$due = new SnapshotPluginVo( 'due-published/due.php', '2.0.0' );
		$due->wpOrg = true;
		$root = $this->makeTempDir( 'classified-pass' );
		$this->installBuildEnvironment( [ $missing, $due ], $root );
		$this->writeFile( WP_PLUGIN_DIR.'/'.$missing->file, "<?php\n// local baseline\n" );
		$this->writeStore( $due, [
			'due.php' => \str_repeat( 'b', 32 ),
		], [
			'ts'                      => 1600000000,
			'snap_version'            => '19.0.0',
			'cs_hashes_at'            => 0,
			'unique_id'               => $due->file,
			'name'                    => $due->Name,
			'version'                 => $due->Version,
			'algo'                    => 'md5',
			'live_hashes'             => false,
			'last_live_hash_check_at' => 1699914100,
		] );

		$coordinator = new ScheduleBuildAllPromotionCoordinator();
		$controller = \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin
			->getController();
		$controller->comps->asset_coordinator = $coordinator;
		$controller->db_con = (object)[
			'scans' => new ScheduleBuildAllScansTable(),
		];
		ServicesState::mergeItems( [
			'service_wpdb' => new ScheduleBuildAllIdleDb(),
		] );

		$urls = [];
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url ) use ( &$urls, $algorithm ) :array {
				if ( \strpos( $url, '/availability' ) !== false ) {
					return ScheduleBuildAllTest::httpResponse( [ 'routes_regex' => '#^hashes$#' ] );
				}
				$urls[] = $url;
				return ScheduleBuildAllTest::httpResponse( [
					'hashes' => \strpos( $url, '/due-published/' ) !== false
						? [ 'due.php' => \hash( $algorithm, 'published reference' ) ]
						: [],
				] );
			}
		);

		$this->invokeBuild( $algorithm );

		$this->assertFalse( $this->loadStore( $missing )->getSnapMeta()[ 'live_hashes' ] );
		$this->assertTrue( $this->loadStore( $due )->getSnapMeta()[ 'live_hashes' ] );
		$this->assertSame( $algorithm, $this->loadStore( $missing )->getSnapMeta()[ 'algo' ] );
		$this->assertSame( $algorithm, $this->loadStore( $due )->getSnapMeta()[ 'algo' ] );
		$this->assertSame( [ 'due.php' => \hash( $algorithm, 'published reference' ) ], $this->loadStore( $due )->getSnapData() );
		$this->assertCount( 2, $urls );
		$this->assertCount( 1, \array_filter(
			$urls,
			static fn( string $url ) :bool => \strpos( $url, '/new-local/' ) !== false
		) );
		$this->assertCount( 1, \array_filter(
			$urls,
			static fn( string $url ) :bool => \strpos( $url, '/due-published/' ) !== false
		) );
		$this->assertSame( [ [ 'plugin', $due->file, $due->Version ] ], $coordinator->assets );
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

	private function invokeBuild( string $algorithm = 'md5' ) :void {
		( new ScheduleBuildAll() )->build( $algorithm );
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
		$this->setStaticProperty( Retrieve::class, 'sources', [ 'seed' => [
			'hashes'           => [ 'file.php' => [ self::MD5 ] ],
			'trusted_source'   => true,
			'comparison_basis' => 'published_reference',
		] ] );
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
		$this->assertSame( [], $this->getStaticProperty( Retrieve::class, 'sources' ) );
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
		$this->assertNotEmpty( $this->getStaticProperty( Retrieve::class, 'sources' ) );
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

class ScheduleBuildAllEmptySlugPluginVo extends SnapshotPluginVo {

	public string $slug = '';
}

class ScheduleBuildAllPromotionCoordinator {

	public array $assets = [];

	public function enqueuePromotionFollowUp(
		string $assetType,
		string $assetKey,
		string $requiredPublishedVersion
	) :bool {
		$this->assets[] = [ $assetType, $assetKey, $requiredPublishedVersion ];
		return true;
	}
}

class ScheduleBuildAllScansTable {

	public function getTable() :string {
		return 'shield_scans';
	}
}

class ScheduleBuildAllIdleDb extends Db {

	public function selectCustom( $query, $format = null ) :array {
		unset( $query, $format );
		return [];
	}
}
