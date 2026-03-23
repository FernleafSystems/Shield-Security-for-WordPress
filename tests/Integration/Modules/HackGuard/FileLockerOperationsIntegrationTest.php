<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\CleanLockRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class FileLockerOperationsIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'file_locker' );
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'file_locker' ] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionSnapshot );
		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
		}
		parent::tear_down();
	}

	public function test_clean_lock_records_deletes_rows_for_unselected_lock_types() :void {
		$con = $this->requireController();
		$handler = $this->requireDb( 'file_locker' );

		$con->opts->optSet( 'file_locker', [ 'wpconfig' ] )->store();
		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		TestDataFactory::insertFileLockRecord( 'root_index', ABSPATH.'index.php' );
		$con->comps->file_locker->clearLocks();

		( new CleanLockRecords() )->run();

		$records = $handler->getQuerySelector()
						   ->setNoOrderBy()
						   ->queryWithResult();
		$this->assertCount( 1, $records );
		$this->assertSame( 'wpconfig', $records[ 0 ]->type );
	}

	public function test_purge_deletes_existing_file_lock_rows() :void {
		global $wpdb;
		$con = $this->requireController();
		$handler = $this->requireDb( 'file_locker' );

		$con->opts->optSet( 'file_locker', [ 'wpconfig' ] )->store();
		TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php' );
		$this->assertSame( 1, (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$handler->getTable()}" ) );

		$con->comps->file_locker->purge();

		$this->assertSame( 0, (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$handler->getTable()}" ) );
	}
}
