<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Hashes;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	HashVerificationResult,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\CacheStoreTestCacheDir;
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	Plugins,
	Request,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class RetrieveVersionedCacheTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private const HASH_V1 = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	private const HASH_V11 = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
	private const HASH_V2 = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Retrieve::resetMemoization();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalizePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalizePath( $path ) );
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ) );
	}

	protected function tearDown() :void {
		Retrieve::resetMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_hash_cache_is_scoped_by_asset_version_in_one_request() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'     => new RetrieveVersionedCacheTestFs(),
			'service_request'  => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$versionOne = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '1.0.0' );
		$versionTwo = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '1.1.0' );
		$this->writeStore( $versionOne, [
			'premium-plugin/plugin.php' => self::HASH_V1,
		], $hashDir );
		$this->writeStore( $versionTwo, [
			'premium-plugin/plugin.php' => self::HASH_V11,
		], $hashDir );

		$retrieve = new Retrieve();

		$this->assertSame(
			[ 'premium-plugin/plugin.php' => [ self::HASH_V1 ] ],
			$retrieve->byVO( $versionOne )
		);
		$this->assertSame(
			[ 'premium-plugin/plugin.php' => [ self::HASH_V11 ] ],
			$retrieve->byVO( $versionTwo )
		);
	}

	public function test_by_slug_uses_reloaded_asset_version_for_snapshot_lookup() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'      => new RetrieveVersionedCacheTestFs(),
			'service_request'   => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
			'service_wpplugins' => new RetrieveVersionedCacheTestPlugins( 'premium-plugin/plugin.php', '1.0.0', '2.0.0' ),
			'service_wpthemes'  => new RetrieveVersionedCacheTestThemes(),
		] );
		$this->installController( $cacheRoot );

		$versionOne = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '1.0.0' );
		$versionTwo = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStore( $versionOne, [
			'plugin.php' => self::HASH_V1,
		], $hashDir );
		$this->writeStore( $versionTwo, [
			'plugin.php' => self::HASH_V2,
		], $hashDir );

		$this->assertSame(
			[ 'plugin.php' => [ self::HASH_V2 ] ],
			( new Retrieve() )->bySlug( 'premium-plugin/plugin.php' )
		);
	}

	public function test_stored_only_published_snapshot_returns_basis_and_trust() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'     => new RetrieveVersionedCacheTestFs(),
			'service_request'  => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStoreWithMeta( $asset, [
			'plugin.php'       => self::HASH_V1,
			'src/Feature.php'  => self::HASH_V11,
		], [
			'version'     => '2.0.0',
			'unique_id'   => 'premium-plugin/plugin.php',
			'live_hashes' => true,
		], $hashDir );

		$this->assertSame( [
			'hashes'           => [
				'plugin.php'       => [ self::HASH_V1 ],
				'src/Feature.php'  => [ self::HASH_V11 ],
			],
			'trusted_source'   => true,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE,
		], ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );
	}

	/**
	 * @dataProvider provideUntrustedStoredSourceMeta
	 */
	public function test_stored_only_non_published_source_is_local_baseline( array $sourceMeta ) :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'    => new RetrieveVersionedCacheTestFs(),
			'service_request' => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStoreWithMeta( $asset, [
			'plugin.php' => self::HASH_V1,
		], \array_merge( [
			'version'   => '2.0.0',
			'unique_id' => 'premium-plugin/plugin.php',
		], $sourceMeta ), $hashDir );

		$this->assertSame( [
			'hashes'           => [
				'plugin.php' => [ self::HASH_V1 ],
			],
			'trusted_source'   => false,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE,
		], ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );
	}

	public function provideUntrustedStoredSourceMeta() :array {
		return [
			'false'   => [ [ 'live_hashes' => false ] ],
			'absent'  => [ [] ],
			'unknown' => [ [ 'live_hashes' => 'published' ] ],
		];
	}

	public function test_stored_only_rejects_partially_invalid_snapshot() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'    => new RetrieveVersionedCacheTestFs(),
			'service_request' => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStoreWithMeta( $asset, [
			'plugin.php' => self::HASH_V1,
			'bad.php'    => 'unsupported-hash',
		], [
			'version'     => '2.0.0',
			'unique_id'   => 'premium-plugin/plugin.php',
			'live_hashes' => true,
		], $hashDir );

		$this->assertNull( ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );
	}

	public function test_local_snapshot_with_mismatched_version_meta_is_rejected() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'     => new RetrieveVersionedCacheTestFs(),
			'service_request'  => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStoreWithMeta( $asset, [
			'plugin.php' => 'hash-for-1.0.0',
		], [
			'version'   => '1.0.0',
			'unique_id' => 'premium-plugin/plugin.php',
		], $hashDir );

		$this->assertNull( ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );
	}

	public function test_local_snapshot_with_mismatched_unique_id_meta_is_rejected() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'     => new RetrieveVersionedCacheTestFs(),
			'service_request'  => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStoreWithMeta( $asset, [
			'plugin.php' => 'hash-for-2.0.0',
		], [
			'version'   => '2.0.0',
			'unique_id' => 'different-plugin/plugin.php',
		], $hashDir );

		$this->assertNull( ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );
	}

	public function test_local_snapshot_with_incomplete_meta_is_rejected() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'     => new RetrieveVersionedCacheTestFs(),
			'service_request'  => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStoreWithMeta( $asset, [
			'plugin.php' => 'hash-for-2.0.0',
		], [
			'version' => '2.0.0',
		], $hashDir );

		$this->assertNull( ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );
	}

	public function test_unchanged_stored_source_reuses_normalized_content_without_more_reads() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$fs = new RetrieveVersionedCacheTestFs();
		ServicesState::installItems( [
			'service_wpfs' => $fs,
		] );
		$this->installController( $cacheRoot );
		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStore( $asset, [ 'plugin.php' => self::HASH_V2 ], $hashDir );
		$store = ( new Store( $asset, true ) )->setWorkingDir( $hashDir );
		$retrieve = new Retrieve();

		$this->assertNotNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		for ( $i = 0; $i < 20; $i++ ) {
			$this->assertNotNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		}

		$this->assertSame( 1, $fs->compressedReads( $store->getSnapStorePath() ) );
		$this->assertSame( 1, $fs->compressedReads( $store->getSnapStoreMetaPath() ) );
	}

	public function test_deleted_positive_source_becomes_a_sticky_miss() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [ 'service_wpfs' => new RetrieveVersionedCacheTestFs() ] );
		$this->installController( $cacheRoot );
		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStore( $asset, [ 'plugin.php' => self::HASH_V2 ], $hashDir );
		$store = ( new Store( $asset, true ) )->setWorkingDir( $hashDir );
		$retrieve = new Retrieve();

		$this->assertNotNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		\unlink( $store->getSnapStorePath() );
		$this->assertNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		$this->writeStore( $asset, [ 'plugin.php' => self::HASH_V2 ], $hashDir );
		$this->assertNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
	}

	public function test_inaccessible_positive_source_becomes_a_sticky_miss() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$fs = new RetrieveVersionedCacheTestFs();
		ServicesState::installItems( [ 'service_wpfs' => $fs ] );
		$this->installController( $cacheRoot );
		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStore( $asset, [ 'plugin.php' => self::HASH_V2 ], $hashDir );
		$store = ( new Store( $asset, true ) )->setWorkingDir( $hashDir );
		$retrieve = new Retrieve();

		$this->assertNotNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		$fs->denyAccess( $store->getSnapStoreMetaPath() );
		$this->assertNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		$fs->allowAccess( $store->getSnapStoreMetaPath() );
		$this->assertNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
	}

	public function test_stable_storage_state_change_refills_once() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$fs = new RetrieveVersionedCacheTestFs();
		ServicesState::installItems( [ 'service_wpfs' => $fs ] );
		$this->installController( $cacheRoot );
		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStore( $asset, [ 'plugin.php' => self::HASH_V2 ], $hashDir );
		$store = ( new Store( $asset, true ) )->setWorkingDir( $hashDir );
		$retrieve = new Retrieve();

		$this->assertFalse( $retrieve->byVOFromStoredSnapshot( $asset )[ 'trusted_source' ] );
		$this->writeStoreWithMeta( $asset, [ 'plugin.php' => self::HASH_V2 ], [
			'version'     => '2.0.0',
			'unique_id'   => 'premium-plugin/plugin.php',
			'live_hashes' => true,
		], $hashDir );
		\touch( $store->getSnapStoreMetaPath(), \filemtime( $store->getSnapStoreMetaPath() ) + 2 );

		$this->assertTrue( $retrieve->byVOFromStoredSnapshot( $asset )[ 'trusted_source' ] );
		$this->assertSame( 2, $fs->compressedReads( $store->getSnapStorePath() ) );
		$this->assertSame( 2, $fs->compressedReads( $store->getSnapStoreMetaPath() ) );
	}

	public function test_storage_change_during_refill_becomes_a_sticky_miss() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		$fs = new RetrieveVersionedCacheTestFs();
		ServicesState::installItems( [ 'service_wpfs' => $fs ] );
		$this->installController( $cacheRoot );
		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->writeStore( $asset, [ 'plugin.php' => self::HASH_V2 ], $hashDir );
		$store = ( new Store( $asset, true ) )->setWorkingDir( $hashDir );
		$retrieve = new Retrieve();

		$this->assertNotNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		\touch( $store->getSnapStoreMetaPath(), \filemtime( $store->getSnapStoreMetaPath() ) + 2 );
		$fs->touchDuringNextRead( $store->getSnapStoreMetaPath(), $store->getSnapStorePath() );
		$this->assertNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
		$this->assertNull( $retrieve->byVOFromStoredSnapshot( $asset ) );
	}

	public function test_hash_lookup_miss_is_cached_until_memoization_reset() :void {
		$cacheRoot = $this->makeTempDir( 'root' );
		$hashDir = $cacheRoot.'/ptguard-aaaaaaaaaaaaaaaa';
		@mkdir( $hashDir, 0777, true );
		ServicesState::installItems( [
			'service_wpfs'     => new RetrieveVersionedCacheTestFs(),
			'service_request'  => new class extends Request {
				public function ts( bool $update = true ) :int {
					unset( $update );
					return 1700000000;
				}
			},
		] );
		$this->installController( $cacheRoot );

		$asset = new RetrieveVersionedCacheTestPluginVo( 'premium-plugin/plugin.php', '2.0.0' );
		$this->assertNull( ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );

		$this->writeStore( $asset, [
			'plugin.php' => self::HASH_V2,
		], $hashDir );

		$this->assertNull( ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );

		Retrieve::resetMemoization();

		$this->assertSame( [
			'hashes'           => [ 'plugin.php' => [ self::HASH_V2 ] ],
			'trusted_source'   => false,
			'comparison_basis' => HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE,
		], ( new Retrieve() )->byVOFromStoredSnapshot( $asset ) );
	}

	private function writeStore( RetrieveVersionedCacheTestPluginVo $asset, array $hashes, string $hashDir ) :void {
		$this->writeStoreWithMeta( $asset, $hashes, [
			'version'   => $asset->Version,
			'unique_id' => $asset->file,
		], $hashDir );
	}

	private function writeStoreWithMeta(
		RetrieveVersionedCacheTestPluginVo $asset,
		array $hashes,
		array $meta,
		string $hashDir
	) :void {
		( new Store( $asset, true ) )
			->setWorkingDir( $hashDir )
			->setSnapData( $hashes )
			->setSnapMeta( $meta )
			->save();
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
		return $this->normalizePath( $this->createTrackedTempDir( 'shield-hash-test-'.$suffix.'-' ) );
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}

class RetrieveVersionedCacheTestFs extends Fs {

	private array $compressedReads = [];
	private array $deniedPaths = [];
	private ?string $mutationTriggerPath = null;
	private ?string $mutationTargetPath = null;

	public function exists( $path ) :?bool {
		return \file_exists( $path );
	}

	public function mkdir( $path ) {
		return \is_dir( $path ) || @mkdir( $path, 0777, true );
	}

	public function isDir( string $path ) :bool {
		return \is_dir( $path );
	}

	public function isAccessibleFile( string $path ) :bool {
		return $path !== ''
			   && !isset( $this->deniedPaths[ $this->normalizePath( $path ) ] )
			   && \is_file( $path );
	}

	public function denyAccess( string $path ) :void {
		$this->deniedPaths[ $this->normalizePath( $path ) ] = true;
	}

	public function allowAccess( string $path ) :void {
		unset( $this->deniedPaths[ $this->normalizePath( $path ) ] );
	}

	public function compressedReads( string $path ) :int {
		return $this->compressedReads[ $this->normalizePath( $path ) ] ?? 0;
	}

	public function touchDuringNextRead( string $triggerPath, string $targetPath ) :void {
		$this->mutationTriggerPath = $this->normalizePath( $triggerPath );
		$this->mutationTargetPath = $this->normalizePath( $targetPath );
	}

	public function getAllFilesInDir( $dir, $includeDirs = true ) {
		$items = [];
		if ( \is_dir( (string)$dir ) ) {
			foreach ( new \DirectoryIterator( (string)$dir ) as $item ) {
				if ( !$item->isDot() && ( $item->isFile() || $includeDirs ) ) {
					$items[] = \str_replace( '\\', '/', $item->getPathname() );
				}
			}
		}
		return $items;
	}

	public function getFileContent( $path, $uncompress = false ) {
		$normalizedPath = $this->normalizePath( (string)$path );
		if ( $uncompress ) {
			$this->compressedReads[ $normalizedPath ] = ( $this->compressedReads[ $normalizedPath ] ?? 0 ) + 1;
		}
		$contents = \file_get_contents( $path );
		if ( $normalizedPath === $this->mutationTriggerPath && \is_string( $this->mutationTargetPath ) ) {
			\touch( $this->mutationTargetPath, \filemtime( $this->mutationTargetPath ) + 2 );
			$this->mutationTriggerPath = null;
			$this->mutationTargetPath = null;
		}
		if ( \is_string( $contents ) && $uncompress ) {
			$inflated = \gzinflate( $contents );
			return \is_string( $inflated ) ? $inflated : null;
		}
		return $contents;
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			@mkdir( $dir, 0777, true );
		}
		return \file_put_contents( $path, $compress ? \gzdeflate( $contents ) : $contents ) !== false;
	}

	public function getModifiedTime( string $path ) :int {
		return (int)\filemtime( $path );
	}

	public function touch( $path, $time = null ) {
		return \touch( $path, $time ?? \time() );
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}

class RetrieveVersionedCacheTestPlugins extends Plugins {

	private string $file;

	private string $version;

	private string $reloadVersion;

	public function __construct( string $file, string $version, string $reloadVersion ) {
		$this->file = $file;
		$this->version = $version;
		$this->reloadVersion = $reloadVersion;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $file === $this->file
			? new RetrieveVersionedCacheTestPluginVo( $file, $reload ? $this->reloadVersion : $this->version )
			: null;
	}
}

class RetrieveVersionedCacheTestThemes extends Themes {

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $stylesheet, $reload );
		return null;
	}
}

class RetrieveVersionedCacheTestPluginVo extends WpPluginVo {

	public string $file;
	public string $Version;

	public function __construct( string $file, string $version ) {
		$this->file = $file;
		$this->Version = $version;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
				return 'plugin';
			case 'slug':
				return \dirname( $this->file );
			case 'unique_id':
				return $this->file;
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
