<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\OptsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\FileLockerController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\CleanLockRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetPendingFileLockDisplays;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\{
	BuildFileFromFileKey,
	GetFileLockCandidateDisplays,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FileLockKeyApplicability;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class FileLockerOperationsIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];
	private array $tempPaths = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'file_locker' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'file_locker', 'filelocker_state', 'snapi_data' ] );
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		if ( static::con() !== null ) {
			\wp_clear_scheduled_hook( static::con()->prefix( 'create_file_locks' ) );
			static::con()->comps->file_locker->clearLocks();
		}
		foreach ( $this->tempPaths as $path ) {
			if ( \is_string( $path ) && $path !== '' && \file_exists( $path ) ) {
				@\unlink( $path );
			}
		}
		parent::tear_down();
	}

	public function test_clean_lock_records_deletes_rows_for_unselected_lock_types() :void {
		global $wpdb;
		$con = $this->requireController();
		$handler = $this->prepareFileLockerRuntime( [ 'wpconfig' ] );

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		TestDataFactory::insertFileLockRecord( 'root_index', ABSPATH.'index.php' );
		$con->comps->file_locker->clearLocks();
		$this->assertSame( 2, (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$handler->getTable()}" ) );

		( new CleanLockRecords() )->run();

		$reloadedHandler = RuntimeTestState::requireDbHandler( 'file_locker', true );
		$this->assertSame(
			[ 'wpconfig' ],
			$wpdb->get_col( "SELECT type FROM {$reloadedHandler->getTable()} ORDER BY id ASC" )
		);
	}

	public function test_purge_deletes_existing_file_lock_rows() :void {
		global $wpdb;
		$con = $this->requireController();
		$handler = $this->prepareFileLockerRuntime( [ 'wpconfig' ] );

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		$this->assertSame( 1, (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$handler->getTable()}" ) );

		$handler::GetTableReadyCache()->setReady( $handler->getTableSchema() );
		$this->assertTrue( $handler::GetTableReadyCache()->isReady( $handler->getTableSchema() ) );

		$con->comps->file_locker->purge();

		$this->assertFalse( $handler::GetTableReadyCache()->isReady( $handler->getTableSchema() ) );

		$reloadedHandler = RuntimeTestState::requireDbHandler( 'file_locker', true );
		$this->assertTrue( $reloadedHandler->tableExists() );
		$this->assertSame( 0, (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$reloadedHandler->getTable()}" ) );
	}

	public function test_run_analysis_keeps_file_locker_when_stored_abspath_has_dot_segment() :void {
		$con = $this->requireController();
		$this->requireFileLockerAnalysisRuntime();
		$this->prepareFileLockerRuntime( [ 'wpconfig', 'root_index' ] );

		$state = $con->comps->file_locker->getState();
		$state[ 'abspath' ] = $this->abspathWithDotSegment();
		$con->opts->optSet( 'filelocker_state', $state )->store();

		$this->runFileLockerAnalysis();

		$this->assertSame( [ 'wpconfig', 'root_index' ], $con->opts->optGet( 'file_locker' ) );
	}

	public function test_run_analysis_clears_file_locker_when_stored_abspath_is_genuinely_different() :void {
		$con = $this->requireController();
		$this->requireFileLockerAnalysisRuntime();
		$this->prepareFileLockerRuntime( [ 'wpconfig', 'root_index' ] );

		$missingAbsPath = $this->missingDifferentAbsPath();
		$this->assertFalse( \realpath( $missingAbsPath ) );

		$state = $con->comps->file_locker->getState();
		$state[ 'abspath' ] = $missingAbsPath;
		$con->opts->optSet( 'filelocker_state', $state )->store();

		$this->runFileLockerAnalysis();

		$this->assertSame( [], $con->opts->optGet( 'file_locker' ) );
		RuntimeTestState::requireDbHandler( 'file_locker', true );
	}

	public function test_run_analysis_handles_non_string_stored_abspath_without_disabling_file_locker() :void {
		$con = $this->requireController();
		$this->requireFileLockerAnalysisRuntime();
		$this->prepareFileLockerRuntime( [ 'wpconfig', 'root_index' ] );

		$state = $con->comps->file_locker->getState();
		$state[ 'abspath' ] = false;
		$con->opts->optSet( 'filelocker_state', $state )->store();

		$this->runFileLockerAnalysis();

		$this->assertSame( [ 'wpconfig', 'root_index' ], $con->opts->optGet( 'file_locker' ) );
	}

	public function test_reassess_locks_now_clears_stale_problem_state_without_touching_cooldown() :void {
		$con = $this->requireController();
		$handler = $this->prepareFileLockerRuntime( [ 'wpconfig' ] );

		$tempPath = \tempnam( \sys_get_temp_dir(), 'shield-file-locker-' );
		$this->assertIsString( $tempPath );
		$this->tempPaths[] = $tempPath;
		$this->assertTrue( Services::WpFs()->putFileContent( $tempPath, 'original-file-content' ) );

		$record = $handler->getRecord();
		$record->type = 'wpconfig';
		$record->path = $tempPath;
		$record->hash_original = \sha1( 'original-file-content' );
		$record->hash_current = \sha1( 'stale-different-content' );
		$record->public_key_id = 1;
		$record->cipher = 'aes-256-cbc';
		$record->content = 'encrypted-content-wpconfig';
		$record->detected_at = \time() - 60;
		$handler->getQueryInserter()->insert( $record );

		global $wpdb;
		$recordId = (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		$this->assertGreaterThan( 0, $recordId );

		$state = $con->comps->file_locker->getState();
		$state[ 'last_analysis_started_at' ] = 123456;
		$con->opts->optSet( 'filelocker_state', $state )->store();
		$con->comps->file_locker->clearLocks();

		$this->assertCount( 1, ( new LoadFileLocks() )->withProblems() );

		$con->comps->file_locker->reassessLocksNow();

		/** @var object $updated */
		$updated = $handler->getQuerySelector()->byId( $recordId );
		$this->assertSame( 0, (int)$updated->detected_at );
		$this->assertSame( '', (string)$updated->hash_current );
		$this->assertCount( 0, ( new LoadFileLocks() )->withProblems() );
		$this->assertSame( 123456, (int)$con->comps->file_locker->getState()[ 'last_analysis_started_at' ] );
	}

	public function test_get_pending_file_lock_displays_returns_only_outstanding_selected_files() :void {
		$this->prepareFileLockerRuntime( [ 'wpconfig', 'root_index' ] );

		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		static::con()->comps->file_locker->clearLocks();

		$pendingDisplays = ( new GetPendingFileLockDisplays() )->run();

		$this->assertCount( 1, $pendingDisplays );
		$this->assertSame( 'root_index', $pendingDisplays[ 0 ][ 'file_key' ] );
		$this->assertSame( 'index.php', $pendingDisplays[ 0 ][ 'title' ] );
		$this->assertSame( \wp_normalize_path( $pendingDisplays[ 0 ][ 'path' ] ), $pendingDisplays[ 0 ][ 'path' ] );
		$this->assertTrue( Services::WpFs()->isAccessibleFile( $pendingDisplays[ 0 ][ 'path' ] ) );
	}

	public function test_file_lock_candidate_displays_follow_supported_order_and_applicability() :void {
		$displays = ( new GetFileLockCandidateDisplays( new FileLockKeyApplicability( false, false ) ) )->run();
		$fileKeys = \array_column( $displays, 'file_key' );

		$this->assertNotEmpty( $fileKeys );
		$this->assertSame(
			\array_values( \array_intersect( BuildFileFromFileKey::SUPPORTED_FILE_KEYS, $fileKeys ) ),
			$fileKeys
		);
		$this->assertNotContains( 'theme_functions', $fileKeys );
		$this->assertNotContains( 'root_webconfig', $fileKeys );
		foreach ( $displays as $display ) {
			$this->assertNotSame( '', $display[ 'title' ] );
			$this->assertSame( \wp_normalize_path( $display[ 'path' ] ), $display[ 'path' ] );
		}
	}

	public function test_real_hooks_ignore_malformed_stored_selections_and_process_valid_siblings() :void {
		$this->requireFileLockerAnalysisRuntime();
		$con = $this->requireController();
		$this->prepareFileLockerRuntime( [ 'wpconfig', 'root_index' ] );
		$state = $con->comps->file_locker->getState();
		$state[ 'abspath' ] = ABSPATH;
		$state[ 'last_analysis_started_at' ] = 0;
		$state[ 'last_locks_created_at' ] = 0;
		$state[ 'last_locks_created_failed_at' ] = 0;
		$con->opts->optSet( 'filelocker_state', $state )->store();
		$this->replaceStoredFileLockerSelections( [
			'wpconfig',
			1,
			1.5,
			true,
			[ 'root_index' ],
			(object)[ 'key' => 'root_index' ],
			null,
			'',
			'unknown_file',
			'root_index',
			'wpconfig',
		] );
		$hook = $con->prefix( 'create_file_locks' );
		$hookSnapshot = $this->snapshotHooks( [ 'wp_loaded', $hook ] );
		\wp_clear_scheduled_hook( $hook );
		$probe = new FileLockerControllerIntegrationProbe();

		try {
			$probe->execute();
			global $wp_filter;
			$this->assertArrayHasKey( 1000, $wp_filter[ 'wp_loaded' ]->callbacks ?? [] );

			\do_action( 'wp_loaded' );

			$this->assertSame(
				Services::Request()->ts(),
				$probe->getState()[ 'last_analysis_started_at' ]
			);
			$this->assertNotFalse( \wp_next_scheduled( $hook ) );

			\do_action( $hook );

			$this->assertSame( [ 'wpconfig', 'root_index' ], $probe->getFilesToLock() );
			$this->assertSame( [ 'wpconfig', 'root_index' ], $probe->attemptedTypes );
		}
		finally {
			\wp_clear_scheduled_hook( $hook );
			$this->restoreHooks( $hookSnapshot );
		}
	}

	public function test_invalid_only_stored_selections_register_no_analysis_or_lock_work() :void {
		$con = $this->requireController();
		$this->prepareFileLockerRuntime( [ 'wpconfig' ] );
		$this->replaceStoredFileLockerSelections( [
			1,
			true,
			[ 'wpconfig' ],
			(object)[ 'key' => 'wpconfig' ],
			null,
			'',
			'unknown_file',
		] );
		$hook = $con->prefix( 'create_file_locks' );
		$hookSnapshot = $this->snapshotHooks( [ 'wp_loaded', $hook ] );
		\wp_clear_scheduled_hook( $hook );
		$probe = new FileLockerControllerIntegrationProbe();

		try {
			$probe->execute();
			global $wp_filter;
			$this->assertArrayNotHasKey( 1000, $wp_filter[ 'wp_loaded' ]->callbacks ?? [] );
			$this->assertFalse( \wp_next_scheduled( $hook ) );
			$this->assertSame( [], $probe->getFilesToLock() );
			$this->assertSame( [], $probe->attemptedTypes );
		}
		finally {
			\wp_clear_scheduled_hook( $hook );
			$this->restoreHooks( $hookSnapshot );
		}
	}

	/**
	 * Optional file-locker storage is only ready after the feature is enabled
	 * and ShieldNet-backed runtime prerequisites are in place.
	 */
	private function prepareFileLockerRuntime( array $lockTypes ) :FileLockerDB\Handler {
		$con = $this->requireController();
		RuntimeTestState::primeShieldNetHandshake();
		$con->opts->optSet( 'file_locker', $lockTypes )->store();

		$handler = RuntimeTestState::requireDbHandler( 'file_locker', true );
		$handler->tableDelete( true );
		$handler = RuntimeTestState::requireDbHandler( 'file_locker', true );
		$con->comps->file_locker->clearLocks();

		return $handler;
	}

	private function requireFileLockerAnalysisRuntime() :void {
		$con = $this->requireController();
		if ( \version_compare( $con->cfg->version(), '19.0.7', '<=' ) ) {
			$this->markTestSkipped( 'File Locker analysis path is disabled for this version.' );
		}

		if ( !Services::Encrypt()->isSupportedOpenSslDataEncryption() ) {
			$this->markTestSkipped( 'OpenSSL data encryption is unavailable.' );
		}
	}

	private function runFileLockerAnalysis() :void {
		$method = new \ReflectionMethod( $this->requireController()->comps->file_locker, 'runAnalysis' );
		$method->setAccessible( true );
		$method->invoke( $this->requireController()->comps->file_locker );
	}

	private function replaceStoredFileLockerSelections( array $selections ) :void {
		$con = $this->requireController();
		$optionName = $con->prefix( 'opts_all', '_' );
		$all = Services::WpGeneral()->getOption( $optionName );
		$this->assertIsArray( $all );
		$all[ 'values' ][ OptsHandler::TYPE_FREE ][ 'file_locker' ] = $selections;
		$all[ 'values' ][ OptsHandler::TYPE_PRO ][ 'file_locker' ] = $selections;
		Services::WpGeneral()->updateOption( $optionName, $all );
		$con->opts = new OptsHandler();
	}

	private function snapshotHooks( array $hookNames ) :array {
		global $wp_filter;
		$snapshot = [];
		foreach ( $hookNames as $hookName ) {
			$snapshot[ $hookName ] = $wp_filter[ $hookName ] ?? null;
			unset( $wp_filter[ $hookName ] );
		}
		return $snapshot;
	}

	private function restoreHooks( array $snapshot ) :void {
		global $wp_filter;
		foreach ( $snapshot as $hookName => $hook ) {
			if ( $hook === null ) {
				unset( $wp_filter[ $hookName ] );
			}
			else {
				$wp_filter[ $hookName ] = $hook;
			}
		}
	}

	private function abspathWithDotSegment() :string {
		$current = \untrailingslashit( \wp_normalize_path( ABSPATH ) );
		$variant = \trailingslashit( \dirname( $current ).'/./'.\basename( $current ) );

		$currentRealPath = \realpath( ABSPATH );
		$variantRealPath = \realpath( $variant );
		if ( !\is_string( $currentRealPath )
			 || !\is_string( $variantRealPath )
			 || \wp_normalize_path( $currentRealPath ) !== \wp_normalize_path( $variantRealPath )
		) {
			$this->markTestSkipped( 'Test ABSPATH cannot be represented with a matching dot-segment variant.' );
		}

		return $variant;
	}

	private function missingDifferentAbsPath() :string {
		return \trailingslashit( \wp_normalize_path(
			\sys_get_temp_dir().'/shield-missing-abspath-'.\uniqid()
		) );
	}
}

class FileLockerControllerIntegrationProbe extends FileLockerController {

	/**
	 * @var list<string>
	 */
	public array $attemptedTypes = [];

	protected function createLocksForType( string $type ) :void {
		$this->attemptedTypes[] = $type;
	}
}
