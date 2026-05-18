<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\CacheStore\{
	CacheStoreTestController,
	CacheStoreTestFs,
	CacheStoreTestOptions,
	CacheStoreTestRequest,
	CacheStoreWordPressFunctions
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDirHandler;
use FernleafSystems\Wordpress\Services\Services;

class CacheDirHandlerTest extends BaseUnitTest {

	use CacheStoreWordPressFunctions;

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	private CacheStoreTestFs $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->fs = new CacheStoreTestFs();
		$tmpDir = $this->makeTempDir( 'tmp' );
		$this->registerCacheStoreWordPressFunctions( $this->fs, $tmpDir );
		ServicesState::installItems( [
			'service_request' => new CacheStoreTestRequest(),
			'service_wpfs'    => $this->fs,
		] );
		CacheStoreTestController::install( new CacheStoreTestOptions() );
		$this->prepareWpContentDirs();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_preferred_final_cache_dir_is_not_nested() :void {
		$preferred = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$this->mkdir( $preferred );

		$this->assertSame( $preferred, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertFalse( \is_dir( $preferred.'/shield' ) );
	}

	public function test_strict_preferred_root_does_not_fall_back_to_cache() :void {
		$preferredBase = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads' );
		$cacheRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/cache/shield' );
		$this->mkdir( $preferredBase );
		$this->mkdir( \dirname( $cacheRoot ) );
		$this->fs->failDir( $preferredBase );

		$this->assertSame( '', ( new CacheDirHandler( '', $preferredBase ) )->dir() );
		$this->assertFalse( \is_dir( $cacheRoot ), 'Strict preferred roots must not fall through to cache.' );
	}

	public function test_default_last_known_root_wins_over_writable_discovery() :void {
		$lastKnownBase = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads' );
		$this->mkdir( $lastKnownBase );

		$this->assertSame(
			$lastKnownBase.'/shield',
			( new CacheDirHandler( $lastKnownBase, '' ) )->dir()
		);
	}

	public function test_failed_candidate_directory_is_not_deleted() :void {
		$preferredRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$this->mkdir( $preferredRoot );
		$sentinel = $preferredRoot.'/sentinel.txt';
		\file_put_contents( $sentinel, 'keep' );
		$this->fs->failDir( $preferredRoot );

		$this->assertSame( '', ( new CacheDirHandler( '', $preferredRoot ) )->dir() );
		$this->assertFileExists( $sentinel );
		$this->assertNotContains( $preferredRoot, $this->fs->deletedDirs );
	}

	public function test_fresh_discovery_prefers_active_marker_before_writable_order() :void {
		$uploadsRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$activeDir = $uploadsRoot.'/ptguard-bbbbbbbbbbbbbbbb';
		$this->mkdir( $activeDir );
		\file_put_contents( $uploadsRoot.'/ptguard-active.txt', 'ptguard-bbbbbbbbbbbbbbbb' );

		$this->assertSame( $uploadsRoot, ( new CacheDirHandler() )->dir() );
	}

	public function test_fresh_discovery_prefers_newest_existing_ptguard_without_marker() :void {
		$cacheRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/cache/shield' );
		$uploadsRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$this->mkdir( $cacheRoot.'/ptguard-cccccccccccccccc' );
		$this->mkdir( $uploadsRoot.'/ptguard-dddddddddddddddd' );
		\touch( $cacheRoot.'/ptguard-cccccccccccccccc', 1700000000 );
		\touch( $uploadsRoot.'/ptguard-dddddddddddddddd', 1700000100 );

		$this->assertSame( $uploadsRoot, ( new CacheDirHandler() )->dir() );
	}

	public function test_fresh_install_without_existing_store_keeps_existing_candidate_order() :void {
		$this->assertSame(
			$this->normaliseCacheStorePath( WP_CONTENT_DIR.'/shield' ),
			( new CacheDirHandler() )->dir()
		);
	}

	public function test_tmp_fallback_only_applies_without_strict_or_existing_store() :void {
		$this->fs->failDir( $this->normaliseCacheStorePath( WP_CONTENT_DIR ) );
		$this->fs->failDir( $this->normaliseCacheStorePath( \rtrim( ABSPATH, '/\\' ).'/wp-content' ) );
		$this->fs->failDir( $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads' ) );
		$this->fs->failDir( $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/cache' ) );
		$this->fs->failDir( $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/tmp' ) );

		$this->assertSame(
			$this->normaliseCacheStorePath( $this->cacheStoreTmpDir.'/shield' ),
			( new CacheDirHandler() )->dir()
		);
	}

	private function prepareWpContentDirs() :void {
		foreach ( [
			WP_CONTENT_DIR,
			WP_CONTENT_DIR.'/uploads',
			WP_CONTENT_DIR.'/cache',
			WP_CONTENT_DIR.'/tmp',
		] as $dir ) {
			$this->mkdir( $this->normaliseCacheStorePath( $dir ) );
		}
		foreach ( [
			WP_CONTENT_DIR.'/shield',
			WP_CONTENT_DIR.'/uploads/shield',
			WP_CONTENT_DIR.'/cache/shield',
			WP_CONTENT_DIR.'/tmp/shield',
		] as $dir ) {
			$this->removeDir( $this->normaliseCacheStorePath( $dir ) );
		}
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normaliseCacheStorePath( \sys_get_temp_dir().'/shield-cache-dir-handler-'.$suffix.'-'.\uniqid() );
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
