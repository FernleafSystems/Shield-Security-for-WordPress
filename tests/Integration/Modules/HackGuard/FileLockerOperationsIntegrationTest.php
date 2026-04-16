<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\CleanLockRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
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

		$con->comps->file_locker->purge();

		$reloadedHandler = RuntimeTestState::requireDbHandler( 'file_locker', true );
		$this->assertTrue( $reloadedHandler->tableExists() );
		$this->assertSame( 0, (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$reloadedHandler->getTable()}" ) );
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

	/**
	 * Optional file-locker storage is only ready after the feature is enabled
	 * and ShieldNet-backed runtime prerequisites are in place.
	 */
	private function prepareFileLockerRuntime( array $lockTypes ) {
		$con = $this->requireController();
		RuntimeTestState::primeShieldNetHandshake();
		$con->opts->optSet( 'file_locker', $lockTypes )->store();

		$handler = RuntimeTestState::requireDbHandler( 'file_locker', true );
		$con->comps->file_locker->clearLocks();

		return $handler;
	}
}
