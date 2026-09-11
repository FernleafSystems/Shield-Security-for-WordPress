<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

class PromoteLocalBaselineTestLog {

	public static array $messages = [];
}

function error_log( string $message ) :bool {
	PromoteLocalBaselineTestLog::$messages[] = $message;
	return true;
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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaseline;
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
use FernleafSystems\Wordpress\Services\Core\Db;

class PromoteLocalBaselineTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;
	use TempDirLifecycleTrait;

	private const NOW = 1700000500;
	private const ORIGINAL_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const PUBLISHED_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

	private array $servicesSnapshot = [];
	private bool $hadWpdb = false;
	private $wpdbSnapshot;
	private PromoteLocalBaselineTestFs $fs;
	private PromoteLocalBaselineTestDb $db;
	private PromoteLocalBaselineTestCoordinator $coordinator;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->hadWpdb = \array_key_exists( 'wpdb', $GLOBALS );
		$this->wpdbSnapshot = $GLOBALS[ 'wpdb' ] ?? null;
		$GLOBALS[ 'wpdb' ] = (object)[ 'last_error' => '' ];
		\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaselineTestLog::$messages = [];
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
		Functions\when( 'path_join' )->alias(
			static fn( string $a, string $b ) :string => \str_replace(
				'\\',
				'/',
				\rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' )
			)
		);
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => (string)\json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );
		Functions\when( 'wp_generate_password' )->alias(
			static fn( int $length, bool $specialChars = true ) :string => \substr( \str_repeat( 'a', $length ), 0, $length )
		);
		Functions\when( 'untrailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( \str_replace( '\\', '/', $path ), '/' )
		);
		Functions\when( 'trailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( \str_replace( '\\', '/', $path ), '/' ).'/'
		);
	}

	protected function tearDown() :void {
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		if ( $this->hadWpdb ) {
			$GLOBALS[ 'wpdb' ] = $this->wpdbSnapshot;
		}
		else {
			unset( $GLOBALS[ 'wpdb' ] );
		}
		parent::tearDown();
	}

	public function test_candidacy_uses_persisted_source_and_daily_boundary_for_plugins_and_themes() :void {
		$absent = new SnapshotPluginVo( 'due-absent/plugin.php', '1.0.0' );
		$boundary = new SnapshotThemeVo( 'due-boundary', '2.0.0' );
		$notYet = new SnapshotPluginVo( 'not-yet/plugin.php', '3.0.0' );
		$invalid = new SnapshotThemeVo( 'due-invalid', '4.0.0' );
		$unknown = new SnapshotThemeVo( 'due-unknown', '4.1.0' );
		$published = new SnapshotPluginVo( 'published/plugin.php', '5.0.0' );
		$this->installEnvironment( [ $absent, $notYet, $published ], [ $boundary, $invalid, $unknown ] );

		$absentSnapshot = $this->writeLocalStore( $absent );
		unset( $absentSnapshot[ 'meta' ][ 'live_hashes' ] );
		$this->rawStore( $absent )
			->setSnapMeta( $absentSnapshot[ 'meta' ] )
			->saveMeta();
		$this->writeLocalStore( $boundary, [
			'last_live_hash_check_at' => self::NOW - 86400,
		] );
		$this->writeLocalStore( $notYet, [
			'last_live_hash_check_at' => self::NOW - 86399,
		] );
		$this->writeLocalStore( $invalid, [
			'last_live_hash_check_at' => 'invalid',
		] );
		$this->writeLocalStore( $unknown, [
			'live_hashes' => 'unknown',
		] );
		$this->writeLocalStore( $published, [
			'live_hashes'             => true,
			'last_live_hash_check_at' => 0,
		] );

		$this->assertTrue( PromoteLocalBaseline::isDue( $this->loadSnapshot( $absent ), self::NOW ) );
		$this->assertTrue( PromoteLocalBaseline::isDue( $this->loadSnapshot( $boundary ), self::NOW ) );
		$this->assertFalse( PromoteLocalBaseline::isDue( $this->loadSnapshot( $notYet ), self::NOW ) );
		$this->assertTrue( PromoteLocalBaseline::isDue( $this->loadSnapshot( $invalid ), self::NOW ) );
		$this->assertTrue( PromoteLocalBaseline::isDue( $this->loadSnapshot( $unknown ), self::NOW ) );
		$this->assertFalse( PromoteLocalBaseline::isDue( $this->loadSnapshot( $published ), self::NOW ) );
	}

	/**
	 * @dataProvider activeStatusProvider
	 */
	public function test_active_afs_defers_before_remote_and_preserves_every_state( string $status ) :void {
		$asset = new SnapshotPluginVo( 'active-'.$status.'/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		$this->db->result = [ [ 'id' => '1', 'status' => $status ] ];
		$requests = 0;
		Functions\when( 'wp_remote_request' )->alias(
			static function () use ( &$requests ) :array {
				$requests++;
				return PromoteLocalBaselineTest::httpResponse( [
					'hashes' => [ 'plugin.php' => self::PUBLISHED_HASH ],
				] );
			}
		);

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->assertSame( 0, $requests );
		$this->assertSame( 1, $this->db->calls );
		$this->assertSame( $original, $this->loadSnapshot( $asset ) );
		$this->assertSame( [], $this->coordinator->assets );
	}

	public static function activeStatusProvider() :array {
		return [
			'queued'   => [ 'queued' ],
			'building' => [ 'building' ],
			'built'    => [ 'built' ],
			'running'  => [ 'running' ],
		];
	}

	public function test_status_query_uncertainty_defers_before_remote() :void {
		$asset = new SnapshotPluginVo( 'uncertain/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		$this->db->result = false;
		$requests = 0;
		Functions\when( 'wp_remote_request' )->alias(
			static function () use ( &$requests ) :array {
				$requests++;
				return [];
			}
		);

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->assertSame( 0, $requests );
		$this->assertSame( $original, $this->loadSnapshot( $asset ) );
		$this->assertSame( [], $this->coordinator->assets );
	}

	public function test_afs_becoming_active_after_remote_preserves_snapshot_and_skips_follow_up() :void {
		$asset = new SnapshotPluginVo( 'late-active/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		$requests = 0;
		Functions\when( 'wp_remote_request' )->alias(
			function () use ( &$requests ) :array {
				$requests++;
				$this->db->result = [ [ 'id' => '1', 'status' => 'running' ] ];
				return self::httpResponse( [
					'hashes' => [ 'plugin.php' => self::PUBLISHED_HASH ],
				] );
			}
		);

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->assertSame( 1, $requests );
		$this->assertSame( 2, $this->db->calls );
		$this->assertSame( $original, $this->loadSnapshot( $asset ) );
		$this->assertSame( [], $this->coordinator->assets );
	}

	/**
	 * @dataProvider unsuccessfulResponseProvider
	 */
	public function test_completed_unsuccessful_check_changes_only_timestamp(
		string $case,
		array $hashes,
		bool $throws
	) :void {
		$asset = new SnapshotPluginVo( 'negative-'.$case.'/plugin.php', '1.2.3' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		$urls = [];
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url ) use ( &$urls, $hashes, $throws ) :array {
				$urls[] = $url;
				if ( $throws ) {
					throw new \RuntimeException( 'Synthetic published-source failure.' );
				}
				return PromoteLocalBaselineTest::httpResponse( [ 'hashes' => $hashes ] );
			}
		);

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$expected = $original;
		$expected[ 'meta' ][ 'last_live_hash_check_at' ] = self::NOW;
		$this->assertSame( $expected, $this->loadSnapshot( $asset ) );
		$this->assertSame( [], $this->coordinator->assets );
		$this->assertCount( 1, $urls );
		$this->assertStringContainsString(
			'/hashes/p/negative-'.$case.'/1.2.3/md5',
			$urls[ 0 ]
		);
	}

	public static function unsuccessfulResponseProvider() :array {
		return [
			'unavailable' => [ 'unavailable', [], false ],
			'exception'   => [ 'exception', [], true ],
			'malformed'   => [
				'malformed',
				[
					'valid.php' => self::PUBLISHED_HASH,
					'bad.php'   => 'not-a-hash',
				],
				false,
			],
		];
	}

	public function test_live_version_change_during_request_makes_attempt_stale_without_mutation() :void {
		$asset = new SnapshotPluginVo( 'version-change/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$snapshotAsset = new SnapshotPluginVo( $asset->file, $asset->Version );
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		Functions\when( 'wp_remote_request' )->alias(
			static function () use ( $asset ) :array {
				$asset->Version = '2.0.0';
				return PromoteLocalBaselineTest::httpResponse( [
					'hashes' => [ 'plugin.php' => self::PUBLISHED_HASH ],
				] );
			}
		);

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->assertSame( $original, $this->loadSnapshot( $snapshotAsset ) );
		$this->assertSame( [], $this->coordinator->assets );
	}

	/**
	 * @dataProvider successfulAssetProvider
	 */
	public function test_success_persists_complete_published_snapshot_resets_memoization_and_enqueues_once(
		string $type,
		string $key,
		string $version,
		string $path
	) :void {
		$asset = $type === 'plugin'
			? new SnapshotPluginVo( $key, $version )
			: new SnapshotThemeVo( $key, $version );
		$asset->wpOrg = true;
		$this->installEnvironment(
			$type === 'plugin' ? [ $asset ] : [],
			$type === 'theme' ? [ $asset ] : []
		);
		$this->writeLocalStore( $asset );
		$this->seedMemoization();
		$urls = [];
		Functions\when( 'wp_remote_request' )->alias(
			static function ( string $url ) use ( &$urls, $path ) :array {
				$urls[] = $url;
				return PromoteLocalBaselineTest::httpResponse( [
					'hashes' => [
						\str_replace( '/', '\\', $path ) => self::PUBLISHED_HASH,
					],
				] );
			}
		);
		$action = ( new PromoteLocalBaseline() )->setAsset( $asset );

		$this->assertTrue( $action->run() );

		$this->assertSame( [
			'meta' => [
				'ts'           => self::NOW,
				'snap_version' => '20.0.0',
				'cs_hashes_at' => 0,
				'unique_id'    => $key,
				'name'         => $asset->Name,
				'version'      => $version,
				'algo'         => 'md5',
				'live_hashes'  => true,
			],
			'data' => [
				$path => self::PUBLISHED_HASH,
			],
		], $this->loadSnapshot( $asset ) );
		$this->assertSame( [ [ $type, $key, $version ] ], $this->coordinator->assets );
		$this->assertSame( 2, $this->db->calls );
		$this->assertMemoizationEmpty();
		$this->assertCount( 1, $urls );

		$this->assertFalse( $action->run() );
		$this->assertSame( [ [ $type, $key, $version ] ], $this->coordinator->assets );
		$this->assertCount( 1, $urls );
	}

	public static function successfulAssetProvider() :array {
		return [
			'plugin' => [ 'plugin', 'successful-plugin/plugin.php', '1.2.3', 'plugin.php' ],
			'theme'  => [ 'theme', 'successful-theme', '4.5.6', 'style.css' ],
		];
	}

	public function test_replacement_failure_restores_original_then_records_completed_check() :void {
		$asset = new SnapshotPluginVo( 'replacement-failure/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		$store = $this->rawStore( $asset );
		$this->fs->resetWriteAttempts();
		$this->fs->failOnWriteAttempt( $store->getSnapStorePath(), 1 );
		$this->mockPublishedHashes( [ 'plugin.php' => self::PUBLISHED_HASH ] );

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$expected = $original;
		$expected[ 'meta' ][ 'last_live_hash_check_at' ] = self::NOW;
		$this->assertSame( $expected, $this->loadSnapshot( $asset ) );
		$this->assertSame( [], $this->coordinator->assets );
		$this->assertSame(
			[],
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaselineTestLog::$messages
		);
	}

	public function test_timestamp_persistence_failure_restores_original_and_logs_once() :void {
		$asset = new SnapshotPluginVo( 'timestamp-failure/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		$store = $this->rawStore( $asset );
		$this->fs->resetWriteAttempts();
		$this->fs->failOnWriteAttempt( $store->getSnapStoreMetaPath(), 1 );
		$this->mockPublishedHashes( [] );

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->assertSame( $original, $this->loadSnapshot( $asset ) );
		$this->assertSame( [], $this->coordinator->assets );
		$this->assertCount(
			1,
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaselineTestLog::$messages
		);
	}

	public function test_pre_write_reload_failure_logs_once_without_writing_or_queueing() :void {
		$asset = new SnapshotPluginVo( 'reload-failure/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$original = $this->writeLocalStore( $asset );
		$metaPath = $this->rawStore( $asset )->getSnapStoreMetaPath();
		$this->fs->fileWriteCounts = [];
		Functions\when( 'wp_remote_request' )->alias(
			function () use ( $metaPath ) :array {
				$this->fs->failFileRead( $metaPath );
				return self::httpResponse( [ 'hashes' => [] ] );
			}
		);

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->fs->failedFileReads = [];
		$this->assertSame( [], $this->fs->fileWriteCounts );
		$this->assertSame( $original, $this->loadSnapshot( $asset ) );
		$this->assertSame( [], $this->coordinator->assets );
		$this->assertCount(
			1,
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaselineTestLog::$messages
		);
	}

	public function test_unverifiable_restoration_logs_once_and_stops_without_follow_up() :void {
		$asset = new SnapshotPluginVo( 'restore-failure/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$this->writeLocalStore( $asset );
		$store = $this->rawStore( $asset );
		$this->fs->resetWriteAttempts();
		$this->fs->failOnWriteAttempt( $store->getSnapStoreMetaPath(), 1 );
		$this->fs->failOnWriteAttempt( $store->getSnapStorePath(), 2 );
		$this->mockPublishedHashes( [ 'plugin.php' => self::PUBLISHED_HASH ] );

		$this->assertFalse( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->assertSame( [], $this->coordinator->assets );
		$this->assertCount(
			1,
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaselineTestLog::$messages
		);
	}

	public function test_enqueue_failure_keeps_verified_published_snapshot_without_second_log_or_retry_state() :void {
		$asset = new SnapshotPluginVo( 'enqueue-failure/plugin.php', '1.0.0' );
		$asset->wpOrg = true;
		$this->installEnvironment( [ $asset ], [] );
		$this->writeLocalStore( $asset );
		$this->coordinator->result = false;
		$this->mockPublishedHashes( [ 'plugin.php' => self::PUBLISHED_HASH ] );

		$this->assertTrue( ( new PromoteLocalBaseline() )->setAsset( $asset )->run() );

		$this->assertTrue( $this->loadSnapshot( $asset )[ 'meta' ][ 'live_hashes' ] );
		$this->assertSame( [ [ 'plugin', $asset->file, $asset->Version ] ], $this->coordinator->assets );
		$this->assertSame(
			[],
			\FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\PromoteLocalBaselineTestLog::$messages
		);
	}

	/**
	 * @param SnapshotPluginVo[] $plugins
	 * @param SnapshotThemeVo[]  $themes
	 */
	private function installEnvironment( array $plugins, array $themes ) :void {
		$root = $this->normalisePath( $this->createTrackedTempDir( 'shield-promote-root-' ) );
		$tmp = $this->normalisePath( $this->createTrackedTempDir( 'shield-promote-tmp-' ) );
		$this->fs = new PromoteLocalBaselineTestFs();
		$this->db = new PromoteLocalBaselineTestDb();
		$this->coordinator = new PromoteLocalBaselineTestCoordinator();
		$this->registerCacheStoreWordPressFunctions( $this->fs, $tmp );
		$wpGeneral = new SnapshotWpGeneral();
		$wpGeneral->setTransient( 'apto-wphashes-api-available-routes', '#^hashes$#' );
		ServicesState::installItems( [
			'service_request'   => new CacheStoreTestRequest( self::NOW ),
			'service_wpdb'      => $this->db,
			'service_wpfs'      => $this->fs,
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

				public function version() :string {
					return '20.0.0';
				}
			}
		);
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $root );
		$controller->comps = (object)[
			'asset_coordinator' => $this->coordinator,
		];
		$controller->db_con = (object)[
			'scans' => new PromoteLocalBaselineTestScansTable(),
		];
	}

	/**
	 * @param SnapshotPluginVo|SnapshotThemeVo $asset
	 * @return array{meta:array,data:array<string,string>}
	 */
	private function writeLocalStore( $asset, array $meta = [] ) :array {
		$meta = \array_merge( [
			'ts'           => 1600000000,
			'snap_version' => '19.0.0',
			'cs_hashes_at' => 7,
			'unique_id'    => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
			'name'         => $asset->Name,
			'version'      => $asset->Version,
			'algo'         => 'md5',
			'live_hashes'  => false,
		], $meta );
		$this->rawStore( $asset )
			->setSnapData( [ 'plugin.php' => self::ORIGINAL_HASH ] )
			->setSnapMeta( $meta )
			->save();
		return $this->loadSnapshot( $asset );
	}

	/**
	 * @param SnapshotPluginVo|SnapshotThemeVo $asset
	 */
	private function rawStore( $asset ) :Store {
		return ( new Store( $asset, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() );
	}

	/**
	 * @param SnapshotPluginVo|SnapshotThemeVo $asset
	 * @return array{meta:array,data:array<string,string>}
	 */
	private function loadSnapshot( $asset ) :array {
		$snapshot = $this->rawStore( $asset )->getUsableSnapshot();
		$this->assertNotNull( $snapshot );
		return $snapshot;
	}

	private function mockPublishedHashes( array $hashes ) :void {
		Functions\when( 'wp_remote_request' )->alias(
			static fn() :array => PromoteLocalBaselineTest::httpResponse( [ 'hashes' => $hashes ] )
		);
	}

	private function seedMemoization() :void {
		$this->setStaticProperty( Retrieve::class, 'sources', [ 'seed' => null ] );
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

	private function normalisePath( string $path ) :string {
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

class PromoteLocalBaselineTestCoordinator {

	public array $assets = [];
	public bool $result = true;

	public function enqueuePromotionFollowUp(
		string $assetType,
		string $assetKey,
		string $requiredPublishedVersion
	) :bool {
		$this->assets[] = [ $assetType, $assetKey, $requiredPublishedVersion ];
		return $this->result;
	}
}

class PromoteLocalBaselineTestScansTable {

	public function getTable() :string {
		return 'shield_scans';
	}
}

class PromoteLocalBaselineTestDb extends Db {

	public $result = [];
	public int $calls = 0;

	public function selectCustom( $query, $format = null ) {
		unset( $query, $format );
		$this->calls++;
		return $this->result;
	}
}

class PromoteLocalBaselineTestFs extends CacheStoreTestFs {

	private array $attempts = [];
	private array $failures = [];

	public function resetWriteAttempts() :void {
		$this->attempts = [];
		$this->failures = [];
	}

	public function failOnWriteAttempt( string $path, int $attempt ) :void {
		$path = $this->normalise( $path );
		$this->failures[ $path ][ $attempt ] = true;
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		$path = $this->normalise( (string)$path );
		$this->attempts[ $path ] = ( $this->attempts[ $path ] ?? 0 ) + 1;
		if ( isset( $this->failures[ $path ][ $this->attempts[ $path ] ] ) ) {
			return false;
		}
		return parent::putFileContent( $path, $contents, $compress );
	}
}
