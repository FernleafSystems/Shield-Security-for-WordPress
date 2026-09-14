<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class BuildForScansRepairsIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'activity_logs_meta' );
	}

	public function test_compaction_preserves_scan_repair_report_count() :void {
		$event = 'scan_item_repair_success';
		$day = Carbon::create( 2030, 6, 22, 0, 0, 0, 'UTC' );
		$this->insertEvent( $event, 2, ( clone $day )->addHour()->timestamp );
		$this->insertEvent( $event, 3, ( clone $day )->addHours( 2 )->timestamp );
		$report = new ReportVO();
		$report->start_at = $day->timestamp;
		$report->end_at = ( clone $day )->endOfDay()->timestamp;
		$report->areas = [ Constants::REPORT_AREA_SCANS => [ 'scan_repairs' ] ];

		$before = ( new BuildForScans( $report ) )->build()[ 'scan_repairs' ][ $event ][ 'count' ] ?? null;
		$this->assertSame( 2, self::con()->db_con->events->getQuerySelector()->filterByEvent( $event )->count() );
		$this->assertTrue( self::con()->db_con->events->compactBoundary( $report->start_at, $report->end_at ) );
		$after = ( new BuildForScans( $report ) )->build()[ 'scan_repairs' ][ $event ][ 'count' ] ?? null;

		$this->assertSame( 5, $before );
		$this->assertSame( $before, $after );
		$this->assertSame( 1, self::con()->db_con->events->getQuerySelector()->filterByEvent( $event )->count() );
	}

	private function insertEvent( string $event, int $count, int $createdAt ) :void {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
	}
}
