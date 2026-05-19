<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\HashesStorageDir;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\{
	BaseAction,
	BaseExec,
	DeleteAll,
	Load
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\AssetSnapshots\SnapshotPluginVo;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestCacheDir,
	CacheStoreTestController,
	CacheStoreTestFs,
	CacheStoreTestOptions,
	CacheStoreTestRequest,
	CacheStoreWordPressFunctions
};

class SnapshotStoreUnavailableTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	private CacheStoreTestFs $fs;

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
		$controller = CacheStoreTestController::install( new CacheStoreTestOptions() );
		$controller->cache_dir_handler = new CacheStoreTestCacheDir( '' );
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

	public function test_base_exec_cannot_run_without_hash_dir() :void {
		$this->assertFalse( ( new SnapshotStoreUnavailableExec() )->canRunForTest() );
	}

	public function test_base_action_throws_unavailable_store_before_file_path_load() :void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Snapshot store directory is unavailable' );

		( new SnapshotStoreUnavailableAction() )
			->setAsset( new SnapshotPluginVo( 'missing/missing.php', '1.0.0' ) )
			->getNewStoreForTest();
	}

	public function test_delete_all_does_not_delete_without_hash_dir() :void {
		( new SnapshotStoreUnavailableDeleteAll() )->runForTest();

		$this->assertSame( [], $this->fs->deletedDirs );
	}

	public function test_load_does_not_create_hash_dir_when_store_missing() :void {
		$root = $this->makeTempDir( 'root' );
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin
			->getController()
			->cache_dir_handler = new CacheStoreTestCacheDir( $root );

		$exceptionMessage = '';
		try {
			( new Load() )
				->setAsset( new SnapshotPluginVo( 'missing/missing.php', '1.0.0' ) )
				->run();
		}
		catch ( \Exception $e ) {
			$exceptionMessage = $e->getMessage();
		}

		$this->assertStringContainsString( 'Snapshot store directory is unavailable', $exceptionMessage );
		$this->assertSame( [], \glob( $root.'/ptguard-*' ) ?: [] );
		$this->assertFileDoesNotExist( $root.'/.ptguard-active.txt' );
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
		$dir = $this->normaliseCacheStorePath( \sys_get_temp_dir().'/shield-store-unavailable-'.$suffix.'-'.\uniqid() );
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
		$this->tempDirs[] = $dir;
		return $dir;
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

class SnapshotStoreUnavailableExec extends BaseExec {
	public function canRunForTest() :bool {
		return $this->canRun();
	}
}

class SnapshotStoreUnavailableAction extends BaseAction {
	public function getNewStoreForTest() {
		return $this->getNewStore();
	}
}

class SnapshotStoreUnavailableDeleteAll extends DeleteAll {
	public function runForTest() :void {
		$this->run();
	}
}
