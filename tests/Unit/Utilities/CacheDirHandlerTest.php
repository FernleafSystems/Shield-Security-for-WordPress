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
	CacheStoreTestDb,
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

	private CacheStoreTestDb $db;

	private int $blogID = 1;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->fs = new CacheStoreTestFs();
		$this->db = new CacheStoreTestDb();
		$tmpDir = $this->makeTempDir( 'tmp' );
		$this->registerCacheStoreWordPressFunctions( $this->fs, $tmpDir );
		ServicesState::installItems( [
			'service_request' => new CacheStoreTestRequest(),
			'service_wpfs'    => $this->fs,
			'service_wpdb'    => $this->db,
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

	public function test_zero_string_configuration_is_not_treated_as_absent() :void {
		$discoveryRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/shield' );
		$this->mkdir( $discoveryRoot );
		$this->fs->failDir( '0' );

		foreach ( [ new CacheDirHandler( '', '0' ), new CacheDirHandler( '0', '' ) ] as $handler ) {
			$this->assertSame( '', $handler->locateExistingDir() );
			$this->assertSame( '', $handler->dir() );
		}

		$this->assertTrue( \is_dir( $discoveryRoot ), 'A configured path must not consume discovery roots.' );
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

	/**
	 * @dataProvider oldUrlCollisionDataProvider
	 */
	public function test_external_namespace_ignores_old_url_collisions_but_changes_with_install_context(
		string $firstURL,
		string $secondURL
	) :void {
		$base = $this->makeTempDir( 'old-url-collision' );
		$this->setInstallContext( 'wp_first_', 1 );
		$this->setCacheStoreSiteUrl( $firstURL );
		$firstRoot = ( new CacheDirHandler( '', $base ) )->dir();

		$this->setCacheStoreSiteUrl( $secondURL );
		$this->assertSame( $firstRoot, ( new CacheDirHandler( '', $base ) )->dir() );

		$this->setInstallContext( 'wp_second_', 2 );
		$secondRoot = ( new CacheDirHandler( '', $base ) )->dir();
		$this->assertNotSame( $firstRoot, $secondRoot );
	}

	public function oldUrlCollisionDataProvider() :array {
		return [
			'punctuation' => [ 'https://a-b.example/', 'https://a.b-example/' ],
			'path case'   => [ 'https://example.com/Admin/', 'https://example.com/admin/' ],
			'long prefix' => [
				'https://example.com/'.\str_repeat( 'same-segment-', 5 ).'first',
				'https://example.com/'.\str_repeat( 'same-segment-', 5 ).'second',
			],
		];
	}

	public function test_external_namespace_has_bounded_v2_format() :void {
		$root = ( new CacheDirHandler( '', $this->makeTempDir( 'v2-format' ) ) )->dir();

		$this->assertMatchesRegularExpression( '#^shield-v2-[a-f0-9]{32}$#', \basename( $root ) );
		$this->assertSame( $this->expectedExternalCacheBasename(), \basename( $root ) );
	}

	public function test_external_namespace_changes_with_base_prefix_and_blog_id() :void {
		$base = $this->makeTempDir( 'context-identity' );
		$initial = ( new CacheDirHandler( '', $base ) )->dir();

		$this->setInstallContext( 'wp_other_', 1 );
		$prefixChanged = ( new CacheDirHandler( '', $base ) )->dir();
		$this->setInstallContext( ' wp_other_ ', 1 );
		$whitespacePrefix = ( new CacheDirHandler( '', $base ) )->dir();
		$this->setInstallContext( 'wp_other_', 7 );
		$blogChanged = ( new CacheDirHandler( '', $base ) )->dir();

		$this->assertNotSame( $initial, $prefixChanged );
		$this->assertNotSame( $prefixChanged, $whitespacePrefix );
		$this->assertNotSame( $prefixChanged, $blogChanged );
	}

	public function test_external_preferred_cache_root_escaped_from_abspath_is_namespaced() :void {
		$base = $this->normaliseCacheStorePath(
			\dirname( \rtrim( ABSPATH, '/\\' ) ).'/shield-cache-dir-handler-escaped-'.\uniqid()
		);
		$preferred = $this->normaliseCacheStorePath( \rtrim( ABSPATH, '/\\' ).'/../'.\basename( $base ).'/shield' );
		$expected = $this->expectedExternalCacheRoot( $base.'/shield' );
		$this->mkdir( $base );
		$this->tempDirs[] = $base;

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertTrue( \is_dir( $expected ) );
		$this->assertFalse( \is_dir( $base.'/shield' ) );
	}

	public function test_external_configured_forms_converge_without_deepening() :void {
		$base = $this->makeTempDir( 'canonical-forms' );
		$root = $this->expectedExternalCacheRoot( $base.'/shield' );
		$legacy = $base.'/shield-example-com-admin';

		foreach ( [ $base, $base.'/shield', $legacy, $root ] as $configured ) {
			$handler = new CacheDirHandler( '', $configured );
			$this->assertSame( $root, $handler->dir() );
			$this->assertSame( $root, $handler->dir( true ) );
			$this->assertSame( $root, ( new CacheDirHandler( '', $configured ) )->dir() );
		}

		$this->assertFalse( \is_dir( $legacy ) );
		$this->assertFalse( \is_dir( $root.'/'.$this->expectedExternalCacheBasename() ) );
	}

	public function test_foreign_v2_config_is_strict_for_write_and_read_modes() :void {
		$base = $this->makeTempDir( 'strict-foreign' );
		$foreign = $base.'/'.$this->foreignExternalCacheBasename();
		$discoveryRoot = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/shield' );
		$this->mkdir( $foreign );
		$this->mkdir( $discoveryRoot );

		$this->assertSame( '', ( new CacheDirHandler( '', $foreign ) )->dir() );
		$this->assertSame( '', ( new CacheDirHandler( '', $foreign ) )->locateExistingDir() );
		$this->assertFalse( \is_dir( $foreign.'/'.$this->expectedExternalCacheBasename() ) );
		$this->assertTrue( \is_dir( $discoveryRoot ), 'Strict configuration must not consume discovery roots.' );
	}

	public function test_paths_beneath_current_or_foreign_v2_roots_are_rejected() :void {
		$base = $this->makeTempDir( 'nested-v2' );
		$currentChild = $this->expectedExternalCacheRoot( $base.'/shield' ).'/nested';
		$foreignChild = $base.'/'.$this->foreignExternalCacheBasename().'/nested';

		foreach ( [ $currentChild, $foreignChild ] as $configured ) {
			$this->assertSame( '', ( new CacheDirHandler( '', $configured ) )->dir() );
			$this->assertSame( '', ( new CacheDirHandler( '', $configured ) )->locateExistingDir() );
			$this->assertFalse( \is_dir( $configured ) );
		}
	}

	public function test_unavailable_external_identity_fails_closed_but_local_cache_remains_available() :void {
		$externalBase = $this->makeTempDir( 'missing-identity' );
		$localBase = $this->normaliseCacheStorePath( WP_CONTENT_DIR.'/uploads' );

		$this->setInstallContext( '', 1 );
		$this->assertSame( '', ( new CacheDirHandler( '', $externalBase ) )->dir() );
		$this->assertSame( '', ( new CacheDirHandler( '', $externalBase ) )->locateExistingDir() );
		$this->assertSame( $localBase.'/shield', ( new CacheDirHandler( '', $localBase ) )->dir() );

		$this->removeDir( $localBase.'/shield' );
		$this->setInstallContext( 'wp_', 0 );
		$this->assertSame( '', ( new CacheDirHandler( '', $externalBase ) )->dir() );
		$this->assertSame( '', ( new CacheDirHandler( '', $externalBase ) )->locateExistingDir() );
		$this->assertSame( $localBase.'/shield', ( new CacheDirHandler( '', $localBase ) )->dir() );
	}

	public function test_automatic_lookup_only_finds_exact_current_external_v2_without_writes() :void {
		$base = $this->cacheStoreTmpDir;
		$unsuffixed = $base.'/shield';
		$foreign = $base.'/'.$this->foreignExternalCacheBasename();
		$current = $this->expectedExternalCacheRoot( $unsuffixed );
		$this->mkdir( $unsuffixed );
		$this->mkdir( $foreign );

		$this->assertSame( '', ( new CacheDirHandler() )->locateExistingDir() );
		$this->mkdir( $current );
		$this->assertSame( $current, ( new CacheDirHandler() )->locateExistingDir() );
		$this->assertFileDoesNotExist( $current.'/assessed.flag' );
		$this->assertFileDoesNotExist( $current.'/.htaccess' );
		$this->assertFileDoesNotExist( $current.'/index.php' );
		$this->assertFileDoesNotExist( $current.'/README.txt' );
	}

	public function test_v2_classification_precedes_legacy_and_legacy_grammar_is_bounded() :void {
		$base = $this->makeTempDir( 'legacy-grammar' );
		$root = $this->expectedExternalCacheRoot( $base.'/shield' );
		$foreign = $base.'/'.$this->foreignExternalCacheBasename();

		$this->assertSame( '', ( new CacheDirHandler( '', $foreign ) )->dir() );
		foreach ( [ $base.'/shield-a', $base.'/shield-'.\str_repeat( 'a', 48 ) ] as $legacy ) {
			$this->assertSame( $root, ( new CacheDirHandler( '', $legacy ) )->dir() );
			$this->assertFalse( \is_dir( $legacy ) );
		}
		foreach ( [
			$base.'/shield-',
			$base.'/shield-a--b',
			$base.'/shield-a_b',
			$base.'/shield-'.\str_repeat( 'a', 49 ),
		] as $notLegacy ) {
			$this->assertSame(
				$notLegacy.'/'.$this->expectedExternalCacheBasename(),
				( new CacheDirHandler( '', $notLegacy ) )->dir()
			);
		}
	}

	public function test_windows_basename_classification_follows_filesystem_case_rules() :void {
		if ( \DIRECTORY_SEPARATOR !== '\\' ) {
			$this->markTestSkipped( 'Windows-only case-insensitive basename contract.' );
		}

		$base = $this->makeTempDir( 'windows-case' );
		$current = $this->expectedExternalCacheRoot( $base.'/shield' );
		$currentUpper = $base.'/'.\strtoupper( \basename( $current ) );
		$foreignUpper = $base.'/'.\strtoupper( $this->foreignExternalCacheBasename() );

		$this->assertSame( $current, ( new CacheDirHandler( '', $currentUpper ) )->dir() );
		$this->assertSame( '', ( new CacheDirHandler( '', $foreignUpper ) )->dir() );
		$this->assertSame( $current, ( new CacheDirHandler( '', $base.'/SHIELD-LEGACY' ) )->dir() );
	}

	public function test_failed_external_candidate_and_sentinel_are_not_deleted() :void {
		$base = $this->makeTempDir( 'failed-external' );
		$root = $this->expectedExternalCacheRoot( $base.'/shield' );
		$this->mkdir( $root );
		$sentinel = $root.'/sentinel.txt';
		\file_put_contents( $sentinel, 'keep' );
		$this->fs->failDir( $root );

		$this->assertSame( '', ( new CacheDirHandler( '', $base ) )->dir() );
		$this->assertFileExists( $sentinel );
		$this->assertNotContains( $root, $this->fs->deletedDirs );
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

	public function test_fresh_candidate_writes_each_protection_once_with_canonical_content() :void {
		$preferred = $this->makeNonTmpCacheRoot( 'fresh-protections' );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );

		foreach ( $this->protectionContents() as $filename => $content ) {
			$path = $expected.'/'.$filename;
			$this->assertFileExists( $path );
			$this->assertSame( $content, \file_get_contents( $path ) );
			$this->assertSame( 1, $this->fs->fileWriteCounts[ $path ] ?? 0 );
		}
	}

	/**
	 * @dataProvider protectionFileDataProvider
	 */
	public function test_write_mode_does_not_rewrite_current_protection( string $filename ) :void {
		$preferred = $this->makeNonTmpCacheRoot( 'current-'.\str_replace( '.', '-', $filename ) );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );

		$protection = $expected.'/'.$filename;
		$this->assertFileExists( $protection );
		$this->fs->fileWriteCounts[ $protection ] = 0;

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertSame( 0, $this->fs->fileWriteCounts[ $protection ] ?? 0 );
	}

	/**
	 * @dataProvider protectionFileDataProvider
	 */
	public function test_stale_protection_is_repaired_and_verified( string $filename ) :void {
		$preferred = $this->makeNonTmpCacheRoot( 'stale-'.\str_replace( '.', '-', $filename ) );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$protection = $expected.'/'.$filename;
		$this->mkdir( $expected );
		\file_put_contents( $protection, 'stale' );

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertSame( $this->protectionContents()[ $filename ], \file_get_contents( $protection ) );
		$this->assertSame( 1, $this->fs->fileWriteCounts[ $protection ] ?? 0 );
	}

	public function test_write_mode_skips_protection_files_for_tmp_cache_root() :void {
		if ( \DIRECTORY_SEPARATOR === '\\' ) {
			$this->markTestSkipped( 'The literal /tmp cache-root guard is Unix-specific.' );
		}

		$base = $this->normaliseCacheStorePath( '/tmp/shield-cache-dir-handler-tmp-skip-'.\uniqid() );
		$preferred = $base.'/shield';
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$this->tempDirs[] = $base;

		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertFileExists( $expected.'/assessed.flag' );
		$this->assertFileDoesNotExist( $expected.'/.htaccess' );
		$this->assertFileDoesNotExist( $expected.'/index.php' );
		$this->assertFileDoesNotExist( $expected.'/README.txt' );
		$this->assertFalse( \is_dir( $preferred ) );
	}

	/**
	 * @dataProvider tmpPathDataProvider
	 */
	public function test_tmp_exemption_requires_exact_path_segment( string $path, bool $expected ) :void {
		$method = new \ReflectionMethod( CacheDirHandler::class, 'isTmpPath' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( new CacheDirHandler(), $path ) );
	}

	public static function tmpPathDataProvider() :array {
		return [
			'tmp root'       => [ '/tmp', true ],
			'tmp child'      => [ '/tmp/shield', true ],
			'prefixed name'  => [ '/tmp-shield', false ],
			'prefixed path'  => [ '/tmpfoo/shield', false ],
			'similar path'   => [ '/temporary/shield', false ],
			'windows path'   => [ 'C:/tmp/shield', false ],
		];
	}

	/**
	 * @dataProvider protectionFileDataProvider
	 */
	public function test_unhashable_protection_entry_rejects_candidate( string $filename ) :void {
		$preferred = $this->makeNonTmpCacheRoot( 'unhashable-'.\str_replace( '.', '-', $filename ) );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$this->mkdir( $expected.'/'.$filename );

		$this->assertSame( '', ( new CacheDirHandler( '', $preferred ) )->dir() );
	}

	public function protectionFileDataProvider() :array {
		return [
			'htaccess' => [ '.htaccess' ],
			'index'    => [ 'index.php' ],
			'readme'   => [ 'README.txt' ],
		];
	}

	/**
	 * @dataProvider protectionFileDataProvider
	 */
	public function test_failed_protection_write_rejects_candidate( string $filename ) :void {
		$preferred = $this->makeNonTmpCacheRoot( 'failed-write-'.\str_replace( '.', '-', $filename ) );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$this->mkdir( $expected );
		$protection = $expected.'/'.$filename;
		\file_put_contents( $protection, 'stale' );
		$this->fs->failFileWrite( $protection );

		$this->assertSame( '', ( new CacheDirHandler( '', $preferred ) )->dir() );
		$this->assertSame( 'stale', \file_get_contents( $protection ) );
	}

	/**
	 * @dataProvider protectionFileDataProvider
	 */
	public function test_unverifiable_repair_rejects_candidate( string $filename ) :void {
		$preferred = $this->makeNonTmpCacheRoot( 'unverifiable-repair-'.\str_replace( '.', '-', $filename ) );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$protection = $expected.'/'.$filename;
		$handler = new CacheDirHandlerHashFailureProbe( '', $preferred );
		$handler->failHashFor( $protection );

		$this->assertSame( '', $handler->dir() );
		$this->assertSame( 1, $this->fs->fileWriteCounts[ $protection ] ?? 0 );
	}

	/**
	 * @dataProvider protectionFileDataProvider
	 */
	public function test_protection_that_disappears_before_hashing_is_repaired( string $filename ) :void {
		$preferred = $this->makeNonTmpCacheRoot( 'disappears-'.\str_replace( '.', '-', $filename ) );
		$expected = $this->expectedExternalCacheRoot( $preferred );
		$this->assertSame( $expected, ( new CacheDirHandler( '', $preferred ) )->dir() );
		$protection = $expected.'/'.$filename;
		$handler = new CacheDirHandlerHashFailureProbe( '', $preferred );
		$handler->disappearOnceBeforeHashing( $protection );

		$this->assertSame( $expected, $handler->dir() );
		$this->assertFileExists( $protection );
		$this->assertSame( 2, $this->fs->fileWriteCounts[ $protection ] ?? 0 );
	}

	/**
	 * @dataProvider protectionFileDataProvider
	 */
	public function test_protection_failure_falls_through_to_usable_sibling( string $filename ) :void {
		$first = $this->makeNonTmpCacheRoot( 'failed-candidate-'.\str_replace( '.', '-', $filename ) );
		$second = $this->makeNonTmpCacheRoot( 'usable-candidate-'.\str_replace( '.', '-', $filename ) );
		$sentinel = $first.'/sentinel.txt';
		\file_put_contents( $sentinel, 'keep' );
		$this->mkdir( $first.'/'.$filename );

		$method = new \ReflectionMethod( CacheDirHandler::class, 'assessCandidates' );
		$method->setAccessible( true );
		$this->assertSame( $second, $method->invoke( new CacheDirHandler(), [ $first, $second ] ) );
		$this->assertFileExists( $sentinel );
		foreach ( $this->protectionContents() as $siblingFilename => $content ) {
			$this->assertSame( $content, \file_get_contents( $second.'/'.$siblingFilename ) );
		}
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

	/**
	 * @return array<string,string>
	 */
	private function protectionContents() :array {
		return [
			'.htaccess' => \implode( "\n", [
				"# BEGIN SHIELD",
				"Options -Indexes",
				"Order allow,deny",
				"Deny from all",
				'<FilesMatch "^.*\.(css|js)$">',
				" Allow from all",
				'</FilesMatch>',
				"# END SHIELD"
			] ),
			'index.php'  => "<?php\n\http_response_code(404);",
			'README.txt' => "This is a temporary caching folder used by the Shield plugin. You can safely delete it, but it'll be recreated if required.\n",
		];
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normaliseCacheStorePath( \sys_get_temp_dir().'/cache-dir-handler-'.$suffix.'-'.\uniqid() );
		$this->mkdir( $dir );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function makeNonTmpCacheRoot( string $suffix ) :string {
		$cwd = \getcwd();
		$base = $this->normaliseCacheStorePath(
			( \is_string( $cwd ) && $cwd !== '' ? $cwd : \sys_get_temp_dir() )
			.'/tmp/shield-cache-dir-handler-'.$suffix.'-'.\uniqid()
		);
		if ( \strpos( $base, '/tmp/' ) === 0 ) {
			$base = $this->normaliseCacheStorePath( '/var/tmp/shield-cache-dir-handler-'.$suffix.'-'.\uniqid() );
		}

		$root = $base.'/shield';
		$this->mkdir( $root );
		$this->tempDirs[] = $base;
		return $root;
	}

	private function expectedExternalCacheRoot( string $root ) :string {
		return $this->normaliseCacheStorePath( \dirname( $root ).'/'.$this->expectedExternalCacheBasename() );
	}

	private function expectedExternalCacheBasename() :string {
		$absPath = \rtrim( $this->normaliseCacheStorePath( ABSPATH ), '/' );
		if ( \DIRECTORY_SEPARATOR === '\\' ) {
			$absPath = \strtolower( $absPath );
		}
		$seed = \implode( "\0", [
			'shield-external-cache-namespace-v2',
			$absPath,
			(string)DB_HOST,
			(string)DB_NAME,
			$this->db->getPrefix(),
			(string)$this->blogID,
		] );
		return 'shield-v2-'.\substr( \hash( 'sha256', $seed ), 0, 32 );
	}

	private function foreignExternalCacheBasename() :string {
		$foreignHash = \str_repeat( 'f', 32 );
		if ( $foreignHash === \substr( $this->expectedExternalCacheBasename(), \strlen( 'shield-v2-' ) ) ) {
			$foreignHash = \str_repeat( 'e', 32 );
		}
		return 'shield-v2-'.$foreignHash;
	}

	private function setInstallContext( string $basePrefix, int $blogID ) :void {
		$this->db->setBasePrefix( $basePrefix );
		$this->blogID = $blogID;
		$this->setCacheStoreBlogID( $blogID );
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

class CacheDirHandlerHashFailureProbe extends CacheDirHandler {

	/**
	 * @var string[]
	 */
	private array $failedHashPaths = [];

	/**
	 * @var string[]
	 */
	private array $disappearOncePaths = [];

	public function failHashFor( string $path ) :void {
		$this->failedHashPaths[] = \str_replace( '\\', '/', $path );
	}

	public function disappearOnceBeforeHashing( string $path ) :void {
		$this->disappearOncePaths[] = \str_replace( '\\', '/', $path );
	}

	protected function hashProtectionFile( string $path ) :?string {
		$normalised = \str_replace( '\\', '/', $path );
		$key = \array_search( $normalised, $this->disappearOncePaths, true );
		if ( $key !== false ) {
			unset( $this->disappearOncePaths[ $key ] );
			@\unlink( $path );
		}
		return \in_array( $normalised, $this->failedHashPaths, true ) ? null : parent::hashProtectionFile( $path );
	}
}
