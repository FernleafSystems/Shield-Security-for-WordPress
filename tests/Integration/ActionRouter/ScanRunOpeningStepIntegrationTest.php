<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Run Manual Scan opens on the result only of a scan someone ran by hand in the last ten minutes. Anything
 * older, and any scheduled scan, leaves it on choosing scans.
 */
class ScanRunOpeningStepIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		Services::WpDb()->doSql( \sprintf( 'DELETE FROM `%s`', self::con()->db_con->scans->getTable() ) );
	}

	public function test_a_manual_scan_finished_minutes_ago_opens_on_its_result() :void {
		TestDataFactory::insertCompletedScan( 'wpv', \time() - 120 );

		$this->assertSame( [ 'wpv' ], ScansProgress::RecentlyFinishedScanSlugs() );
	}

	public function test_a_manual_scan_finished_over_ten_minutes_ago_does_not() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - ScansProgress::RECENT_RESULT_WINDOW - 60 );

		$this->assertSame( [], ScansProgress::RecentlyFinishedScanSlugs() );
	}

	public function test_a_scheduled_scan_that_just_finished_does_not() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'apc', \time() - 30 );
		Services::WpDb()->doSql( \sprintf(
			"UPDATE `%s` SET `run_trigger`='cron' WHERE `id`=%d",
			self::con()->db_con->scans->getTable(),
			$scanID
		) );

		$this->assertSame( [], ScansProgress::RecentlyFinishedScanSlugs() );
	}

	public function test_results_list_scans_in_page_order() :void {
		TestDataFactory::insertCompletedScan( 'apc', \time() - 60 );
		TestDataFactory::insertCompletedScan( 'afs', \time() - 90 );

		$this->assertSame( [ 'afs', 'apc' ], ScansProgress::RecentlyFinishedScanSlugs() );
	}
}
