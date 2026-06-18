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
	Exceptions\AssetHashesNotFound,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
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

	private array $servicesSnapshot = [];
	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashMemoization();
		$this->resetHashesStorageDir();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalizePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalizePath( $path ) );
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ) );
	}

	protected function tearDown() :void {
		$this->resetHashMemoization();
		$this->resetHashesStorageDir();
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
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
			'premium-plugin/plugin.php' => 'hash-for-1.0.0',
		], $hashDir );
		$this->writeStore( $versionTwo, [
			'premium-plugin/plugin.php' => 'hash-for-1.1.0',
		], $hashDir );

		$retrieve = new Retrieve();

		$this->assertSame(
			[ 'premium-plugin/plugin.php' => 'hash-for-1.0.0' ],
			$retrieve->byVO( $versionOne )
		);
		$this->assertSame(
			[ 'premium-plugin/plugin.php' => 'hash-for-1.1.0' ],
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
			'plugin.php' => 'hash-for-1.0.0',
		], $hashDir );
		$this->writeStore( $versionTwo, [
			'plugin.php' => 'hash-for-2.0.0',
		], $hashDir );

		$this->assertSame(
			[ 'plugin.php' => 'hash-for-2.0.0' ],
			( new Retrieve() )->bySlug( 'premium-plugin/plugin.php' )
		);
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

		$this->expectException( AssetHashesNotFound::class );

		( new Retrieve() )->byVO( $asset );
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

		$this->expectException( AssetHashesNotFound::class );

		( new Retrieve() )->byVO( $asset );
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

		$this->expectException( AssetHashesNotFound::class );

		( new Retrieve() )->byVO( $asset );
	}

	public function test_hash_lookup_retries_after_local_snapshot_becomes_available_in_same_request() :void {
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
		$firstLookupMissed = false;
		try {
			( new Retrieve() )->byVO( $asset );
		}
		catch ( AssetHashesNotFound $e ) {
			$firstLookupMissed = true;
		}
		$this->assertTrue( $firstLookupMissed );

		$this->writeStore( $asset, [
			'plugin.php' => 'hash-for-2.0.0',
		], $hashDir );

		try {
			$hashes = ( new Retrieve() )->byVO( $asset );
		}
		catch ( AssetHashesNotFound $e ) {
			$this->fail( 'Hash retrieval should retry after a local snapshot becomes available in the same request.' );
		}
		$this->assertSame(
			[ 'plugin.php' => 'hash-for-2.0.0' ],
			$hashes
		);
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

	private function resetHashMemoization() :void {
		$reflection = new \ReflectionClass( Retrieve::class );
		foreach ( [ 'hashes', 'trustedSources' ] as $propertyName ) {
			$property = $reflection->getProperty( $propertyName );
			$property->setAccessible( true );
			$property->setValue( null, [] );
		}
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
		$dir = $this->normalizePath( \sys_get_temp_dir().'/shield-hash-test-'.$suffix.'-'.\uniqid() );
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

class RetrieveVersionedCacheTestFs extends Fs {

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
		return $path !== '' && \is_file( $path );
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
		$contents = \file_get_contents( $path );
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
