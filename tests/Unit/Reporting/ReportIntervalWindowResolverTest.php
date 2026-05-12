<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	ReportIntervalWindow,
	ReportIntervalWindowResolver
};
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ReportIntervalWindowResolverTest extends TestCase {

	/**
	 * @dataProvider providerCompletedIntervals
	 */
	public function test_resolve_completed_window_returns_expected_boundaries(
		string $interval,
		Carbon $referenceNow,
		Carbon $expectedStart,
		Carbon $expectedEnd
	) :void {
		$window = ( new ReportIntervalWindowResolver() )->resolveCompletedWindow( $interval, $referenceNow );

		$this->assertSame( $expectedStart->timestamp, $window->start_at );
		$this->assertSame( $expectedEnd->timestamp, $window->end_at );
		$this->assertSame( $referenceNow->getTimezone()->getName(), $window->timezone );
	}

	public function test_resolve_previous_matching_window_uses_matching_calendar_interval() :void {
		$resolver = new ReportIntervalWindowResolver();

		$monthlyWindow = new ReportIntervalWindow(
			Carbon::create( 2024, 3, 1, 0, 0, 0, 'UTC' )->timestamp,
			Carbon::create( 2024, 3, 31, 23, 59, 59, 'UTC' )->timestamp,
			'UTC'
		);
		$previousMonth = $resolver->resolvePreviousMatchingWindow( $monthlyWindow, 'monthly' );
		$this->assertSame( Carbon::create( 2024, 2, 1, 0, 0, 0, 'UTC' )->timestamp, $previousMonth->start_at );
		$this->assertSame( Carbon::create( 2024, 2, 29, 23, 59, 59, 'UTC' )->timestamp, $previousMonth->end_at );

		$yearlyWindow = new ReportIntervalWindow(
			Carbon::create( 2024, 1, 1, 0, 0, 0, 'UTC' )->timestamp,
			Carbon::create( 2024, 12, 31, 23, 59, 59, 'UTC' )->timestamp,
			'UTC'
		);
		$previousYear = $resolver->resolvePreviousMatchingWindow( $yearlyWindow, 'yearly' );
		$this->assertSame( Carbon::create( 2023, 1, 1, 0, 0, 0, 'UTC' )->timestamp, $previousYear->start_at );
		$this->assertSame( Carbon::create( 2023, 12, 31, 23, 59, 59, 'UTC' )->timestamp, $previousYear->end_at );
	}

	public function test_resolve_completed_daily_window_preserves_dst_short_day_boundaries() :void {
		$referenceNow = Carbon::create( 2024, 4, 1, 12, 0, 0, 'Europe/London' );
		$window = ( new ReportIntervalWindowResolver() )->resolveCompletedWindow( 'daily', $referenceNow );

		$expectedStart = Carbon::create( 2024, 3, 31, 0, 0, 0, 'Europe/London' );
		$expectedEnd = Carbon::create( 2024, 3, 31, 23, 59, 59, 'Europe/London' );

		$this->assertSame( $expectedStart->timestamp, $window->start_at );
		$this->assertSame( $expectedEnd->timestamp, $window->end_at );
		$this->assertSame( 82799, $window->end_at - $window->start_at );
	}

	public function test_resolve_adjacent_inclusive_window_for_custom_reports() :void {
		$window = ( new ReportIntervalWindowResolver() )->resolveAdjacentInclusiveWindow( 1000, 1099, 'UTC' );

		$this->assertSame( 900, $window->start_at );
		$this->assertSame( 999, $window->end_at );
	}

	public static function providerCompletedIntervals() :array {
		$referenceNow = Carbon::create( 2024, 4, 19, 13, 45, 0, 'Europe/London' );

		return [
			'hourly' => [
				'hourly',
				clone $referenceNow,
				Carbon::create( 2024, 4, 19, 12, 0, 0, 'Europe/London' ),
				Carbon::create( 2024, 4, 19, 12, 59, 59, 'Europe/London' ),
			],
			'daily' => [
				'daily',
				clone $referenceNow,
				Carbon::create( 2024, 4, 18, 0, 0, 0, 'Europe/London' ),
				Carbon::create( 2024, 4, 18, 23, 59, 59, 'Europe/London' ),
			],
			'weekly' => [
				'weekly',
				clone $referenceNow,
				Carbon::create( 2024, 4, 8, 0, 0, 0, 'Europe/London' ),
				Carbon::create( 2024, 4, 14, 23, 59, 59, 'Europe/London' ),
			],
			'biweekly' => [
				'biweekly',
				clone $referenceNow,
				Carbon::create( 2024, 4, 1, 0, 0, 0, 'Europe/London' ),
				Carbon::create( 2024, 4, 14, 23, 59, 59, 'Europe/London' ),
			],
			'monthly' => [
				'monthly',
				clone $referenceNow,
				Carbon::create( 2024, 3, 1, 0, 0, 0, 'Europe/London' ),
				Carbon::create( 2024, 3, 31, 23, 59, 59, 'Europe/London' ),
			],
			'yearly' => [
				'yearly',
				clone $referenceNow,
				Carbon::create( 2023, 1, 1, 0, 0, 0, 'Europe/London' ),
				Carbon::create( 2023, 12, 31, 23, 59, 59, 'Europe/London' ),
			],
		];
	}
}
