<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
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
	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

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
		$this->cleanupTrackedTempDirs();
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

	public function test_external_preferred_cache_root_is_namespaced_by_install() :void {
		$preferred = $this->makeTempDir( 'preferred-external' ).'/shield';
		$expected = $this->expectedExternalCacheRoot( $preferred );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $preferred ) );
	}

	public function test_external_preferred_base_dir_is_namespaced_by_install() :void {
		$preferredBase = $this->makeTempDir( 'preferred-external-base' );
		$expected = $this->expectedExternalCacheRoot( $preferredBase.'/shield' );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferredBase ) )->dir() );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $preferredBase.'/shield' ) );
	}

	public function test_external_last_known_cache_root_is_namespaced_by_install() :void {
		$lastKnownBase = $this->makeTempDir( 'last-known-external' );
		$expected = $this->expectedExternalCacheRoot( $lastKnownBase.'/shield' );

		$this->assertSame( $expected, ( new CacheDirHandler( $lastKnownBase, '' ) )->dir() );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $lastKnownBase.'/shield' ) );
	}

	public function test_external_namespaced_preferred_cache_root_is_not_namespaced_again() :void {
		$preferred = $this->expectedExternalCacheRoot( $this->makeTempDir( 'already-namespaced' ).'/shield' );

		$this->assertSame( $preferred, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertTrue( \is_dir( $preferred ) );
	}

	public function test_external_cache_root_suffix_uses_wp_site_url_host_port_and_path() :void {
		$this->setCacheStoreSiteUrl( 'https://WWW.Example.COM:8443/abc/Def?x=1' );
		$preferred = $this->makeTempDir( 'site-url-shape' ).'/shield';
		$expected = $this->expectedExternalCacheRoot( $preferred, 'example-com-8443-abc-def' );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $preferred ) );
	}

	public function test_external_cache_root_suffix_falls_back_to_safe_hash_for_non_latin_site_url() :void {
		$siteURL = "https://\u{4F8B}\u{3048}.\u{30C6}\u{30B9}\u{30C8}/\u{7BA1}\u{7406} \u{30D1}\u{30CD}\u{30EB}?x=1";
		$this->setCacheStoreSiteUrl( $siteURL );
		$preferred = $this->makeTempDir( 'unicode-site-url' ).'/shield';
		$expectedSuffix = 'site-'.\substr( \hash( 'sha256', $siteURL ), 0, 12 );
		$expected = $this->expectedExternalCacheRoot( $preferred, $expectedSuffix );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertMatchesRegularExpression( '#/shield-[a-z0-9][a-z0-9-]{0,47}$#', $expected );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $preferred ) );
	}

	public function test_external_cache_root_suffix_falls_back_when_url_identity_is_partly_non_ascii() :void {
		$siteURL = "https://\u{4F8B}\u{3048}.\u{30C6}\u{30B9}\u{30C8}/wp";
		$this->setCacheStoreSiteUrl( $siteURL );
		$preferred = $this->makeTempDir( 'mixed-unicode-site-url' ).'/shield';
		$expectedSuffix = 'site-'.\substr( \hash( 'sha256', $siteURL ), 0, 12 );
		$expected = $this->expectedExternalCacheRoot( $preferred, $expectedSuffix );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $preferred ) );
	}

	public function test_external_cache_root_suffix_is_capped_and_trimmed_for_long_site_url() :void {
		$path = \str_repeat( 'long-segment-', 8 );
		$this->setCacheStoreSiteUrl( 'https://www.example.com/'.$path );
		$preferred = $this->makeTempDir( 'long-site-url' ).'/shield';
		$expectedSuffix = \trim( \substr( 'example-com-'.$path, 0, 48 ), '-' );
		$expected = $this->expectedExternalCacheRoot( $preferred, $expectedSuffix );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertLessThanOrEqual( 48, \strlen( \substr( \basename( $expected ), \strlen( 'shield-' ) ) ) );
		$this->assertMatchesRegularExpression( '#/shield-[a-z0-9][a-z0-9-]{0,47}$#', $expected );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $preferred ) );
	}

	public function test_external_preferred_cache_root_escaped_from_abspath_is_namespaced() :void {
		$base = $this->normaliseCacheStorePath(
			$this->createTrackedTempDir(
				'shield-cache-dir-handler-escaped-',
				\dirname( \rtrim( ABSPATH, '/\\' ) )
			)
		);
		$preferred = $this->normaliseCacheStorePath( \rtrim( ABSPATH, '/\\' ).'/../'.\basename( $base ).'/shield' );
		$expected = $this->expectedExternalCacheRoot( $base.'/shield' );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $base.'/shield' ) );
	}

	public function test_locate_existing_dir_with_missing_preferred_root_does_not_create_or_fall_back() :void {
		$preferredBase = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/missing-preferred' );

		$this->assertSame( '', ( new CacheDirHandler( '', $preferredBase ) )->locateExistingDir() );
		$this->assertFalse( \is_dir( $preferredBase ) );
		$this->assertFalse( \is_dir( $preferredBase.'/shield' ) );
		$this->assertFalse( \is_dir( $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/shield' ) ) );
	}

	public function test_locate_existing_dir_with_existing_configured_root_does_not_write_setup_files() :void {
		$preferred = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$this->mkdir( $preferred );

		$this->assertSame( $preferred, ( new CacheDirHandler( '', $preferred ) )->locateExistingDir() );
		$this->assertFileDoesNotExist( $preferred.'/assessed.flag' );
		$this->assertFileDoesNotExist( $preferred.'/.htaccess' );
		$this->assertFileDoesNotExist( $preferred.'/index.php' );
		$this->assertFileDoesNotExist( $preferred.'/README.txt' );
	}

	public function test_locate_existing_dir_prefers_active_marker_without_writing_setup_files() :void {
		$cacheRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/cache/shield' );
		$uploadsRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$this->mkdir( $cacheRoot.'/ptguard-cccccccccccccccc' );
		$this->mkdir( $uploadsRoot.'/ptguard-bbbbbbbbbbbbbbbb' );
		\file_put_contents( $uploadsRoot.'/.ptguard-active.txt', 'ptguard-bbbbbbbbbbbbbbbb' );
		\touch( $cacheRoot.'/ptguard-cccccccccccccccc', 1700000100 );
		\touch( $uploadsRoot.'/ptguard-bbbbbbbbbbbbbbbb', 1700000000 );

		$this->assertSame( $uploadsRoot, ( new CacheDirHandler() )->locateExistingDir() );
		$this->assertFileDoesNotExist( $uploadsRoot.'/assessed.flag' );
		$this->assertFileDoesNotExist( $uploadsRoot.'/README.txt' );
	}

	public function test_locate_existing_dir_prefers_newest_hash_dir_without_writing_marker() :void {
		$cacheRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/cache/shield' );
		$uploadsRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' );
		$this->mkdir( $cacheRoot.'/ptguard-cccccccccccccccc' );
		$this->mkdir( $uploadsRoot.'/ptguard-dddddddddddddddd' );
		\touch( $cacheRoot.'/ptguard-cccccccccccccccc', 1700000000 );
		\touch( $uploadsRoot.'/ptguard-dddddddddddddddd', 1700000100 );

		$this->assertSame( $uploadsRoot, ( new CacheDirHandler() )->locateExistingDir() );
		$this->assertFileDoesNotExist( $uploadsRoot.'/.ptguard-active.txt' );
		$this->assertFileDoesNotExist( $uploadsRoot.'/README.txt' );
	}

	public function test_locate_existing_dir_returns_first_existing_discovery_root_without_writing_setup_files() :void {
		$root = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/shield' );
		$this->mkdir( $root );

		$this->assertSame( $root, ( new CacheDirHandler() )->locateExistingDir() );
		$this->assertFileDoesNotExist( $root.'/assessed.flag' );
		$this->assertFileDoesNotExist( $root.'/.htaccess' );
		$this->assertFileDoesNotExist( $root.'/index.php' );
		$this->assertFileDoesNotExist( $root.'/README.txt' );
	}

	public function test_locate_existing_dir_without_discovery_root_does_not_create_roots() :void {
		( new CacheDirHandler() )->locateExistingDir();

		$this->assertFalse( \is_dir( $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/shield' ) ) );
		$this->assertFalse( \is_dir( $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads/shield' ) ) );
		$this->assertFalse( \is_dir( $this->normaliseCacheStorePath( $this->cacheStoreTmpDir.'/shield' ) ) );
		$this->assertFalse( \is_dir( $this->expectedExternalCacheRoot( $this->cacheStoreTmpDir.'/shield' ) ) );
	}

	public function test_locate_existing_dir_ignores_shared_external_cache_root_without_namespace() :void {
		$sharedRoot = $this->normaliseCacheStorePath( $this->cacheStoreTmpDir.'/shield' );
		$this->mkdir( $sharedRoot );

		$this->assertSame( '', ( new CacheDirHandler() )->locateExistingDir() );
	}

	public function test_locate_existing_dir_finds_existing_external_namespaced_cache_root() :void {
		$root = $this->expectedExternalCacheRoot( $this->cacheStoreTmpDir.'/shield' );
		$this->mkdir( $root );

		$this->assertSame( $root, ( new CacheDirHandler() )->locateExistingDir() );
	}

	public function test_locate_existing_dir_ignores_external_cache_root_for_another_site_suffix() :void {
		$otherSiteRoot = $this->normaliseCacheStorePath( $this->cacheStoreTmpDir.'/shield-other-site' );
		$this->mkdir( $otherSiteRoot );

		$this->assertSame( '', ( new CacheDirHandler() )->locateExistingDir() );
	}

	public function test_write_mode_does_not_rewrite_current_readme() :void {
		$preferred = $this->makeNonTmpCacheRoot( 'readme' );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );

		$readme = $expected.'/README.txt';
		$this->assertFileExists( $readme );
		\touch( $readme, 1600000000 );
		\clearstatcache( true, $readme );
		$mtime = \filemtime( $readme );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		\clearstatcache( true, $readme );
		$this->assertSame( $mtime, \filemtime( $readme ) );
	}

	public function test_write_mode_skips_protection_files_for_tmp_cache_root() :void {
		if ( \DIRECTORY_SEPARATOR === '\\' ) {
			$this->markTestSkipped( 'The literal /tmp cache-root guard is Unix-specific.' );
		}

		$base = $this->normaliseCacheStorePath( $this->createTrackedTempDir( 'shield-cache-dir-handler-tmp-skip-', '/tmp' ) );
		$preferred = $base.'/shield';
		$expected = $this->expectedExternalCacheRoot( $preferred );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertFileExists( $expected.'/assessed.flag' );
		$this->assertFileDoesNotExist( $expected.'/.htaccess' );
		$this->assertFileDoesNotExist( $expected.'/index.php' );
		$this->assertFileDoesNotExist( $expected.'/README.txt' );
		$this->assertFalse( \is_dir( $preferred ) );
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
		\file_put_contents( $uploadsRoot.'/.ptguard-active.txt', 'ptguard-bbbbbbbbbbbbbbbb' );

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
			$this->expectedExternalCacheRoot( $this->cacheStoreTmpDir.'/shield' ),
			( new CacheDirHandler() )->dir()
		);
		$this->assertFalse( \is_dir( $this->normaliseCacheStorePath( $this->cacheStoreTmpDir.'/shield' ) ) );
	}

	public function test_build_sub_dir_uses_namespaced_external_cache_root() :void {
		$preferred = $this->makeTempDir( 'scan-subdir' ).'/shield';
		$expectedRoot = $this->expectedExternalCacheRoot( $preferred );
		$expectedSubDir = $expectedRoot.'/afs-file-optimiser';

		$this->assertSame(
			$expectedSubDir,
			( new CacheDirHandler( '', $preferred ) )->buildSubDir( 'afs-file-optimiser' )
		);
		$this->assertTrue( \is_dir( $expectedSubDir ) );
		$this->assertSame( $expectedSubDir.'/malware-clean', path_join( $expectedSubDir, 'malware-clean' ) );
		$this->assertFalse( \is_dir( $preferred.'/afs-file-optimiser' ) );
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
		return $this->normaliseCacheStorePath( $this->createTrackedTempDir( 'shield-cache-dir-handler-'.$suffix.'-' ) );
	}

	private function makeNonTmpCacheRoot( string $suffix ) :string {
		$parent = $this->normaliseCacheStorePath( \dirname( __DIR__, 3 ) );
		if ( $parent === '/tmp' || \strpos( $parent, '/tmp/' ) === 0 ) {
			$parent = '/var/tmp';
		}

		$base = $this->normaliseCacheStorePath( $this->createTrackedTempDir( 'shield-cache-dir-handler-'.$suffix.'-', $parent ) );
		$root = $base.'/shield';
		$this->mkdir( $root );
		return $root;
	}

	private function expectedExternalCacheRoot( string $root, string $suffix = 'example-com-abc' ) :string {
		return $this->normaliseCacheStorePath( $root ).'-'.$suffix;
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
