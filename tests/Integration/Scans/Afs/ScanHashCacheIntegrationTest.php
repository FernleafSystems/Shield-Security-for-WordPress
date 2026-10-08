<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve,
	ScanHashCache
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\{
	HashesStorageDir,
	Store
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\PopulateScanItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	CompleteQueue,
	ProcessQueueItem,
	QueueItemVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\{
	FileScanner,
	Processing\AssetTrustState,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard\Scan\Support\AfsAssetChangeIntegrationSupport;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDirHandler;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class ScanHashCacheIntegrationTest extends ShieldIntegrationTestCase {

	use TempDirLifecycleTrait;
	use AfsAssetChangeIntegrationSupport;

	private $originalCacheDir;
	private array $options;
	private string $root;
	private string $path;
	private WpPluginVo $plugin;
	private array $requests = [];
	private array $hashes = [];
	private ?string $httpFailure = null;
	private $httpFilter;

	public function set_up() {
		parent::set_up();
		foreach ( [ 'scans', 'scan_items', 'scan_results', 'scan_result_items', 'scan_result_item_meta', 'malware' ] as $db ) {
			$this->requireDb( $db );
		}
		$con = $this->requireController();
		$this->options = $this->snapshotSelectedOptions( [ 'enable_core_file_integrity_scan', 'file_scan_areas', 'is_scan_cron' ] );
		$this->enablePremiumCapabilities( [ 'scan_pluginsthemes_local' ] );
		$con->opts->optSet( 'enable_core_file_integrity_scan', 'Y' )->optSet( 'file_scan_areas', [ 'plugins', 'themes', 'wp' ] );
		$this->originalCacheDir = $con->cache_dir_handler;
		$con->cache_dir_handler = new CacheDirHandler( '', $this->createTrackedTempDir( 'shield-scan-hashes-integration-' ) );
		$this->root = $con->cache_dir_handler->dir();
		$this->assertNotSame( '', $this->root );
		$dir = $this->createTrackedTempDir( 'shield-postmark-', WP_PLUGIN_DIR );
		$this->path = $dir.'/postmark.php';
		$this->writePlugin( '1.20.0' );
		$this->plugin = Services::WpPlugins()->getPluginAsVo( \basename( $dir ).'/postmark.php', true );
		$this->hashes = [ 'postmark.php' => [ \str_repeat( 'a', 40 ), \sha1_file( $this->path ) ] ];
		Services::WpGeneral()->setTransient( 'apto-wphashes-api-available-routes', '#^cshashes$#' );
		$this->httpFilter = function ( $response, array $args, string $url ) {
			if ( \strpos( $url, '/apto-wphashes/' ) === false ) {
				return $response;
			}
			$crowd = \strpos( $url, '/cshashes/' ) !== false;
			if ( $crowd ) {
				$this->requests[] = [ 'url' => $url, 'timeout' => $args[ 'timeout' ] ];
				if ( $this->httpFailure === 'transport' ) {
					return new \WP_Error( 'http_request_failed', 'Simulated crowd-sourced request failure.' );
				}
			}
			return [
				'body' => \json_encode( $crowd ? [ 'hashes' => $this->hashes ] : [] ),
				'headers' => [], 'cookies' => [], 'filename' => null,
				'response' => [ 'code' => $crowd && $this->httpFailure === 'http' ? 503 : 200, 'message' => 'Fixture response' ],
			];
		};
		\add_filter( 'pre_http_request', $this->httpFilter, 10, 3 );
		$this->resetHashState();
		$con->comps->asset_coordinator->deleteState();
		$this->writeSnapshot();
	}

	public function tear_down() {
		\remove_filter( 'pre_http_request', $this->httpFilter, 10 );
		$this->requireController()->comps->asset_coordinator->deleteState();
		$this->requireController()->cache_dir_handler = $this->originalCacheDir;
		$this->restoreSelectedOptions( $this->options );
		$this->resetHashState();
		$this->cleanupTrackedTempDirs();
		\wp_clean_plugins_cache( true );
		\wp_clean_themes_cache( true );
		parent::tear_down();
	}

	public function test_full_scan_accepts_second_hash_and_results_recheck_agrees_on_local_plan() :void {
		$con = $this->requireController();
		$this->assertFalse( $con->caps->canScanPluginsThemesRemote() );
		$action = $this->fullAction();
		$action->asset_snapshot_eligibility = $con->comps->asset_coordinator
			->prepareFullScanSnapshotEligibility( [ $this->plugin ], static function () :void {} );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 10, $this->requests[ 0 ][ 'timeout' ] );
		$state = new AssetTrustState( $action );
		$context = $state->resolveAssetContext( $this->path );
		$verification = $state->verifyAssetContext( $this->path, $context );
		$this->assertTrue( $verification->verified );
		$this->assertTrue( $verification->trustedSource );
		$this->assertNull( ( new FileScanner() )->setScanActionVO( $action )->scan( $this->path ) );
		$this->assertCount( 1, $this->requests );

		$scanID = $this->insertAfsScan( 'plugin', $this->plugin->file, [ ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY ] );
		$scenario = [ 'meta' => [ 'is_in_plugin' => 1, 'is_checksumfail' => 1, 'ptg_slug' => $this->plugin->file ] ];
		$finding = $this->seedAfsFinding( $scanID, $scenario, $this->path );
		$item = ( new RetrieveItems() )->byID( $finding[ 'result_item_id' ] );
		$this->assertTrue( $con->comps->scans->AFS()->cleanStaleResultItem( $item ) );
		$stored = $con->db_con->scan_result_items->getQuerySelector()->byId( $finding[ 'result_item_id' ] );
		$this->assertSame( 'repaired', $stored->resolution_reason );
		$this->assertCount( 2, $this->requests );
	}

	/** @dataProvider provideSnapshotTrust */
	public function test_api_miss_is_quiet_and_preserves_existing_failure_result( bool $trusted ) :void {
		$this->writeSnapshot( $trusted );
		$this->hashes = [];
		$log = $this->createTrackedTempFile( 'shield-scan-hash-errors-', '.log' );
		$previousLog = \ini_set( 'error_log', $log );
		$action = $this->fullAction();
		try {
			$action->asset_snapshot_eligibility = $this->requireController()->comps->asset_coordinator
				->prepareFullScanSnapshotEligibility( [ $this->plugin ], static function () :void {} );
		}
		finally {
			\ini_set( 'error_log', $previousLog );
		}
		$this->assertSame( '', \file_get_contents( $log ) );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveCurrentContext( $this->path );
		$data = ( new ScanHashCache() )->read( $context );
		$this->assertSame( 'snapshot', $data[ 'meta' ][ 'source' ] );
		$this->assertSame( $trusted, $data[ 'meta' ][ 'trusted' ] );
		$this->assertEquals( $resolver->verifyStoredContext( $this->path, $context ), $resolver->verifyScanContext( $this->path, $context ) );
		$withCache = ( new FileScanner() )->setScanActionVO( $action )->scan( $this->path );
		$this->assertNotNull( $withCache );
		( new ScanHashCache() )->deleteDirectory();
		$withoutCache = ( new FileScanner() )->setScanActionVO( $action )->scan( $this->path );
		$this->assertEquals( $withoutCache, $withCache );
		$this->assertCount( 1, $this->requests );
	}

	public static function provideSnapshotTrust() :array {
		return [ [ false ], [ true ] ];
	}

	/** @dataProvider provideHttpFailures */
	public function test_real_request_error_is_logged_once_and_snapshot_copy_is_written( string $failure ) :void {
		$this->httpFailure = $failure;
		$log = $this->createTrackedTempFile( 'shield-scan-hash-request-errors-', '.log' );
		$previousLog = \ini_set( 'error_log', $log );
		try {
			( new ScanHashCache() )->fill( $this->plugin );
		}
		finally {
			\ini_set( 'error_log', $previousLog );
		}
		$this->assertSame( 1, \substr_count( \file_get_contents( $log ), 'Shield scan hash cache fill failed:' ), \file_get_contents( $log ) );
		$context = ( new AssetTrustResolver() )->resolveCurrentContext( $this->path );
		$this->assertSame( 'snapshot', ( new ScanHashCache() )->read( $context )[ 'meta' ][ 'source' ] );
		$this->assertCount( 1, $this->requests );
	}

	public static function provideHttpFailures() :array {
		return [ [ 'transport' ], [ 'http' ] ];
	}

	public function test_trusted_crowd_hash_skips_malware_but_untrusted_snapshot_copy_keeps_malware_scanning() :void {
		$con = $this->requireController();
		$this->enablePremiumCapabilities( [ 'scan_pluginsthemes_local', 'scan_malware_local' ] );
		$con->opts->optSet( 'file_scan_areas', [ 'themes', 'malware_php' ] );
		$this->assertTrue( $con->comps->scans->AFS()->isEnabledMalwareScanPHP() );
		$signature = 'SHIELD_SCAN_HASH_TEST_MALWARE';
		$dir = $this->createTrackedTempDir( 'shield-scan-hash-malware-theme-', \realpath( WP_CONTENT_DIR.'/themes' ) );
		\file_put_contents( $dir.'/style.css', "/*\nTheme Name: Scan hash malware fixture\nVersion: 1.0.0\n*/\n" );
		$path = $dir.'/index.php';
		\file_put_contents( $path, '<?php // '.$signature );
		\wp_clean_themes_cache( true );
		$theme = Services::WpThemes()->getThemeAsVo( \basename( $dir ), true );
		$path = $theme->getInstallDir().'index.php';
		$hash = \sha1_file( $path );
		( new Store( $theme, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( [ 'index.php' => $hash ] )
			->setSnapMeta( [ 'unique_id' => $theme->stylesheet, 'version' => $theme->Version, 'live_hashes' => false ] )
			->save();
		$action = $this->fullAction();
		$action->coverage_families = [ ScanActionVO::COVERAGE_FAMILY_THEME_INTEGRITY, ScanActionVO::COVERAGE_FAMILY_MALWARE ];
		$action->patterns_raw = [ $signature ];
		$action->patterns_iraw = [];
		$action->patterns_regex = [];
		$action->patterns_functions = [];
		$action->patterns_keywords = [];
		$this->hashes = [ 'index.php' => [ $hash ] ];
		$action->asset_snapshot_eligibility = $con->comps->asset_coordinator
			->prepareFullScanSnapshotEligibility( [ $theme ], static function () :void {} );
		$resolver = new AssetTrustResolver();
		$this->assertTrue( $resolver->verifyScanContext( $path, $resolver->resolveCurrentContext( $path ) )->trustedSource );
		$this->assertNull( ( new FileScanner() )->setScanActionVO( $action )->scan( $path ) );

		$this->hashes = [];
		$action->asset_snapshot_eligibility = $con->comps->asset_coordinator
			->prepareFullScanSnapshotEligibility( [ $theme ], static function () :void {} );
		$resolver = new AssetTrustResolver();
		$verification = $resolver->verifyScanContext( $path, $resolver->resolveCurrentContext( $path ) );
		$this->assertTrue( $verification->verified );
		$this->assertFalse( $verification->trustedSource );
		$result = ( new FileScanner() )->setScanActionVO( $action )->scan( $path );
		$this->assertNotNull( $result );
		$this->assertTrue( $result->is_mal );
		$this->assertFalse( $result->is_unrecognised );
		$this->assertFalse( $result->is_checksumfail );
		$this->assertCount( 2, $this->requests );
	}

	public function test_file_present_only_in_crowd_cache_is_not_unrecognised() :void {
		$path = \dirname( $this->path ).'/extra.php';
		\file_put_contents( $path, '<?php // verified crowd copy absent from stored snapshot' );
		$this->hashes = [ 'extra.php' => [ \sha1_file( $path ) ] ];
		$action = $this->fullAction();
		$action->asset_snapshot_eligibility = $this->requireController()->comps->asset_coordinator
			->prepareFullScanSnapshotEligibility( [ $this->plugin ], static function () :void {} );
		$resolver = new AssetTrustResolver();
		$context = $resolver->resolveCurrentContext( $path );
		$this->assertFalse( $resolver->verifyStoredContext( $path, $context )->recognisedInSnapshot );
		$this->assertTrue( $resolver->verifyScanContext( $path, $context )->verified );
		$this->assertNull( ( new FileScanner() )->setScanActionVO( $action )->scan( $path ) );
		$this->assertCount( 1, $this->requests );
	}

	/** @dataProvider provideScopes */
	public function test_scoped_preflight_uses_installed_asset_and_core_fills_none( string $scope ) :void {
		$scan = $this->newScan( $scope, $scope === 'plugin' ? $this->plugin->file : 'core' );
		( new PopulateScanItems() )->setRecord( $scan )->setScanController( $this->requireController()->comps->scans->AFS() )->run();
		$files = \glob( $this->root.'/scan-hashes/*.json' ) ?: [];
		$this->assertCount( $scope === 'plugin' ? 1 : 0, $files );
		$this->assertCount( $scope === 'plugin' ? 1 : 0, $this->requests );
		if ( $scope === 'plugin' ) {
			$data = \json_decode( \file_get_contents( $files[ 0 ] ), true );
			$this->assertSame( $this->plugin->file, $data[ 'meta' ][ 'key' ] );
			$this->assertSame( '1.20.0', $data[ 'meta' ][ 'version' ] );
		}
	}

	public static function provideScopes() :array {
		return [ [ 'plugin' ], [ 'core' ] ];
	}

	public function test_theme_scoped_preflight_fills_current_version_without_mutating_snapshot() :void {
		$dir = $this->createTrackedTempDir( 'shield-scan-hash-theme-', \realpath( WP_CONTENT_DIR.'/themes' ) );
		\file_put_contents( $dir.'/style.css', "/*\nTheme Name: Scan hash theme\nVersion: 3.2.0\n*/\n" );
		\file_put_contents( $dir.'/index.php', '<?php // original theme copy' );
		\wp_clean_themes_cache( true );
		$theme = Services::WpThemes()->getThemeAsVo( \basename( $dir ), true );
		$this->hashes = [ 'index.php' => [ \str_repeat( 'b', 40 ), \sha1_file( $dir.'/index.php' ) ] ];
		$store = ( new Store( $theme, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( [ 'index.php' => $this->hashes[ 'index.php' ][ 0 ] ] )
			->setSnapMeta( [ 'unique_id' => $theme->stylesheet, 'version' => $theme->Version, 'live_hashes' => true ] );
		$store->save();
		$paths = [ $store->getSnapStorePath(), $store->getSnapStoreMetaPath() ];
		$before = \array_map( 'file_get_contents', $paths );
		$scan = $this->newScan( 'theme', $theme->stylesheet );

		( new PopulateScanItems() )->setRecord( $scan )->setScanController( $this->requireController()->comps->scans->AFS() )->run();

		$files = \glob( $this->root.'/scan-hashes/*.json' ) ?: [];
		$this->assertCount( 1, $files );
		$this->assertCount( 1, $this->requests );
		$data = \json_decode( \file_get_contents( $files[ 0 ] ), true );
		$this->assertSame( 'theme', $data[ 'meta' ][ 'type' ] );
		$this->assertSame( $theme->stylesheet, $data[ 'meta' ][ 'key' ] );
		$this->assertSame( '3.2.0', $data[ 'meta' ][ 'version' ] );
		$this->assertSame( $before, \array_map( 'file_get_contents', $paths ) );
		$action = new ScanActionVO();
		$action->scope_type = 'theme';
		$action->scope_key = $theme->stylesheet;
		$state = new AssetTrustState( $action );
		$path = $theme->getInstallDir().'index.php';
		$context = $state->resolveAssetContext( $path );
		$this->assertNotNull( $context );
		$this->assertTrue( $state->verifyAssetContext( $path, $context )->trustedSource );
		$this->assertCount( 1, $this->requests );
	}

	/** @dataProvider provideActiveStatuses */
	public function test_initialise_preserves_cache_only_when_another_afs_is_active( ?string $otherStatus ) :void {
		$cache = new ScanHashCache();
		$cache->fill( $this->plugin );
		$marker = $this->root.'/scan-hashes/previous-scan.json';
		\file_put_contents( $marker, '{}' );
		$current = $this->newScan( 'plugin', $this->plugin->file );
		if ( $otherStatus !== null ) {
			$other = $this->newScan( 'plugin', 'other/plugin.php' );
			$this->requireController()->db_con->scans->getQueryUpdater()->updateById( $other->id, [ 'status' => $otherStatus ] );
		}
		( new PopulateScanItems() )->setRecord( $current )->setScanController( $this->requireController()->comps->scans->AFS() )->run();
		$this->assertSame( $otherStatus !== null, \is_file( $marker ) );
		$this->assertCount( 2, $this->requests );
	}

	public static function provideActiveStatuses() :array {
		return [ [ null ], [ ScanStatus::QUEUED ], [ ScanStatus::BUILDING ], [ ScanStatus::BUILT ], [ ScanStatus::RUNNING ] ];
	}

	public function test_queue_completion_and_hourly_cleanup_leave_other_scan_directories_untouched() :void {
		$con = $this->requireController();
		$cache = new ScanHashCache();
		$cache->fill( $this->plugin );
		$patterns = $con->cache_dir_handler->buildSubDir( 'scans' ).'/untouched.txt';
		\file_put_contents( $patterns, 'malware-pattern-cache' );
		$active = $this->newScan( 'plugin', $this->plugin->file );
		$con->comps->asset_coordinator->runScanHashCacheMaintenance();
		$this->assertDirectoryExists( $this->root.'/scan-hashes' );
		$con->db_con->scans->getQueryUpdater()->updateById( $active->id, [ 'status' => ScanStatus::FAILED, 'finished_at' => Services::Request()->ts() ] );
		$con->comps->asset_coordinator->runScanHashCacheMaintenance();
		$this->assertDirectoryDoesNotExist( $this->root.'/scan-hashes' );
		$cache->fill( $this->plugin );
		( new CompleteQueue() )->complete();
		$this->assertDirectoryDoesNotExist( $this->root.'/scan-hashes' );
		$this->assertSame( 'malware-pattern-cache', \file_get_contents( $patterns ) );
	}

	public function test_upgrade_between_preflight_and_batch_keeps_existing_incomplete_and_followup_behaviour() :void {
		$con = $this->requireController();
		$action = $this->fullAction();
		$action->asset_snapshot_eligibility = $con->comps->asset_coordinator
			->prepareFullScanSnapshotEligibility( [ $this->plugin ], static function () :void {} );
		$scan = $this->newScan( 'full', '' );
		$scan->meta = $action->getRawData();
		$con->db_con->scans->getQueryUpdater()->updateById( $scan->id, [ 'status' => ScanStatus::BUILT, 'meta' => $scan->getRawData()[ 'meta' ] ] );
		$record = $con->db_con->scan_items->getRecord();
		$record->scan_ref = $scan->id;
		$record->items = [ \base64_encode( $this->path ) ];
		$record->item_count = 1;
		$this->assertTrue( $con->db_con->scan_items->getQueryInserter()->insert( $record ) );
		$queueID = (int)$GLOBALS[ 'wpdb' ]->insert_id;
		$this->writePlugin( '1.20.1' );
		AssetTrustResolver::resetMemoization();
		$patterns = $con->cache_dir_handler->buildSubDir( 'scans' ).'/malcache_patterns_v2.txt';
		Services::WpFs()->putFileContent( $patterns, \json_encode( [ 'raw' => [], 're' => [], 'iraw' => [], 'functions' => [], 'keywords' => [] ] ), true );
		$item = ( new QueueItemVO() )->applyFromArray( [
			'scan_id' => $scan->id, 'qitem_id' => $queueID, 'scan' => 'afs', 'scope_type' => 'full', 'scope_key' => '',
			'meta' => $action->getRawData(), 'items' => [ \base64_encode( $this->path ) ], 'attempts' => 0,
		] );
		( new ProcessQueueItem() )->run( $item );
		$stored = $con->db_con->scans->getQuerySelector()->byId( $scan->id );
		$this->assertSame( [ $this->plugin->file ], $stored->meta[ 'asset_comparison_incomplete' ][ 'plugin' ] );
		$key = $con->prefix( 'asset_coordinator_state' );
		$state = \is_multisite() ? \get_site_option( $key ) : \get_option( $key );
		$this->assertArrayHasKey( $this->plugin->file, $state[ 'assets' ][ 'plugin' ] );
		$this->assertSame( 0, $this->countAfsResultItemsForPath( $this->path ) );
		$this->assertCount( 1, $this->requests );
	}

	private function newScan( string $scope, string $key ) :Record {
		$dbh = $this->requireController()->db_con->scans;
		$scan = $dbh->getRecord();
		$scan->scan = 'afs';
		$scan->scope_type = $scope;
		$scan->scope_key = $key;
		$scan->status = ScanStatus::BUILDING;
		$scan->run_trigger = 'manual';
		$this->assertTrue( $dbh->getQueryInserter()->insert( $scan ) );
		$scan->id = (int)$GLOBALS[ 'wpdb' ]->insert_id;
		return $scan;
	}

	private function fullAction() :ScanActionVO {
		$action = new ScanActionVO();
		$action->scan = 'afs';
		$action->scope_type = 'full';
		$action->file_exts = [ 'php' ];
		$action->max_file_size = 16*1024*1024;
		$action->coverage_families = [ ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY ];
		return $action;
	}

	private function writePlugin( string $version ) :void {
		\file_put_contents( $this->path, "<?php\n/*\nPlugin Name: Scan hash fixture\nVersion: ".$version."\n*/\n// original Postmark copy\n" );
		\wp_clean_plugins_cache( true );
	}

	private function writeSnapshot( bool $trusted = true ) :void {
		( new Store( $this->plugin, true ) )
			->setWorkingDir( ( new HashesStorageDir() )->getTempDir() )
			->setSnapData( [ 'postmark.php' => $this->hashes[ 'postmark.php' ][ 0 ] ] )
			->setSnapMeta( [ 'unique_id' => $this->plugin->file, 'version' => $this->plugin->Version, 'live_hashes' => $trusted ] )
			->save();
	}

	private function resetHashState() :void {
		ScanHashCache::resetMemoization();
		Retrieve::resetMemoization();
		AssetTrustResolver::resetMemoization();
		foreach ( [ 'dir', 'rootDir' ] as $name ) {
			$property = new \ReflectionProperty( HashesStorageDir::class, $name );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
	}
}
