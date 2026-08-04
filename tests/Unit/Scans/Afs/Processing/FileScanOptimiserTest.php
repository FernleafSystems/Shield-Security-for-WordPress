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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\{
	FileScanOptimiser,
	TrustedFileContext
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	WrittenFixtureFiles
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

	use TempDirLifecycleTrait;
	use WrittenFixtureFiles;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		AssetTrustResolver::resetMemoization();
		Retrieve::resetMemoization();
		$this->resetHashesStorageDir();
		OptimiserPlugins::$installedPluginFilesCalls = 0;
		OptimiserPlugins::$getPluginAsVoCalls = 0;
		OptimiserThemes::$getThemesCalls = 0;
		OptimiserThemes::$getThemeAsVoCalls = 0;
		Functions\when( 'path_join' )->alias( fn( string $a, string $b ) :string => $this->normalisePath( \rtrim( $a, '/\\' ).'/'.\ltrim( $b, '/\\' ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => \json_encode( $data ) );
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalisePath( $path ) );
		Functions\when( 'get_theme_root' )->alias( fn() :string => $this->normalisePath( WP_CONTENT_DIR.'/themes' ) );
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalisePath( $path ), '/' ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		AssetTrustResolver::resetMemoization();
		Retrieve::resetMemoization();
		$this->resetHashesStorageDir();
		PluginControllerInstaller::reset();
		$this->removeWrittenFixtureFiles();
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_missing_cache_dir_fails_open() :void {
		$cacheDir = $this->normalisePath( $this->createTrackedTempPath( 'shield-missing-cache-' ) );
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

	public function test_known_valid_record_probe_returns_false_when_cache_root_is_missing() :void {
		$cacheDir = $this->normalisePath( $this->createTrackedTempPath( 'shield-missing-cache-' ) );
		$this->installEnvironment( $cacheDir, false );

		$this->assertFalse( ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
	}

	public function test_known_valid_record_probe_does_not_create_optimiser_cache_dir() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment( $cacheDir );

		$this->assertFalse( ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
		$this->assertFileDoesNotExist( $this->normalisePath( $cacheDir.'/afs-file-optimiser' ) );
	}

	public function test_known_valid_record_probe_returns_false_without_known_valid_dir() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		@\mkdir( $this->normalisePath( $cacheDir.'/afs-file-optimiser' ), 0755, true );
		$this->installEnvironment( $cacheDir );

		$this->assertFalse( ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
	}

	public function test_known_valid_record_probe_returns_false_without_jsonl_files() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$dir = $this->makeKnownValidRecordDir( $cacheDir );
		\file_put_contents( $dir.'/not-records.txt', 'ignored' );
		$this->installEnvironment( $cacheDir );

		$this->assertFalse( ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
	}

	public function test_known_valid_record_probe_returns_true_for_recorded_known_valid_file() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment( $cacheDir );
		$optimiser = new FileScanOptimiser();

		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		$this->assertTrue( $optimiser->hasKnownValidFileRecords() );
	}

	public function test_known_valid_record_probe_treats_malformed_jsonl_presence_as_work() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$dir = $this->makeKnownValidRecordDir( $cacheDir );
		\file_put_contents( $dir.'/aa.jsonl', "not-json\n" );
		$this->installEnvironment( $cacheDir );

		$this->assertTrue( ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
	}

	public function test_known_valid_record_probe_ignores_malware_clean_records() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-content/uploads/clean.php', '<?php clean();' );
		$this->installEnvironment( $cacheDir );

		( new FileScanOptimiser() )->recordCleanMalwareVerdict( $path, $this->newAction( [ 'bad_token' ] ) );

		$this->assertFalse( ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
	}

	public function test_known_valid_record_probe_fails_open_without_existing_root_probe() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[],
			[],
			null,
			true,
			null,
			null,
			new class( $cacheDir ) {
				private string $dir;

				public function __construct( string $dir ) {
					$this->dir = $dir;
				}

				public function exists() :bool {
					return \is_dir( $this->dir ) && \is_writable( $this->dir );
				}

				public function buildSubDir( string $subDir ) :string {
					$path = $this->dir.'/'.$subDir;
					return ( \is_dir( $path ) || @\mkdir( $path, 0755, true ) ) ? $path : '';
				}
			}
		);
		( new FileScanOptimiser() )->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		$this->assertFalse( ( new FileScanOptimiser() )->hasKnownValidFileRecords() );
	}

	public function test_known_valid_shard_dir_is_created_through_wp_filesystem_service() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$fs = new OptimiserFs();
		$this->installEnvironment( $cacheDir, true, '6.5.0', [], [], null, true, null, $fs );

		( new FileScanOptimiser() )->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		$this->assertContains(
			$this->normalisePath( $cacheDir.'/afs-file-optimiser/known-valid' ),
			$fs->mkdirCalls()
		);
	}

	public function test_malware_clean_shard_dir_is_created_through_wp_filesystem_service() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-content/uploads/clean.php', '<?php clean();' );
		$fs = new OptimiserFs();
		$this->installEnvironment( $cacheDir, true, '6.5.0', [], [], null, true, null, $fs );

		( new FileScanOptimiser() )->recordCleanMalwareVerdict( $path, $this->newAction( [ 'bad_token' ] ) );

		$this->assertContains(
			$this->normalisePath( $cacheDir.'/afs-file-optimiser/malware-clean' ),
			$fs->mkdirCalls()
		);
	}

	public function test_shard_dir_service_mkdir_failure_fails_open_without_cache_hit() :void {
		$cacheDir = $this->makeTempDir( 'cache' );
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$shardDir = $this->normalisePath( $cacheDir.'/afs-file-optimiser/known-valid' );
		$fs = new OptimiserFs();
		$fs->failMkdirFor( $shardDir );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [], [], null, true, null, $fs );
		$optimiser = new FileScanOptimiser();

		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		$this->assertContains( $shardDir, $fs->mkdirCalls() );
		$this->assertFileDoesNotExist( $shardDir );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	public function test_exact_known_valid_context_hit_skips_file() :void {
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();

		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	public function test_reconstructed_null_extensions_safely_disable_known_valid_skip() :void {
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );
		$action = ( new ScanActionVO() )->applyFromArray( [ 'file_exts' => null ] );

		$this->assertSame( [], $action->file_exts );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $action ) );
	}

	public function test_reconstructed_mixed_associative_extensions_preserve_known_valid_skip() :void {
		$path = $this->writeFile( ABSPATH.'wp-admin/core.php', '<?php clean();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/core.php' ) );
		$action = ( new ScanActionVO() )->applyFromArray( [
			'file_exts' => [ 'invalid' => 12, 'primary' => ' PHP ', 'duplicate' => 'php' ],
		] );

		$this->assertSame( [ 'php' ], $action->file_exts );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $action ) );
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
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'alpha/alpha.php', 'beta/beta.php' ]
		);
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( 'alpha/alpha.php' ), [
			'dup.php' => \md5_file( $alpha ),
		] );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( 'beta/beta.php' ), [
			'dup.php' => \md5_file( $beta ),
		] );
		$optimiser = new FileScanOptimiser();

		$optimiser->recordKnownValidFile( $alpha, new TrustedFileContext( 'plugin', 'alpha/alpha.php', '1.0.0', 'dup.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $alpha, $this->newAction() ) );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $beta, $this->newAction() ) );
	}

	public function test_known_valid_plugin_record_requires_current_published_snapshot() :void {
		$path = $this->writeFile( WP_PLUGIN_DIR.'/local/local.php', '<?php local();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'local/local.php' ]
		);
		$this->writeSnapshot( $cacheDir, new OptimiserPluginVo( 'local/local.php' ), [
			'local.php' => \md5_file( $path ),
		], false );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile(
			$path,
			new TrustedFileContext( 'plugin', 'local/local.php', '1.0.0', 'local.php' )
		);

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	/**
	 * @dataProvider provideIneligibleFullScanPluginSnapshots
	 */
	public function test_full_scan_known_valid_plugin_requires_exact_comparison_eligibility(
		?array $eligibility
	) :void {
		$pluginFile = 'full-gated/full-gated.php';
		$path = $this->writeFile( WP_PLUGIN_DIR.'/'.$pluginFile, '<?php full_gated();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [ $pluginFile ] );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( $pluginFile ), [
			'full-gated.php' => \md5_file( $path ),
		] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile(
			$path,
			new TrustedFileContext( 'plugin', $pluginFile, '1.0.0', 'full-gated.php' )
		);

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newFullScanAction( $eligibility ) ) );
	}

	public function test_full_scan_known_valid_plugin_accepts_exact_comparison_eligibility() :void {
		$pluginFile = 'full-eligible/full-eligible.php';
		$path = $this->writeFile( WP_PLUGIN_DIR.'/'.$pluginFile, '<?php full_eligible();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [ $pluginFile ] );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( $pluginFile ), [
			'full-eligible.php' => \md5_file( $path ),
		] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile(
			$path,
			new TrustedFileContext( 'plugin', $pluginFile, '1.0.0', 'full-eligible.php' )
		);
		$action = $this->newFullScanAction(
			$this->assetSnapshotEligibility( 'plugin', $pluginFile, '1.0.0', true )
		);

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $action ) );
	}

	public function test_targeted_known_valid_plugin_does_not_require_comparison_eligibility() :void {
		$pluginFile = 'targeted/targeted.php';
		$path = $this->writeFile( WP_PLUGIN_DIR.'/'.$pluginFile, '<?php targeted();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [ $pluginFile ] );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( $pluginFile ), [
			'targeted.php' => \md5_file( $path ),
		] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile(
			$path,
			new TrustedFileContext( 'plugin', $pluginFile, '1.0.0', 'targeted.php' )
		);

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	public function test_full_scan_known_valid_theme_requires_exact_comparison_eligibility() :void {
		$stylesheet = 'full-gated-theme';
		$path = $this->writeFile( WP_CONTENT_DIR.'/themes/'.$stylesheet.'/style.php', '<?php full_gated_theme();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [], [ $stylesheet ] );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserThemeVo( $stylesheet ), [
			'style.php' => \md5_file( $path ),
		] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile(
			$path,
			new TrustedFileContext( 'theme', $stylesheet, '1.0.0', 'style.php' )
		);

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newFullScanAction() ) );

		$action = $this->newFullScanAction(
			$this->assetSnapshotEligibility( 'theme', $stylesheet, '1.0.0', true )
		);
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $action ) );
	}

	public function test_full_scan_known_valid_core_does_not_require_asset_comparison_eligibility() :void {
		$path = $this->writeFile( ABSPATH.'wp-admin/full-core.php', '<?php full_core();' );
		$this->installEnvironment( $this->makeTempDir( 'cache' ) );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $path, $this->coreContext( 'wp-admin/full-core.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $this->newFullScanAction() ) );
	}

	public function test_known_valid_snapshot_verification_only_runs_for_exact_record_candidate() :void {
		$publishedNoRecord = $this->writeFile( WP_PLUGIN_DIR.'/published/no-record.php', '<?php published_no_record();' );
		$localNoRecord = $this->writeFile( WP_PLUGIN_DIR.'/local/no-record.php', '<?php local_no_record();' );
		$staleRecord = $this->writeFile( WP_PLUGIN_DIR.'/stale/stale.php', '<?php stale_one();' );
		$exactRecord = $this->writeFile( WP_PLUGIN_DIR.'/exact/exact.php', '<?php exact();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$fs = new OptimiserFs();
		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[
				'published/published.php',
				'local/local.php',
				'stale/stale.php',
				'exact/exact.php',
			],
			[],
			null,
			true,
			null,
			$fs
		);
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( 'published/published.php' ), [
			'no-record.php' => \md5_file( $publishedNoRecord ),
		] );
		$this->writeSnapshot( $cacheDir, new OptimiserPluginVo( 'local/local.php' ), [
			'no-record.php' => \md5_file( $localNoRecord ),
		], false );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( 'stale/stale.php' ), [
			'stale.php' => \md5_file( $staleRecord ),
		] );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( 'exact/exact.php' ), [
			'exact.php' => \md5_file( $exactRecord ),
		] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile(
			$staleRecord,
			new TrustedFileContext( 'plugin', 'stale/stale.php', '1.0.0', 'stale.php' )
		);
		$optimiser->recordKnownValidFile(
			$exactRecord,
			new TrustedFileContext( 'plugin', 'exact/exact.php', '1.0.0', 'exact.php' )
		);
		\file_put_contents( $staleRecord, '<?php stale_two();' );
		$fs->resetReadCalls();

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $publishedNoRecord, $this->newAction() ) );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $localNoRecord, $this->newAction() ) );
		$this->assertFalse( $optimiser->canSkipKnownValidFile( $staleRecord, $this->newAction() ) );
		$this->assertSame( [], $fs->isFileCalls() );
		$this->assertSame( [], $fs->getFileContentCalls() );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $exactRecord, $this->newAction() ) );
		$this->assertSame( [ $this->normalisePath( $exactRecord ) ], $fs->isFileCalls() );
		$this->assertNotEmpty( $fs->getFileContentCalls() );
	}

	public function test_known_valid_size_limit_precedes_context_and_snapshot_work() :void {
		$atLimit = $this->writeFile( WP_PLUGIN_DIR.'/limit/at-limit.php', \str_repeat( 'a', 16 ) );
		$overLimit = $this->writeFile( WP_PLUGIN_DIR.'/limit/over-limit.php', \str_repeat( 'b', 17 ) );
		$cacheDir = $this->makeTempDir( 'cache' );
		$fs = new OptimiserFs();
		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'limit/limit.php' ],
			[],
			null,
			true,
			null,
			$fs
		);
		$action = $this->newAction();
		$action->max_file_size = 16;
		$fs->resetReadCalls();
		OptimiserPlugins::$installedPluginFilesCalls = 0;
		OptimiserPlugins::$getPluginAsVoCalls = 0;

		$this->assertFalse( ( new FileScanOptimiser() )->canSkipKnownValidFile( $atLimit, $action ) );
		$this->assertFalse( ( new FileScanOptimiser() )->canSkipKnownValidFile( $overLimit, $action ) );
		$this->assertSame( 0, OptimiserPlugins::$installedPluginFilesCalls );
		$this->assertSame( 0, OptimiserPlugins::$getPluginAsVoCalls );
		$this->assertSame( [], $fs->isFileCalls() );
		$this->assertSame( [], $fs->getFileContentCalls() );
	}

	public function test_disabled_file_change_scan_areas_still_skip_known_valid_asset_files() :void {
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
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( 'alpha/alpha.php' ), [
			'dup.php' => \md5_file( $plugin ),
		] );
		$this->writePublishedSnapshot( $cacheDir, new OptimiserThemeVo( 'clean' ), [
			'style.php' => \md5_file( $theme ),
		] );
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
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $plugin, $this->newAction() ) );

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
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $theme, $this->newAction() ) );
	}

	public function test_known_valid_plugin_context_reuses_asset_directory_resolution() :void {
		$first = $this->writeFile( WP_PLUGIN_DIR.'/alpha/one.php', '<?php one();' );
		$second = $this->writeFile( WP_PLUGIN_DIR.'/alpha/two.php', '<?php two();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[ 'alpha/alpha.php' ]
		);
		$this->writePublishedSnapshot( $cacheDir, new OptimiserPluginVo( 'alpha/alpha.php' ), [
			'one.php' => \md5_file( $first ),
			'two.php' => \md5_file( $second ),
		] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $first, new TrustedFileContext( 'plugin', 'alpha/alpha.php', '1.0.0', 'one.php' ) );
		$optimiser->recordKnownValidFile( $second, new TrustedFileContext( 'plugin', 'alpha/alpha.php', '1.0.0', 'two.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $first, $this->newAction() ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $second, $this->newAction() ) );
		$this->assertSame( 1, OptimiserPlugins::$installedPluginFilesCalls );
		$this->assertSame( 1, OptimiserPlugins::$getPluginAsVoCalls );
	}

	public function test_known_valid_theme_context_reuses_asset_directory_resolution() :void {
		$first = $this->writeFile( WP_CONTENT_DIR.'/themes/clean/one.php', '<?php one();' );
		$second = $this->writeFile( WP_CONTENT_DIR.'/themes/clean/two.php', '<?php two();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$this->installEnvironment(
			$cacheDir,
			true,
			'6.5.0',
			[],
			[ 'clean' ]
		);
		$this->writePublishedSnapshot( $cacheDir, new OptimiserThemeVo( 'clean' ), [
			'one.php' => \md5_file( $first ),
			'two.php' => \md5_file( $second ),
		] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $first, new TrustedFileContext( 'theme', 'clean', '1.0.0', 'one.php' ) );
		$optimiser->recordKnownValidFile( $second, new TrustedFileContext( 'theme', 'clean', '1.0.0', 'two.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $first, $this->newAction() ) );
		$this->assertTrue( $optimiser->canSkipKnownValidFile( $second, $this->newAction() ) );
		$this->assertSame( 1, OptimiserThemes::$getThemesCalls );
		$this->assertSame( 1, OptimiserThemes::$getThemeAsVoCalls );
	}

	public function test_known_valid_plugin_record_does_not_skip_after_same_version_snapshot_hash_replacement() :void {
		$path = $this->writeFile( WP_PLUGIN_DIR.'/alpha/one.php', '<?php original();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$asset = new OptimiserPluginVo( 'alpha/alpha.php' );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [ 'alpha/alpha.php' ] );
		$this->writePublishedSnapshot( $cacheDir, $asset, [ 'one.php' => \md5_file( $path ) ] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $path, new TrustedFileContext( 'plugin', 'alpha/alpha.php', '1.0.0', 'one.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );

		$this->writePublishedSnapshot( $cacheDir, $asset, [ 'one.php' => \md5( '<?php corrected();' ) ] );

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
	}

	public function test_known_valid_theme_record_does_not_skip_after_same_version_snapshot_path_removal() :void {
		$path = $this->writeFile( WP_CONTENT_DIR.'/themes/clean/one.php', '<?php original();' );
		$cacheDir = $this->makeTempDir( 'cache' );
		$asset = new OptimiserThemeVo( 'clean' );
		$this->installEnvironment( $cacheDir, true, '6.5.0', [], [ 'clean' ] );
		$this->writePublishedSnapshot( $cacheDir, $asset, [ 'one.php' => \md5_file( $path ) ] );
		$optimiser = new FileScanOptimiser();
		$optimiser->recordKnownValidFile( $path, new TrustedFileContext( 'theme', 'clean', '1.0.0', 'one.php' ) );

		$this->assertTrue( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );

		$this->writePublishedSnapshot( $cacheDir, $asset, [ 'other.php' => \md5( '<?php other();' ) ] );

		$this->assertFalse( $optimiser->canSkipKnownValidFile( $path, $this->newAction() ) );
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

	public function test_full_scan_malware_clean_verdict_is_independent_of_asset_comparison_eligibility() :void {
		$path = $this->writeFile( WP_PLUGIN_DIR.'/malware-cache/malware-cache.php', '<?php malware_cache();' );
		$this->installEnvironment(
			$this->makeTempDir( 'cache' ),
			true,
			'6.5.0',
			[ 'malware-cache/malware-cache.php' ]
		);
		$optimiser = new FileScanOptimiser();
		$action = $this->newFullScanAction();
		$action->patterns_raw = [ 'bad_token' ];

		$optimiser->recordCleanMalwareVerdict( $path, $action );

		$this->assertTrue( $optimiser->hasCleanMalwareVerdict( $path, $action ) );
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
		?OptimiserAfsComponent $afsComponent = null,
		?OptimiserFs $fs = null,
		?object $cacheDirHandler = null
	) :void {
		ServicesState::installItems( [
			'service_corefilehashes' => new OptimiserCoreHashes(),
			'service_request'        => $request ?? new OptimiserRequest( 1700000000 ),
			'service_wpfs'           => $fs ?? new OptimiserFs(),
			'service_wpgeneral'      => new OptimiserGeneral( $wpVersion ),
			'service_wpplugins'      => new OptimiserPlugins( $pluginFiles ),
			'service_wpthemes'       => new OptimiserThemes( $themes ),
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cache_dir_handler = $cacheDirHandler ?? new OptimiserCacheDir( $cacheDir, $cacheExists, $cacheBuildable );
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
		$action->max_file_size = ScanActionVO::DEFAULT_MAX_FILE_SIZE;
		$action->patterns_raw = $rawPatterns;
		$action->patterns_iraw = [];
		$action->patterns_regex = [];
		$action->patterns_functions = [];
		$action->patterns_keywords = [];
		return $action;
	}

	private function newFullScanAction( ?array $eligibility = null ) :ScanActionVO {
		$action = $this->newAction();
		$action->scope_type = 'full';
		if ( $eligibility !== null ) {
			$action->asset_snapshot_eligibility = $eligibility;
		}
		return $action;
	}

	private function assetSnapshotEligibility(
		string $assetType,
		string $assetKey,
		string $assetVersion,
		bool $comparisonEligible
	) :array {
		$eligibility = [
			'plugin' => [],
			'theme'  => [],
		];
		$eligibility[ $assetType ][ $assetKey ] = [
			'version'             => $assetVersion,
			'comparison_eligible' => $comparisonEligible,
		];
		return $eligibility;
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

	public static function provideIneligibleFullScanPluginSnapshots() :array {
		return [
			'absent map' => [ null ],
			'explicit false' => [ [
				'plugin' => [
					'full-gated/full-gated.php' => [
						'version'             => '1.0.0',
						'comparison_eligible' => false,
					],
				],
				'theme' => [],
			] ],
			'malformed map' => [ [
				'plugin' => [
					'full-gated/full-gated.php' => true,
				],
			] ],
			'wrong key' => [ [
				'plugin' => [
					'other/other.php' => [
						'version'             => '1.0.0',
						'comparison_eligible' => true,
					],
				],
				'theme' => [],
			] ],
			'wrong version' => [ [
				'plugin' => [
					'full-gated/full-gated.php' => [
						'version'             => '0.9.0',
						'comparison_eligible' => true,
					],
				],
				'theme' => [],
			] ],
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

	/**
	 * @param OptimiserPluginVo|OptimiserThemeVo $asset
	 */
	private function writePublishedSnapshot( string $cacheDir, $asset, array $hashes ) :void {
		$this->writeSnapshot( $cacheDir, $asset, $hashes, true );
	}

	/**
	 * @param OptimiserPluginVo|OptimiserThemeVo $asset
	 */
	private function writeSnapshot( string $cacheDir, $asset, array $hashes, bool $liveHashes ) :void {
		$hashDir = $this->normalisePath( $cacheDir.'/ptguard-aaaaaaaaaaaaaaaa' );
		if ( !\is_dir( $hashDir ) ) {
			@\mkdir( $hashDir, 0755, true );
		}
		( new Store( $asset, true ) )
			->setWorkingDir( $hashDir )
			->setSnapData( $hashes )
			->setSnapMeta( [
				'version'     => $asset->Version,
				'unique_id'   => $asset->asset_type === 'plugin' ? $asset->file : $asset->stylesheet,
				'live_hashes' => $liveHashes,
			] )
			->save();
		Retrieve::resetMemoization();
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

	private function writeFile( string $path, string $content ) :string {
		$path = $this->normalisePath( $path );
		if ( !\is_dir( \dirname( $path ) ) ) {
			@\mkdir( \dirname( $path ), 0755, true );
		}
		\file_put_contents( $path, $content );
		return $this->trackWrittenFixtureFile( $path );
	}

	private function makeTempDir( string $suffix ) :string {
		return $this->normalisePath( $this->createTrackedTempDir( 'shield-optimiser-'.$suffix.'-' ) );
	}

	private function makeKnownValidRecordDir( string $cacheDir ) :string {
		$dir = $this->normalisePath( $cacheDir.'/afs-file-optimiser/known-valid' );
		@\mkdir( $dir, 0755, true );
		return $dir;
	}

	private function normalisePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
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

	public function locateExistingDir() :string {
		return $this->exists() ? $this->dir : '';
	}

	public function buildSubDir( string $subDir ) :string {
		if ( !$this->exists() || !$this->buildable ) {
			return '';
		}
		$path = $this->dir.'/'.$subDir;
		return ( \is_dir( $path ) || @\mkdir( $path, 0755, true ) ) ? $path : '';
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
	private array $mkdirCalls = [];

	private array $mkdirFailures = [];

	private array $isFileCalls = [];

	private array $getFileContentCalls = [];

	public function mkdirCalls() :array {
		return $this->mkdirCalls;
	}

	public function isFileCalls() :array {
		return $this->isFileCalls;
	}

	public function getFileContentCalls() :array {
		return $this->getFileContentCalls;
	}

	public function resetReadCalls() :void {
		$this->isFileCalls = [];
		$this->getFileContentCalls = [];
	}

	public function failMkdirFor( string $path ) :void {
		$this->mkdirFailures[] = $this->normalisePath( $path );
	}

	public function mkdir( $path ) {
		$path = $this->normalisePath( (string)$path );
		$this->mkdirCalls[] = $path;
		if ( \in_array( $path, $this->mkdirFailures, true ) ) {
			return false;
		}
		return \is_dir( $path ) || @\mkdir( $path, 0755, true );
	}

	public function exists( $path ) :?bool {
		return \file_exists( $path );
	}

	public function isDir( string $path ) :bool {
		return \is_dir( $path );
	}

	public function isFile( $path ) :bool {
		$this->isFileCalls[] = $this->normalisePath( (string)$path );
		return \is_file( $path );
	}

	public function isAccessibleFile( string $file ) :bool {
		return \is_file( $file ) && \is_readable( $file );
	}

	public function getFileContent( $path, $uncompress = false ) {
		$this->getFileContentCalls[] = $this->normalisePath( (string)$path );
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
			@\mkdir( $dir, 0755, true );
		}
		return \file_put_contents( $path, $compress ? \gzdeflate( $contents ) : $contents ) !== false;
	}

	public function getModifiedTime( string $path ) :int {
		return (int)\filemtime( $path );
	}

	public function touch( $path, $time = null ) {
		return \touch( $path, $time ?? \time() );
	}

	public function isAbsPath( $path ) {
		return \preg_match( '#^([A-Z]:)?/#i', \str_replace( '\\', '/', (string)$path ) ) === 1;
	}

	private function normalisePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
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
	public static int $installedPluginFilesCalls = 0;

	public static int $getPluginAsVoCalls = 0;

	private array $pluginFiles;

	public function __construct( array $pluginFiles ) {
		$this->pluginFiles = $pluginFiles;
	}

	public function getInstalledPluginFiles() :array {
		self::$installedPluginFilesCalls++;
		return $this->pluginFiles;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $reload );
		self::$getPluginAsVoCalls++;
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
	public static int $getThemesCalls = 0;

	public static int $getThemeAsVoCalls = 0;

	private array $themes;

	public function __construct( array $themes ) {
		$this->themes = $themes;
	}

	public function getThemes() :array {
		self::$getThemesCalls++;
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
		self::$getThemeAsVoCalls++;
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
