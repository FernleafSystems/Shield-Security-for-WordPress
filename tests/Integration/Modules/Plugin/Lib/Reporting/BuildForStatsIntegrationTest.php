<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	ReportIntervalWindowResolver,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForStats;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class BuildForStatsIntegrationTest extends ShieldIntegrationTestCase {

	private string $timezoneSnapshot = '';

	/** @var mixed */
	private $gmtOffsetSnapshot;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'events' );
		$this->loginAsSecurityAdmin();
		$this->timezoneSnapshot = (string)\get_option( 'timezone_string', '' );
		$this->gmtOffsetSnapshot = \get_option( 'gmt_offset', 0 );
		$this->setNamedTimezone( 'UTC' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			\update_option( 'timezone_string', $this->timezoneSnapshot );
			\update_option( 'gmt_offset', $this->gmtOffsetSnapshot );
		}
		parent::tear_down();
	}

	public function test_build_for_group_uses_exact_previous_daily_boundaries_without_overlap() :void {
		$resolver = new ReportIntervalWindowResolver();
		$currentWindow = $resolver->resolveCompletedWindow( 'daily', Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) );
		$previousWindow = $resolver->resolvePreviousMatchingWindow( $currentWindow, 'daily' );

		$this->insertEventRecord( 'ip_blocked', 2, $previousWindow->start_at );
		$this->insertEventRecord( 'ip_blocked', 1, $previousWindow->end_at );
		$this->insertEventRecord( 'ip_blocked', 3, $currentWindow->start_at );
		$this->insertEventRecord( 'ip_blocked', 1, $currentWindow->end_at );
		$this->insertEventRecord( 'ip_blocked', 11, $previousWindow->start_at - 1 );
		$this->insertEventRecord( 'ip_blocked', 13, $currentWindow->end_at + 1 );

		$stats = ( new BuildForStats( $this->buildReport( $currentWindow->start_at, $currentWindow->end_at, 'daily' ) ) )
			->buildForGroup( [ 'ip_blocked' ] );

		$this->assertSame( 4, $stats[ 'ip_blocked' ][ 'count_current_period' ] );
		$this->assertSame( 3, $stats[ 'ip_blocked' ][ 'count_previous_period' ] );
		$this->assertSame( 1, $stats[ 'ip_blocked' ][ 'count_diff' ] );
		$this->assertSame( 1, $stats[ 'ip_blocked' ][ 'count_diff_abs' ] );
		$this->assertSame( 33, $stats[ 'ip_blocked' ][ 'diff_percentage' ] );
	}

	public function test_build_for_group_monthly_previous_period_uses_previous_calendar_month() :void {
		$resolver = new ReportIntervalWindowResolver();
		$currentWindow = $resolver->resolveCompletedWindow( 'monthly', Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) );

		$this->insertEventRecord( 'ip_blocked', 7, Carbon::create( 2024, 1, 31, 12, 0, 0, 'UTC' )->timestamp );
		$this->insertEventRecord( 'ip_blocked', 5, Carbon::create( 2024, 2, 15, 12, 0, 0, 'UTC' )->timestamp );
		$this->insertEventRecord( 'ip_blocked', 8, Carbon::create( 2024, 3, 15, 12, 0, 0, 'UTC' )->timestamp );

		$stats = ( new BuildForStats( $this->buildReport( $currentWindow->start_at, $currentWindow->end_at, 'monthly' ) ) )
			->buildForGroup( [ 'ip_blocked' ] );

		$this->assertSame( 8, $stats[ 'ip_blocked' ][ 'count_current_period' ] );
		$this->assertSame( 5, $stats[ 'ip_blocked' ][ 'count_previous_period' ] );
		$this->assertSame( 60, $stats[ 'ip_blocked' ][ 'diff_percentage' ] );
	}

	public function test_build_for_group_yearly_previous_period_handles_leap_year_boundary() :void {
		$resolver = new ReportIntervalWindowResolver();
		$currentWindow = $resolver->resolveCompletedWindow( 'yearly', Carbon::create( 2025, 4, 19, 13, 45, 0, 'UTC' ) );

		$this->insertEventRecord( 'ip_blocked', 4, Carbon::create( 2023, 1, 1, 12, 0, 0, 'UTC' )->timestamp );
		$this->insertEventRecord( 'ip_blocked', 6, Carbon::create( 2024, 6, 30, 12, 0, 0, 'UTC' )->timestamp );

		$stats = ( new BuildForStats( $this->buildReport( $currentWindow->start_at, $currentWindow->end_at, 'yearly' ) ) )
			->buildForGroup( [ 'ip_blocked' ] );

		$this->assertSame( 6, $stats[ 'ip_blocked' ][ 'count_current_period' ] );
		$this->assertSame( 4, $stats[ 'ip_blocked' ][ 'count_previous_period' ] );
		$this->assertSame( 50, $stats[ 'ip_blocked' ][ 'diff_percentage' ] );
	}

	public function test_build_for_group_custom_reports_use_adjacent_inclusive_previous_window() :void {
		$this->insertEventRecord( 'ip_blocked', 7, 899 );
		$this->insertEventRecord( 'ip_blocked', 3, 999 );
		$this->insertEventRecord( 'ip_blocked', 5, 1000 );

		$stats = ( new BuildForStats( $this->buildReport( 1000, 1099, 'custom' ) ) )
			->buildForGroup( [ 'ip_blocked' ] );

		$this->assertSame( 5, $stats[ 'ip_blocked' ][ 'count_current_period' ] );
		$this->assertSame( 3, $stats[ 'ip_blocked' ][ 'count_previous_period' ] );
		$this->assertSame( 67, $stats[ 'ip_blocked' ][ 'diff_percentage' ] );
	}

	public function test_build_for_group_reports_negative_percentage_for_real_decrease_path() :void {
		$resolver = new ReportIntervalWindowResolver();
		$currentWindow = $resolver->resolveCompletedWindow( 'daily', Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) );
		$previousWindow = $resolver->resolvePreviousMatchingWindow( $currentWindow, 'daily' );

		$this->insertEventRecord( 'ip_blocked', 10, $previousWindow->start_at + 3600 );
		$this->insertEventRecord( 'ip_blocked', 4, $currentWindow->start_at + 3600 );

		$stats = ( new BuildForStats( $this->buildReport( $currentWindow->start_at, $currentWindow->end_at, 'daily' ) ) )
			->buildForGroup( [ 'ip_blocked' ] );

		$this->assertSame( 4, $stats[ 'ip_blocked' ][ 'count_current_period' ] );
		$this->assertSame( 10, $stats[ 'ip_blocked' ][ 'count_previous_period' ] );
		$this->assertSame( -6, $stats[ 'ip_blocked' ][ 'count_diff' ] );
		$this->assertSame( 6, $stats[ 'ip_blocked' ][ 'count_diff_abs' ] );
		$this->assertSame( -60, $stats[ 'ip_blocked' ][ 'diff_percentage' ] );
	}

	public function test_build_for_group_reports_full_increase_for_real_zero_baseline_path() :void {
		$resolver = new ReportIntervalWindowResolver();
		$currentWindow = $resolver->resolveCompletedWindow( 'daily', Carbon::create( 2024, 4, 19, 13, 45, 0, 'UTC' ) );

		$this->insertEventRecord( 'ip_blocked', 5, $currentWindow->start_at + 1800 );

		$stats = ( new BuildForStats( $this->buildReport( $currentWindow->start_at, $currentWindow->end_at, 'daily' ) ) )
			->buildForGroup( [ 'ip_blocked' ] );

		$this->assertSame( 5, $stats[ 'ip_blocked' ][ 'count_current_period' ] );
		$this->assertSame( 0, $stats[ 'ip_blocked' ][ 'count_previous_period' ] );
		$this->assertSame( 5, $stats[ 'ip_blocked' ][ 'count_diff' ] );
		$this->assertSame( 5, $stats[ 'ip_blocked' ][ 'count_diff_abs' ] );
		$this->assertSame( 100, $stats[ 'ip_blocked' ][ 'diff_percentage' ] );
	}

	public function test_build_for_group_offset_only_daily_boundaries_use_wordpress_timezone() :void {
		$this->setOffsetOnlyTimezone( 5.5 );

		$currentStart = Carbon::create( 2024, 4, 18, 0, 0, 0, '+05:30' )->timestamp;
		$currentEnd = Carbon::create( 2024, 4, 18, 23, 59, 59, '+05:30' )->timestamp;
		$previousStart = Carbon::create( 2024, 4, 17, 0, 0, 0, '+05:30' )->timestamp;
		$previousEnd = Carbon::create( 2024, 4, 17, 23, 59, 59, '+05:30' )->timestamp;

		$this->insertEventRecord( 'ip_blocked', 2, $previousStart );
		$this->insertEventRecord( 'ip_blocked', 1, $previousEnd );
		$this->insertEventRecord( 'ip_blocked', 3, $currentStart );
		$this->insertEventRecord( 'ip_blocked', 1, $currentEnd );
		$this->insertEventRecord( 'ip_blocked', 11, $previousStart - 1 );
		$this->insertEventRecord( 'ip_blocked', 13, $currentEnd + 1 );

		$stats = ( new BuildForStats( $this->buildReport( $currentStart, $currentEnd, 'daily' ) ) )
			->buildForGroup( [ 'ip_blocked' ] );

		$this->assertSame( 4, $stats[ 'ip_blocked' ][ 'count_current_period' ] );
		$this->assertSame( 3, $stats[ 'ip_blocked' ][ 'count_previous_period' ] );
		$this->assertSame( 33, $stats[ 'ip_blocked' ][ 'diff_percentage' ] );
	}

	private function buildReport( int $startAt, int $endAt, string $interval ) :ReportVO {
		$report = new ReportVO();
		$report->start_at = $startAt;
		$report->end_at = $endAt;
		$report->interval = $interval;
		return $report;
	}

	private function insertEventRecord( string $event, int $count, int $createdAt ) :void {
		$dbh = self::con()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = $event;
		$record->count = $count;
		$record->created_at = $createdAt;
		$dbh->getQueryInserter()->insert( $record );
	}

	private function setNamedTimezone( string $timezone ) :void {
		\update_option( 'timezone_string', $timezone );
		\update_option( 'gmt_offset', 0 );
	}

	private function setOffsetOnlyTimezone( float $offset ) :void {
		\update_option( 'timezone_string', '' );
		\update_option( 'gmt_offset', $offset );
	}
}
