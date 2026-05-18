<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestCacheDir,
	CacheStoreTestController,
	CacheStoreTestFs,
	CacheStoreTestOptions,
	CacheStoreTestRequest,
	CacheStoreWordPressFunctions
};

class HashesStorageDirTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	private CacheStoreTestFs $fs;

	private CacheStoreTestCacheDir $cacheRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
		$this->fs = new CacheStoreTestFs();
		$this->registerCacheStoreWordPressFunctions( $this->fs, $this->makeTempDir( 'tmp' ) );
		ServicesState::installItems( [
			'service_request' => new CacheStoreTestRequest(),
			'service_wpfs'    => $this->fs,
		] );
		$this->cacheRoot = new CacheStoreTestCacheDir( '' );
		$controller = CacheStoreTestController::install( new CacheStoreTestOptions() );
		$controller->cache_dir_handler = $this->cacheRoot;
	}

	protected function tearDown() :void {
		$this->resetHashesStorageDir();
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_empty_cache_root_returns_empty_hash_dir() :void {
		$this->cacheRoot->root = '';

		$this->assertSame( '', ( new HashesStorageDir() )->getTempDir() );
	}

	public function test_valid_active_marker_selects_marked_dir() :void {
		$root = $this->makeTempDir( 'root' );
		$marked = $root.'/ptguard-bbbbbbbbbbbbbbbb';
		$other = $root.'/ptguard-cccccccccccccccc';
		$this->mkdir( $marked );
		$this->mkdir( $other );
		\file_put_contents( $root.'/ptguard-active.txt', 'ptguard-bbbbbbbbbbbbbbbb' );
		$this->cacheRoot->root = $root;

		$this->assertSame( $marked, ( new HashesStorageDir() )->getTempDir() );
	}

	public function test_missing_marker_selects_newest_existing_ptguard_and_writes_marker() :void {
		$root = $this->makeTempDir( 'root' );
		$old = $root.'/ptguard-dddddddddddddddd';
		$new = $root.'/ptguard-eeeeeeeeeeeeeeee';
		$this->mkdir( $old );
		$this->mkdir( $new );
		\touch( $old, 1700000000 );
		\touch( $new, 1700000100 );
		$this->cacheRoot->root = $root;

		$this->assertSame( $new, ( new HashesStorageDir() )->getTempDir() );
		$this->assertSame( 'ptguard-eeeeeeeeeeeeeeee', \trim( (string)\file_get_contents( $root.'/ptguard-active.txt' ) ) );
	}

	public function test_invalid_marker_is_replaced_inside_current_root() :void {
		$root = $this->makeTempDir( 'root' );
		$valid = $root.'/ptguard-ffffffffffffffff';
		$this->mkdir( $valid );
		\file_put_contents( $root.'/ptguard-active.txt', '../outside' );
		$this->cacheRoot->root = $root;

		$this->assertSame( $valid, ( new HashesStorageDir() )->getTempDir() );
		$this->assertSame( 'ptguard-ffffffffffffffff', \trim( (string)\file_get_contents( $root.'/ptguard-active.txt' ) ) );
	}

	public function test_static_dir_is_invalidated_when_cache_root_changes() :void {
		$rootA = $this->makeTempDir( 'root-a' );
		$rootB = $this->makeTempDir( 'root-b' );
		$this->mkdir( $rootA.'/ptguard-aaaaaaaaaaaaaaaa' );
		$this->mkdir( $rootB.'/ptguard-bbbbbbbbbbbbbbbb' );
		$this->cacheRoot->root = $rootA;
		$this->assertSame( $rootA.'/ptguard-aaaaaaaaaaaaaaaa', ( new HashesStorageDir() )->getTempDir() );

		$this->cacheRoot->root = $rootB;

		$this->assertSame( $rootB.'/ptguard-bbbbbbbbbbbbbbbb', ( new HashesStorageDir() )->getTempDir() );
	}

	public function test_stale_static_dir_without_root_is_ignored() :void {
		$stale = $this->makeTempDir( 'stale' ).'/ptguard-cccccccccccccccc';
		$root = $this->makeTempDir( 'root' );
		$current = $root.'/ptguard-dddddddddddddddd';
		$this->mkdir( $stale );
		$this->mkdir( $current );
		$this->primeStaleStaticDirWithoutRoot( $stale );
		$this->cacheRoot->root = $root;

		$this->assertSame( $current, ( new HashesStorageDir() )->getTempDir() );
		$this->assertSame( 'ptguard-dddddddddddddddd', \trim( (string)\file_get_contents( $root.'/ptguard-active.txt' ) ) );
	}

	public function test_static_dir_is_invalidated_when_directory_is_deleted() :void {
		$root = $this->makeTempDir( 'root' );
		$first = $root.'/ptguard-aaaaaaaaaaaaaaaa';
		$this->mkdir( $first );
		$this->cacheRoot->root = $root;
		$this->assertSame( $first, ( new HashesStorageDir() )->getTempDir() );
		$this->removeDir( $first );

		$this->assertSame( $root.'/ptguard-aaaaaaaaaaaaaaaa', ( new HashesStorageDir() )->getTempDir() );
		$this->assertDirectoryExists( $root.'/ptguard-aaaaaaaaaaaaaaaa' );
	}

	public function test_new_hash_dir_creation_writes_active_marker() :void {
		$root = $this->makeTempDir( 'root' );
		$this->cacheRoot->root = $root;

		$this->assertSame( $root.'/ptguard-aaaaaaaaaaaaaaaa', ( new HashesStorageDir() )->getTempDir() );
		$this->assertSame( 'ptguard-aaaaaaaaaaaaaaaa', \trim( (string)\file_get_contents( $root.'/ptguard-active.txt' ) ) );
	}

	public function test_existing_only_lookup_does_not_create_hash_dir() :void {
		$root = $this->makeTempDir( 'root' );
		$this->cacheRoot->root = $root;

		$this->assertSame( '', ( new HashesStorageDir() )->getTempDir( false ) );
		$this->assertSame( [], \glob( $root.'/ptguard-*' ) ?: [] );
		$this->assertFileDoesNotExist( $root.'/ptguard-active.txt' );
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

	private function primeStaleStaticDirWithoutRoot( string $dir ) :void {
		$reflection = new \ReflectionClass( HashesStorageDir::class );
		$property = $reflection->getProperty( 'dir' );
		$property->setAccessible( true );
		$property->setValue( null, $dir );
		if ( $reflection->hasProperty( 'rootDir' ) ) {
			$root = $reflection->getProperty( 'rootDir' );
			$root->setAccessible( true );
			$root->setValue( null, null );
		}
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normaliseCacheStorePath( \sys_get_temp_dir().'/shield-hashes-dir-'.$suffix.'-'.\uniqid() );
		$this->mkdir( $dir );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function mkdir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
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
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $dir );
	}
}
