<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility\CleanOutOldGuardFiles;
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

class CleanOutOldGuardFilesTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->resetHashesStorageDir();
		$fs = new CacheStoreTestFs();
		$this->registerCacheStoreWordPressFunctions( $fs, $this->makeTempDir( 'tmp' ) );
		ServicesState::installItems( [
			'service_request' => new CacheStoreTestRequest(),
			'service_wpfs'    => $fs,
		] );
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

	public function test_cleanup_preserves_active_marked_dir() :void {
		$root = $this->installRoot();
		$old = $root.'/ptguard-aaaaaaaaaaaaaaaa';
		$active = $root.'/ptguard-bbbbbbbbbbbbbbbb';
		$this->mkdir( $old );
		$this->mkdir( $active );
		$this->mkdir( $root.'/ptguard' );
		\file_put_contents( $root.'/.ptguard-active.txt', 'ptguard-bbbbbbbbbbbbbbbb' );

		( new CleanOutOldGuardFiles() )->execute();

		$this->assertDirectoryExists( $active );
		$this->assertDirectoryDoesNotExist( $old );
		$this->assertDirectoryDoesNotExist( $root.'/ptguard' );
	}

	public function test_cleanup_uses_newest_existing_dir_without_writing_marker() :void {
		$root = $this->installRoot();
		$old = $root.'/ptguard-cccccccccccccccc';
		$new = $root.'/ptguard-dddddddddddddddd';
		$this->mkdir( $old );
		$this->mkdir( $new );
		\touch( $old, 1700000000 );
		\touch( $new, 1700000100 );

		( new CleanOutOldGuardFiles() )->execute();

		$this->assertDirectoryExists( $new );
		$this->assertDirectoryDoesNotExist( $old );
		$this->assertFileDoesNotExist( $root.'/.ptguard-active.txt' );
	}

	public function test_cleanup_does_not_create_hash_dir_when_root_has_no_hash_dirs() :void {
		$root = $this->installRoot();

		( new CleanOutOldGuardFiles() )->execute();

		$this->assertSame( [], \glob( $root.'/ptguard-*' ) ?: [] );
		$this->assertFileDoesNotExist( $root.'/.ptguard-active.txt' );
	}

	public function test_cleanup_does_not_create_missing_cache_root() :void {
		$root = $this->makeTempDir( 'missing-root' );
		$this->removeDir( $root );
		$controller = CacheStoreTestController::install( new CacheStoreTestOptions() );
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $root );

		( new CleanOutOldGuardFiles() )->execute();

		$this->assertDirectoryDoesNotExist( $root );
	}

	private function installRoot() :string {
		$root = $this->makeTempDir( 'root' );
		$controller = CacheStoreTestController::install( new CacheStoreTestOptions() );
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( $root );
		return $root;
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
		$dir = $this->normaliseCacheStorePath( \sys_get_temp_dir().'/shield-clean-guard-'.$suffix.'-'.\uniqid() );
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
