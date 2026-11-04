<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

/**
 * Runs the real AFS scan against a site at the filesystem root, where ABSPATH is "//", and against the same site in an
 * ordinary directory. The two must agree, as a root install is only a difference in how paths are spelled.
 *
 * The site has to be at "/", so this runs only in the disposable Docker test runner (`php bin/shield test:source`).
 * The parent uses the normal WordPress test lifecycle and supplies real core files from its bootstrapped tree.
 * The scan runs in a child process with its own ABSPATH.
 */
class RootAbsPathScanIntegrationTest extends ShieldWordPressTestCase {

	private const CACHE_INDEX_DIR = 'wp-content/shield-v2-0123456789abcdef0123456789abcdef';

	private const WP_LOAD_CONTENT = "<?php\ndefine( 'ABSPATH', __DIR__.'/' );\nrequire getenv( 'SHIELD_ROOT_SCAN_FIXTURE' );";

	private const CORE_FILES = [
		'index.php',
		'wp-config-sample.php',
		'wp-login.php',
		'xmlrpc.php',
		'wp-admin/about.php',
		'wp-admin/install.php',
		'wp-includes/version.php',
		'wp-includes/js/core.js',
		'wp-content/index.php',
	];

	private const OWNED_AT_ROOT = [
		'content',
		'index.php',
		'shell.php',
		'wp-config-sample.php',
		'wp-load.php',
		'wp-login.php',
		'xmlrpc.php',
		'wp-admin',
		'wp-content',
		'wp-includes',
	];

	private string $tempDir = '';

	private bool $ownsRoot = false;

	public function set_up() {
		parent::set_up();
		if ( \getenv( 'SHIELD_TEST_MODE' ) !== 'docker' ) {
			$this->markTestSkipped( 'Needs the disposable Docker test runner, as it writes a site to the filesystem root.' );
		}
		if ( !empty( \getenv( 'SHIELD_PACKAGE_PATH' ) ) ) {
			$this->markTestSkipped( 'The source runtime lane covers this; packaged code has no development autoloader.' );
		}
		$this->tempDir = \sys_get_temp_dir().'/shield-root-scan-'.\bin2hex( \random_bytes( 6 ) );
		\mkdir( $this->tempDir, 0777, true );
	}

	public function tear_down() {
		if ( $this->ownsRoot ) {
			foreach ( self::OWNED_AT_ROOT as $name ) {
				$this->remove( '/'.$name );
			}
		}
		if ( $this->tempDir !== '' ) {
			$this->remove( $this->tempDir );
		}
		parent::tear_down();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideLayouts' )]
	public function test_pristine_site_has_no_findings( string $layout ) :void {
		$site = $this->writeSite( $layout );

		$result = $this->scan( $site );

		$this->assertNull( $result[ 'exception' ] );
		$this->assertSame( [], $result[ 'findings' ] );
		$this->assertCount(
			\count( $result[ 'items' ] ),
			\array_unique( \array_map( '\\realpath', $result[ 'items' ] ) ),
			'Each physical file is listed once, including paths with different leading slashes'
		);
		foreach ( $result[ 'items' ] as $path ) {
			$this->assertStringStartsWith( $this->siteDir( $result ), $path );
		}
		$this->assertContains( $this->siteDir( $result ).'wp-admin/about.php', $result[ 'items' ] );
		$this->assertContains( $this->siteDir( $result ).'index.php', $result[ 'items' ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideLayouts' )]
	public function test_every_kind_of_change_is_classified_and_expected_files_are_left_out( string $layout ) :void {
		$site = $this->writeSite( $layout );
		$this->applyChanges( $site );

		$result = $this->scan( $site );

		$this->assertNull( $result[ 'exception' ] );
		$this->assertFindings( $this->expectedFindings(), $result );
		$this->assertNotContains( $this->siteDir( $result ).'wp-content/cache/page.php', $result[ 'items' ], 'Default exclusions apply' );
	}

	/**
	 * Hosts can define these constants with one slash in wp-config.php, regardless of how ABSPATH is spelled.
	 */
	public function test_single_slash_content_constants_at_the_root_change_nothing() :void {
		$site = $this->writeSite( 'root' );
		$this->applyChanges( $site );

		$result = $this->scan( $site, [
			'wp_content_dir' => '/wp-content',
			'wp_plugin_dir'  => '/wp-content/plugins',
		] );

		$this->assertNull( $result[ 'exception' ] );
		$this->assertFindings( $this->expectedFindings(), $result );
		$this->assertSame( $this->expectedAssetOwners(), $result[ 'asset_owners' ] );
		$this->assertSame( $this->expectedVendorLookups(), $result[ 'vendor_lookups' ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideLayouts' )]
	public function test_result_items_give_vendor_file_utilities_a_path_they_recognise( string $layout ) :void {
		$result = $this->scan( $this->writeSite( $layout ) );

		$this->assertSame( $this->expectedVendorLookups(), $result[ 'vendor_lookups' ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideLayouts' )]
	public function test_file_deleted_after_the_list_was_built_does_not_fail_the_scan( string $layout ) :void {
		$site = $this->writeSite( $layout );
		$this->write( $site.'wp-content/uploads/temporary.php', '<?php echo "x";' );

		$result = $this->scan( $site, [
			'delete_after_build' => [ $site.'wp-content/uploads/temporary.php' ],
		] );

		$this->assertContains( $this->siteDir( $result ).'wp-content/uploads/temporary.php', $result[ 'items' ] );
		$this->assertNull( $result[ 'exception' ] );
		$this->assertSame( [], $result[ 'findings' ] );
	}

	/**
	 * A scan queued before an upgrade holds paths as they were spelled at the time.
	 */
	public function test_queued_paths_in_the_old_spelling_are_still_classified_correctly() :void {
		$site = $this->writeSite( 'root' );
		\unlink( $site.'wp-admin/about.php' );
		$this->write( $site.'shell.php', '<?php echo "x";' );
		$this->write( $site.'wp-content/unknown.php', '<?php echo "x";' );

		$result = $this->scan( $site, [
			'queued_paths' => [
				'/wp-admin/about.php',
				'/wp-includes/version.php',
				'/index.php',
				'//shell.php',
				'//wp-content/unknown.php',
			],
		] );

		$this->assertNull( $result[ 'exception' ] );
		$this->assertFindings( [
			'wp-admin/about.php'     => [ 'is_missing' ],
			'shell.php'              => [ 'is_unidentified' ],
			'wp-content/unknown.php' => [ 'is_unidentified' ],
		], $result );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideLayouts' )]
	public function test_stored_fragments_still_identify_core_files( string $layout ) :void {
		$result = $this->scan( $this->writeSite( $layout ) );

		foreach ( [ 'path_full', 'legacy_path_full' ] as $pathSet ) {
			foreach ( $result[ $pathSet ] as $fragment => $resolved ) {
				$this->assertSame( $this->siteDir( $result ).$fragment, $resolved[ 'path_full' ] );
				$this->assertTrue( $resolved[ 'is_core' ], $pathSet.': '.$fragment );
			}
		}
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideLayouts' )]
	public function test_saved_exclusions_cannot_remove_core_directories_from_the_scan( string $layout ) :void {
		$result = $this->scan( $this->writeSite( $layout ) );

		$this->assertSame( [ 'wp-content/custom/*' ], $result[ 'exclusions' ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideLayouts' )]
	public function test_plugin_files_resolve_to_their_owner_from_any_spelling( string $layout ) :void {
		$result = $this->scan( $this->writeSite( $layout ) );

		$this->assertSame( $this->expectedAssetOwners(), $result[ 'asset_owners' ] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideRelocatedContentDirs' )]
	public function test_relocated_content_directory( string $layout, ?string $contentDir ) :void {
		$site = $this->writeSite( $layout );
		$this->writeContentDir( $site.'content/' );

		$contentDir = $contentDir ?? $site.'content';
		$result = $this->scan( $site, [
			'wp_content_dir' => $contentDir,
			'wp_plugin_dir'  => $contentDir.'/plugins',
		] );

		$this->assertNull( $result[ 'exception' ] );
		$this->assertFindings( [ 'content/unknown.php' => [ 'is_unidentified' ] ], $result );
	}

	public static function provideRelocatedContentDirs() :array {
		return [
			'root, built from ABSPATH'     => [ 'root', '//content' ],
			'root, written with one slash' => [ 'root', '/content' ],
			'ordinary directory'           => [ 'directory', null ],
		];
	}

	public function test_content_directory_outside_abspath() :void {
		$site = $this->writeSite( 'directory' );
		$contentDir = $this->tempDir.'/app';
		$this->writeContentDir( $contentDir.'/' );

		$result = $this->scan( $site, [
			'wp_content_dir' => $contentDir,
			'wp_plugin_dir'  => $contentDir.'/plugins',
		] );

		$this->assertNull( $result[ 'exception' ] );
		$this->assertFindings( [ $contentDir.'/unknown.php' => [ 'is_unidentified' ] ], $result );
	}

	/**
	 * Everything here is expected to be left alone except for unknown.php.
	 */
	private function writeContentDir( string $dir ) :void {
		$this->write( $dir.'unknown.php', '<?php echo "x";' );
		$this->write( $dir.'mu-plugins/example.php', '<?php echo "x";' );
		$this->write( $dir.'plugins/sample/sample.php', '<?php echo "plugin";' );
		$this->write( $dir.'themes/sample/functions.php', '<?php echo "theme";' );
		$this->write(
			$dir.'languages/admin-en_GB.l10n.php',
			"<?php return ['x-generator'=>'GlotPress/4.0','translation-revision-date'=>'2026-01-01','messages'=>['A'=>'B']];"
		);
		$this->write( $dir.'shield-v2-0123456789abcdef0123456789abcdef/index.php', "<?php\n\http_response_code(404);" );
	}

	/**
	 * Scans spell a path with one leading slash, whichever way ABSPATH spells the filesystem root.
	 */
	private function siteDir( array $result ) :string {
		return \rtrim( $result[ 'abspath' ], '/' ).'/';
	}

	private function assertFindings( array $expected, array $result ) :void {
		\ksort( $expected );
		\ksort( $result[ 'findings' ] );
		$this->assertSame( $expected, $result[ 'findings' ] );
	}

	public static function provideLayouts() :array {
		return [
			'filesystem root'    => [ 'root' ],
			'ordinary directory' => [ 'directory' ],
		];
	}

	/**
	 * @return array<string,?string>
	 */
	private function expectedAssetOwners() :array {
		return [
			'abspath'   => 'sample/sample.php|sample.php',
			'single'    => 'sample/sample.php|sample.php',
			'fragment'  => 'sample/sample.php|sample.php',
			'unowned'   => null,
		];
	}

	/**
	 * @return array<string,?string>
	 */
	private function expectedVendorLookups() :array {
		return [
			'plugin'          => 'sample/sample.php',
			'plugin_relative' => 'sample.php',
			'theme_fragment'  => 'sample/style.css',
		];
	}

	/**
	 * @return array<string,list<string>>
	 */
	private function expectedFindings() :array {
		return [
			'wp-admin/about.php'     => [ 'is_missing' ],
			'wp-admin/shell.php'     => [ 'is_unrecognised' ],
			'shell.php'              => [ 'is_unidentified' ],
			'wp-login.php'           => [ 'is_checksumfail' ],
			'wp-content/unknown.php' => [ 'is_unidentified' ],
		];
	}

	private function applyChanges( string $site ) :void {
		\unlink( $site.'wp-admin/about.php' );
		\unlink( $site.'wp-config-sample.php' );
		$this->write( $site.'wp-admin/shell.php', '<?php echo "x";' );
		$this->write( $site.'shell.php', '<?php echo "x";' );
		$this->write( $site.'wp-login.php', '<?php echo "modified";' );
		$this->write( $site.'wp-content/unknown.php', '<?php echo "x";' );
		$this->write( $site.'wp-content/cache/page.php', '<?php echo "x";' );
		$this->write( $site.'wp-content/uploads/2026/backupbuddy_dat.php', '<?php echo "x";' );
		$this->write(
			$site.'wp-includes/languages/admin-en_GB.l10n.php',
			"<?php return ['x-generator'=>'GlotPress/4.0','translation-revision-date'=>'2026-01-01','messages'=>['A'=>'B']];"
		);
		$this->write( $site.self::CACHE_INDEX_DIR.'/index.php', "<?php\n\http_response_code(404);" );
	}

	/**
	 * @return string the site directory, with a trailing slash
	 */
	private function writeSite( string $layout ) :string {
		if ( $layout === 'root' ) {
			if ( !\is_writable( '/' ) ) {
				$this->fail( 'The Docker test runner must be able to write to the filesystem root.' );
			}
			foreach ( self::OWNED_AT_ROOT as $name ) {
				if ( \file_exists( '/'.$name ) ) {
					$this->fail( 'Refusing to overwrite /'.$name );
				}
			}
			$this->ownsRoot = true;
			$site = '/';
		}
		else {
			$site = $this->tempDir.'/site/';
		}

		foreach ( self::CORE_FILES as $file ) {
			$this->write( $site.$file, $this->coreFileContent( $file ) );
		}
		// WordPress's functions.php loads this through ABSPATH, so the site needs the real file.
		$this->write( $site.'wp-includes/option.php', (string)\file_get_contents( $this->wpTree().'/wp-includes/option.php' ) );
		$this->write( $site.'wp-content/plugins/sample/sample.php', '<?php echo "plugin";' );
		$this->write( $site.'wp-content/themes/sample/style.css', '/* theme */' );
		$this->write( $site.'wp-load.php', self::WP_LOAD_CONTENT );
		return $site;
	}

	private function scan( string $site, array $overrides = [] ) :array {
		$configFile = $this->tempDir.'/config-'.\bin2hex( \random_bytes( 4 ) ).'.json';
		$this->write( $configFile, (string)\json_encode( \array_merge( [
			'shield_root' => $this->repoRoot(),
			'wp_tree'     => $this->wpTree(),
			'checksums'   => $this->coreChecksums(),
		], $overrides ) ) );

		$command = \implode( ' ', \array_map( '\escapeshellarg', [
			\PHP_BINARY,
			'-d',
			'display_errors=stderr',
			$site.'wp-load.php',
		] ) );
		$process = \proc_open(
			$command,
			[ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ],
			$pipes,
			null,
			\array_merge( \getenv(), [
				'SHIELD_ROOT_SCAN_CONFIG'  => $configFile,
				'SHIELD_ROOT_SCAN_FIXTURE' => $this->repoRoot().'/tests/fixtures/scans/root-abspath-scan.php',
			] )
		);
		$this->assertIsResource( $process );
		$stdout = (string)\stream_get_contents( $pipes[ 1 ] );
		$stderr = (string)\stream_get_contents( $pipes[ 2 ] );
		\proc_close( $process );

		$result = \json_decode( $stdout, true );
		$this->assertIsArray( $result, 'Scan process output: '.\substr( $stdout.$stderr, 0, 4000 ) );
		$this->assertSame( $site === '/' ? '//' : $site, $result[ 'abspath' ] );
		return $result;
	}

	/**
	 * Fixed values rather than hashes of the files on disk, so a change to a file is seen as a change.
	 *
	 * @return array<string,string>
	 */
	private function coreChecksums() :array {
		$checksums = [];
		foreach ( self::CORE_FILES as $file ) {
			$checksums[ $file ] = \md5( $this->coreFileContent( $file ) );
		}
		$checksums[ 'wp-load.php' ] = \md5( self::WP_LOAD_CONTENT );
		$checksums[ 'wp-includes/option.php' ] = \md5_file( $this->wpTree().'/wp-includes/option.php' );
		return $checksums;
	}

	private function coreFileContent( string $file ) :string {
		// As in WordPress, this is the one core file a scan sees as unowned, and only auto-filtering excuses it.
		return $file === 'wp-content/index.php'
			? "<?php\n// Silence is golden.\n"
			: '<?php echo '.\var_export( $file, true ).';';
	}

	private function repoRoot() :string {
		return \dirname( __DIR__, 4 );
	}

	private function wpTree() :string {
		return \rtrim( ABSPATH, '/' );
	}

	private function write( string $path, string $content ) :void {
		if ( !\is_dir( \dirname( $path ) ) ) {
			\mkdir( \dirname( $path ), 0777, true );
		}
		\file_put_contents( $path, $content );
	}

	private function remove( string $path ) :void {
		if ( \is_dir( $path ) && !\is_link( $path ) ) {
			foreach ( \array_diff( (array)\scandir( $path ), [ '.', '..' ] ) as $name ) {
				$this->remove( $path.'/'.$name );
			}
			\rmdir( $path );
		}
		elseif ( \file_exists( $path ) || \is_link( $path ) ) {
			\unlink( $path );
		}
	}
}
