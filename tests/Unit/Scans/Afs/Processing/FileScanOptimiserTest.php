<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Processing;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\{
	FileScanOptimiser,
	TrustedFileContext
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	CoreFileHashes,
	Fs,
	General,
	Plugins,
	Request,
	Themes
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

class FileScanOptimiserTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalisePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalisePath( $path ) );
		Functions\when( 'get_theme_root' )->alias( fn() :string => $this->normalisePath( WP_CONTENT_DIR.'/themes' ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_missing_cache_dir_fails_open() :void {
		$cacheDir = $this->normalisePath( \sys_get_temp_dir().'/shield-missing-cache-'.\uniqid() );
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment( $cacheDir, false );
		$optimiser = new FileScanOptimiser();

		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	public function test_unbuildable_optimiser_cache_dir_fails_open_without_writing_cache() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [], [], null, false );
		$optimiser = new FileScanOptimiser();
		$action = $this->newAction( [ 'bad_token' ] );

		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );
		$optimiser->recordCleanMalwareVerdict( $path, $action );

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $action ) );
		$this->assertFalse( $optimiser->hasCleanMalwareVerdict( $path, $action ) );
		$this->assertFileDoesNotExist( $this->normalisePath( $cacheDir.'/afs-file-optimiser' ) );
	}

	public function test_exact_known_valid_context_hit_skips_file() :void {
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();

		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	public function test_known_valid_context_misses_for_version_path_and_hash_changes() :void {
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$otherPath = $this->writeFile( ABSPATH.'wp-admin/other.php', '<?php clean();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment( $cacheDir );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		\file_put_contents( $path, '<?php changed();' );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $otherPath, $this->newAction() ) );

		\file_put_contents( $path, '<?php clean();' );
		$this->installEnvironment( $cacheDir, true, '6.5.1' );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	public function test_same_content_in_different_plugin_does_not_skip_known_valid_context() :void {
		$alpha = $this->writeFile( WP_PLUGIN_DIR.'/alpha/dup.php', '<?php shared();' );
		$beta = $this->writeFile( WP_PLUGIN_DIR.'/beta/dup.php', '<?php shared();' );
		$this->installEnvironment(
			$this->makeTempDir( 'cache' ),
			true,
			'6.5.0',
			[ 'alpha/alpha.php', 'beta/beta.php' ]
		);
		$optimiser = new FileScanOptimiser();

		$optimiser->recordKnownValidFile( $alpha, new TrustedFileContext( 'plugin', 'alpha/alpha.php', '1.0.0', 'dup.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $alpha, $this->newAction() ) );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $beta, $this->newAction() ) );
	}

	public function test_branch_disabled_contexts_do_not_skip_known_valid_files() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$core = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$plugin = $this->writeFile( WP_PLUGIN_DIR.'/alpha/dup.php', '<?php plugin();' );
		$theme = $this->writeFile( WP_CONTENT_DIR.'/themes/clean/style.php', '<?php theme();' );
		$optimiser = new FileScanOptimiser();

		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'alpha/alpha.php' ],
			[ 'clean' ],
			null,
			true,
			new OptimiserAfsComponent( true, true, true )
		);
		$optimiser->recordKnownValidFile( $core, $this->coreContext( 'wp-admin/core.php' ) );
		$optimiser->recordKnownValidFile( $plugin, new TrustedFileContext( 'plugin', 'alpha/alpha.php', '1.0.0', 'dup.php' ) );
		$optimiser->recordKnownValidFile( $theme, new TrustedFileContext( 'theme', 'clean', '1.0.0', 'style.php' ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $core, $this->newAction() ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $plugin, $this->newAction() ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $theme, $this->newAction() ) );

		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'alpha/alpha.php' ],
			[ 'clean' ],
			null,
			true,
			new OptimiserAfsComponent( false, true, true )
		);
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $core, $this->newAction() ) );

		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'alpha/alpha.php' ],
			[ 'clean' ],
			null,
			true,
			new OptimiserAfsComponent( true, false, true )
		);
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $plugin, $this->newAction() ) );

		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'alpha/alpha.php' ],
			[ 'clean' ],
			null,
			true,
			new OptimiserAfsComponent( true, true, false )
		);
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $theme, $this->newAction() ) );
	}

	/**
	 * @dataProvider patternFamiliesProvider
	 */
	public function test_malware_clean_verdict_fingerprint_includes_pattern_family( string $family ) :void {
		$path = $this->writeFile( ABSPATH.'wp-content/uploads/clean.php', '<?php clean();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();
		$action = $this->newActionWithPatterns( $family, [ 'bad_token' ] );

		$optimiser->recordCleanMalwareVerdict( $path, $action );

		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $path, $action ) );
		$this->assertFalse( $optimiser->hasCleanMalwareVerdict(
			$path,
			$this->newActionWithPatterns( $family, [ 'different_token' ] )
		) );
	}

	public function test_malware_clean_verdict_requires_same_sha256_when_size_matches() :void {
		$path = $this->writeFile( ABSPATH.'wp-content/uploads/clean.php', '<?php clean_a();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();
		$action = $this->newAction( [ 'bad_token' ] );

		$optimiser->recordCleanMalwareVerdict( $path, $action );

		\file_put_contents( $path, '<?php clean_b();' );
		$this->assertFalse( $optimiser->hasCleanMalwareVerdict( $path, $action ) );
	}

	public function test_malformed_cache_lines_are_ignored_inside_optimiser() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-content/uploads/clean.php', '<?php clean();' );
		$this->installEnvironment( $cacheDir );
		$optimiser = new FileScanOptimiser();
		$action = $this->newAction( [ 'bad_token' ] );
		$optimiser->recordCleanMalwareVerdict( $path, $action );
		foreach ( \glob( $cacheDir.'/afs-file-optimiser/malware-clean/*.jsonl' ) ?: [] as $file ) {
			\file_put_contents( $file, "not-json\n".\file_get_contents( $file ) );
		}

		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $path, $action ) );
	}

	/**
	 * @dataProvider staleSchemaProvider
	 */
	public function test_records_without_current_schema_version_do_not_hit_cache( ?int $schemaVersion ) :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$knownValid = $this->writeFile( ABSPATH.'wp-admin/schema-valid.php', '<?php clean_valid();' );
		$clean = $this->writeFile( ABSPATH.'wp-content/uploads/schema-clean.php', '<?php clean_malware();' );
		$this->installEnvironment( $cacheDir );
		$optimiser = new FileScanOptimiser();
		$action = $this->newAction( [ 'bad_token' ] );

		$optimiser->recordKnownValidFile( $knownValid, $this->coreContext( 'wp-admin/schema-valid.php' ) );
		$optimiser->recordCleanMalwareVerdict( $clean, $action );
		$this->rewriteCacheRecords(
			$cacheDir,
			static function ( array $record ) use ( $schemaVersion ) :array {
				if ( $schemaVersion === null ) {
					unset( $record[ 'schema_version' ] );
				}
				else {
					$record[ 'schema_version' ] = $schemaVersion;
				}
				return $record;
			}
		);

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $knownValid, $action ) );
		$this->assertFalse( $optimiser->hasCleanMalwareVerdict( $clean, $action ) );
	}

	public function test_records_without_family_specific_fields_do_not_hit_cache() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$knownValid = $this->writeFile( ABSPATH.'wp-admin/schema-valid.php', '<?php clean_valid();' );
		$clean = $this->writeFile( ABSPATH.'wp-content/uploads/schema-clean.php', '<?php clean_malware();' );
		$this->installEnvironment( $cacheDir );
		$optimiser = new FileScanOptimiser();
		$action = $this->newAction( [ 'bad_token' ] );

		$optimiser->recordKnownValidFile( $knownValid, $this->coreContext( 'wp-admin/schema-valid.php' ) );
		$optimiser->recordCleanMalwareVerdict( $clean, $action );
		$this->rewriteCacheRecords(
			$cacheDir,
			static function ( array $record ) :array {
				unset( $record[ 'context_key' ], $record[ 'pattern_fingerprint' ] );
				return $record;
			}
		);

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $knownValid, $action ) );
		$this->assertFalse( $optimiser->hasCleanMalwareVerdict( $clean, $action ) );
	}

	public function test_stale_cleanup_removes_old_records_and_preserves_fresh_records() :void {
		$oldClean = $this->writeFile( ABSPATH.'wp-content/uploads/old.php', '<?php old_clean();' );
		$freshClean = $this->writeFile( ABSPATH.'wp-content/uploads/fresh.php', '<?php fresh_clean();' );
		$oldValid = $this->writeFile( ABSPATH.'wp-admin/old-valid.php', '<?php old_valid();' );
		$freshValid = $this->writeFile( ABSPATH.'wp-admin/fresh-valid.php', '<?php fresh_valid();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$request = new OptimiserRequest( 100 );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [], [], $request );
		$optimiser = new FileScanOptimiser();
		$action = $this->newAction( [ 'bad_token' ] );
		$optimiser->recordCleanMalwareVerdict( $oldClean, $action );
		$optimiser->recordKnownValidFile( $oldValid, $this->coreContext( 'wp-admin/old-valid.php' ) );
		$request->ts = 300;
		$optimiser->recordCleanMalwareVerdict( $freshClean, $action );
		$optimiser->recordKnownValidFile( $freshValid, $this->coreContext( 'wp-admin/fresh-valid.php' ) );

		$optimiser->cleanStaleHashesOlderThan( 200 );

		$this->assertFalse( $optimiser->hasCleanMalwareVerdict( $oldClean, $action ) );
		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $freshClean, $action ) );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $oldValid, $action ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $freshValid, $action ) );
	}

	public function test_clear_clean_malware_verdict_cache_preserves_known_valid_cache() :void {
		$clean = $this->writeFile( ABSPATH.'wp-content/uploads/clear-clean.php', '<?php clean_malware();' );
		$valid = $this->writeFile( ABSPATH.'wp-admin/clear-valid.php', '<?php clean_valid();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();
		$action = $this->newAction( [ 'bad_token' ] );

		$optimiser->recordCleanMalwareVerdict( $clean, $action );
		$optimiser->recordKnownValidFile( $valid, $this->coreContext( 'wp-admin/clear-valid.php' ) );
		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $clean, $action ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $valid, $action ) );

		$optimiser->clearCleanMalwareVerdictCache();

		$this->assertFalse( $optimiser->hasCleanMalwareVerdict( $clean, $action ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $valid, $action ) );
	}

	private function installEnvironment(
		string $cacheDir,
		bool $cacheExists = true,
		string $wpVersion = '6.5.0',
		array $pluginFiles = [],
		array $themes = [],
		?OptimiserRequest $request = null,
		bool $cacheBuildable = true,
		?OptimiserAfsComponent $afsComponent = null
	) :void {
		ServicesState::installItems( [
			'service_corefilehashes' => new OptimiserCoreHashes(),
			'service_request'        => $request ?? new OptimiserRequest( 1700000000 ),
			'service_wpfs'           => new OptimiserFs(),
			'service_wpgeneral'      => new OptimiserGeneral( $wpVersion ),
			'service_wpplugins'      => new OptimiserPlugins( $pluginFiles ),
			'service_wpthemes'       => new OptimiserThemes( $themes ),
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cache_dir_handler = new OptimiserCacheDir( $cacheDir, $cacheExists, $cacheBuildable );
		$controller->comps = (object)[
			'scans' => new class( $afsComponent ?? new OptimiserAfsComponent() ) {
				private OptimiserAfsComponent $afsComponent;

				public function __construct( OptimiserAfsComponent $afsComponent ) {
					$this->afsComponent = $afsComponent;
				}

				public function AFS() :OptimiserAfsComponent {
					return $this->afsComponent;
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function newAction( array $rawPatterns = [] ) :ScanActionVO {
		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->file_exts = [ 'php' ];
		$action->patterns_raw = $rawPatterns;
		$action->patterns_iraw = [];
		$action->patterns_regex = [];
		$action->patterns_functions = [];
		$action->patterns_keywords = [];
		return $action;
	}

	private function newActionWithPatterns( string $family, array $patterns ) :ScanActionVO {
		$action = $this->newAction();
		$property = 'patterns_'.$family;
		$action->{$property} = $patterns;
		return $action;
	}

	public static function patternFamiliesProvider() :array {
		return [
			'raw'       => [ 'raw' ],
			'iraw'      => [ 'iraw' ],
			'regex'     => [ 'regex' ],
			'functions' => [ 'functions' ],
			'keywords'  => [ 'keywords' ],
		];
	}

	public static function staleSchemaProvider() :array {
		return [
			'missing schema' => [ null ],
			'wrong schema'   => [ 0 ],
		];
	}

	private function rewriteCacheRecords( string $cacheDir, callable $mutator ) :void {
		foreach ( \glob( $cacheDir.'/afs-file-optimiser/*/*.jsonl' ) ?: [] as $file ) {
			$records = [];
			foreach ( \file( $file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES ) ?: [] as $line ) {
				$record = \json_decode( $line, true );
				if ( \is_array( $record ) ) {
					$records[] = $mutator( $record );
				}
			}
			\file_put_contents(
				$file,
				\implode( "\n", \array_map( static fn( array $record ) :string => \json_encode( $record ), $records ) )."\n"
			);
		}
	}

	private function coreContext( string $relativePath ) :TrustedFileContext {
		return new TrustedFileContext( 'core', 'core', '6.5.0', $relativePath );
	}

	private function writeFile( string $path, string $content ) :string {
		$path = $this->normalisePath( $path );
		if ( !\is_dir( \dirname( $path ) ) ) {
			@\mkdir( \dirname( $path ), 0777, true );
		}
		\file_put_contents( $path, $content );
		return $path;
	}

	private function makeTempDir( string $suffix ) :string {
		$dir = $this->normalisePath( \sys_get_temp_dir().'/shield-optimiser-'.$suffix.'-'.\uniqid() );
		@\mkdir( $dir, 0777, true );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function normalisePath( string $path ) :string {
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
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $dir );
	}
}

class OptimiserCacheDir {
	private string $dir;
	private bool $exists;
	private bool $buildable;

	public function __construct( string $dir, bool $exists, bool $buildable = true ) {
		$this->dir = $dir;
		$this->exists = $exists;
		$this->buildable = $buildable;
	}

	public function exists() :bool {
		return $this->exists && \is_dir( $this->dir ) && \is_writable( $this->dir );
	}

	public function buildSubDir( string $subDir ) :string {
		if ( !$this->exists() || !$this->buildable ) {
			return '';
		}
		$path = $this->dir.'/'.$subDir;
		return ( \is_dir( $path ) || @\mkdir( $path, 0777, true ) ) ? $path : '';
	}
}

class OptimiserRequest extends Request {
	public int $ts;

	public function __construct( int $ts ) {
		$this->ts = $ts;
	}

	public function ts( bool $update = true ) :int {
		unset( $update );
		return $this->ts;
	}
}

class OptimiserFs extends Fs {
	public function isAccessibleFile( string $file ) :bool {
		return \is_file( $file ) && \is_readable( $file );
	}

	public function isAbsPath( $path ) {
		return \preg_match( '#^([A-Z]:)?/#i', \str_replace( '\\', '/', (string)$path ) ) === 1;
	}
}

class OptimiserGeneral extends General {
	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	public function getVersion( $ignoreClassicpress = false ) :string {
		unset( $ignoreClassicpress );
		return $this->version;
	}
}

class OptimiserCoreHashes extends CoreFileHashes {
	public function isCoreFile( $file ) :bool {
		return \strpos( \str_replace( '\\', '/', (string)$file ), '/wp-admin/' ) !== false;
	}
}

class OptimiserAfsComponent {
	private bool $coreEnabled;
	private bool $pluginsEnabled;
	private bool $themesEnabled;

	public function __construct( bool $coreEnabled = true, bool $pluginsEnabled = true, bool $themesEnabled = true ) {
		$this->coreEnabled = $coreEnabled;
		$this->pluginsEnabled = $pluginsEnabled;
		$this->themesEnabled = $themesEnabled;
	}

	public function isEnabled() :bool {
		return $this->coreEnabled;
	}

	public function isScanEnabledPlugins() :bool {
		return $this->pluginsEnabled;
	}

	public function isScanEnabledThemes() :bool {
		return $this->themesEnabled;
	}
}

class OptimiserPlugins extends Plugins {
	private array $pluginFiles;

	public function __construct( array $pluginFiles ) {
		$this->pluginFiles = $pluginFiles;
	}

	public function getInstalledPluginFiles() :array {
		return $this->pluginFiles;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		return \in_array( $file, $this->pluginFiles, true ) ? new OptimiserPluginVo( $file ) : null;
	}
}

class OptimiserPluginVo extends WpPluginVo {
	public string $file;
	public string $Version = '1.0.0';

	public function __construct( string $file ) {
		$this->file = $file;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
				return 'plugin';
			case 'unique_id':
				return $this->file;
			case 'slug':
				return \dirname( $this->file );
			default:
				return $this->{$key} ?? null;
		}
	}
}

class OptimiserThemes extends Themes {
	private array $themes;

	public function __construct( array $themes ) {
		$this->themes = $themes;
	}

	public function getThemes() :array {
		return \array_map(
			static fn( string $stylesheet ) => new class( $stylesheet ) {
				private string $stylesheet;

				public function __construct( string $stylesheet ) {
					$this->stylesheet = $stylesheet;
				}

				public function get_stylesheet() :string {
					return $this->stylesheet;
				}
			},
			$this->themes
		);
	}

	public function getThemeAsVo( string $stylesheet, bool $reload = false ) :?WpThemeVo {
		unset( $reload );
		return \in_array( $stylesheet, $this->themes, true ) ? new OptimiserThemeVo( $stylesheet ) : null;
	}
}

class OptimiserThemeVo extends WpThemeVo {
	public string $stylesheet;
	public string $Version = '1.0.0';

	public function __construct( string $stylesheet ) {
		$this->stylesheet = $stylesheet;
	}

	public function __get( string $key ) {
		switch ( $key ) {
			case 'asset_type':
				return 'theme';
			case 'unique_id':
			case 'slug':
				return $this->stylesheet;
			case 'is_child':
				return false;
			default:
				return $this->{$key} ?? null;
		}
	}
}
