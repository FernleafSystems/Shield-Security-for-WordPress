<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\{
	ScanProgressBuilder,
	ScansProgress
};
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
		$this->loginAsSecurityAdmin();
	}

	public function test_a_manual_scan_finished_minutes_ago_opens_on_its_result() :void {
		TestDataFactory::insertCompletedScan( 'wpv', \time() - 120 );

		$opening = ( new ScanProgressBuilder() )->forOpeningPage();

		$this->assertNotNull( $opening );
		$this->assertSame( ScansProgress::STATE_COMPLETED, $opening[ 'progress_state' ] );
		$this->assertStringContainsString( $this->scanName( 'wpv' ), $opening[ 'progress_html' ] );
		$this->assertStringNotContainsString( $this->scanName( 'afs' ), $opening[ 'progress_html' ] );
	}

	public function test_a_manual_scan_finished_over_ten_minutes_ago_does_not() :void {
		TestDataFactory::insertCompletedScan( 'afs', \time() - ScanProgressBuilder::RECENT_RESULT_WINDOW - 60 );

		$this->assertNull( ( new ScanProgressBuilder() )->forOpeningPage() );
	}

	public function test_a_scheduled_scan_that_just_finished_does_not() :void {
		$scanID = TestDataFactory::insertCompletedScan( 'apc', \time() - 30 );
		Services::WpDb()->doSql( \sprintf(
			"UPDATE `%s` SET `run_trigger`='cron' WHERE `id`=%d",
			self::con()->db_con->scans->getTable(),
			$scanID
		) );

		$this->assertNull( ( new ScanProgressBuilder() )->forOpeningPage() );
	}

	public function test_results_list_scans_in_page_order() :void {
		TestDataFactory::insertCompletedScan( 'apc', \time() - 60 );
		TestDataFactory::insertCompletedScan( 'afs', \time() - 90 );

		$html = ( new ScanProgressBuilder() )->forOpeningPage()[ 'progress_html' ];

		$afsAt = \strpos( $html, $this->scanName( 'afs' ) );
		$apcAt = \strpos( $html, $this->scanName( 'apc' ) );
		$this->assertIsInt( $afsAt );
		$this->assertIsInt( $apcAt );
		$this->assertLessThan( $apcAt, $afsAt );
		$this->assertStringNotContainsString( $this->scanName( 'wpv' ), $html );
	}

	private function scanName( string $slug ) :string {
		return self::con()->comps->scans->getScanCon( $slug )->getStrings()[ 'name' ];
	}
}
