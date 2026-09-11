<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportIntervalWindowResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time\{
	CalendarIntervalWindow,
	CalendarIntervalWindowResolver
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

		$monthlyWindow = new CalendarIntervalWindow(
			Carbon::create( 2024, 3, 1, 0, 0, 0, 'UTC' )->timestamp,
			Carbon::create( 2024, 3, 31, 23, 59, 59, 'UTC' )->timestamp,
			'UTC'
		);
		$previousMonth = $resolver->resolvePreviousMatchingWindow( $monthlyWindow, 'monthly' );
		$this->assertSame( Carbon::create( 2024, 2, 1, 0, 0, 0, 'UTC' )->timestamp, $previousMonth->start_at );
		$this->assertSame( Carbon::create( 2024, 2, 29, 23, 59, 59, 'UTC' )->timestamp, $previousMonth->end_at );

		$yearlyWindow = new CalendarIntervalWindow(
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

	public function test_calendar_containing_windows_preserve_dst_long_and_fractional_offset_days() :void {
		$resolver = new CalendarIntervalWindowResolver();
		$london = $resolver->resolveWindowContaining(
			'daily',
			Carbon::create( 2024, 10, 27, 12, 0, 0, 'Europe/London' )
		);
		$this->assertSame( 89999, $london->end_at - $london->start_at );

		$kathmandu = $resolver->resolveWindowContaining(
			'daily',
			Carbon::create( 2024, 4, 19, 12, 0, 0, 'Asia/Kathmandu' )
		);
		$this->assertSame(
			Carbon::create( 2024, 4, 19, 0, 0, 0, 'Asia/Kathmandu' )->timestamp,
			$kathmandu->start_at
		);
		$this->assertSame(
			Carbon::create( 2024, 4, 19, 23, 59, 59, 'Asia/Kathmandu' )->timestamp,
			$kathmandu->end_at
		);
	}

	public function test_resolve_adjacent_inclusive_window_for_custom_reports() :void {
		$window = ( new ReportIntervalWindowResolver() )->resolveAdjacentInclusiveWindow( 1000, 1099, 'UTC' );

		$this->assertSame( 900, $window->start_at );
		$this->assertSame( 999, $window->end_at );
	}

	public function test_biweekly_window_is_stable_during_second_week_of_fortnight() :void {
		$resolver = new ReportIntervalWindowResolver();
		$firstWeek = $resolver->resolveCompletedWindow(
			'biweekly',
			Carbon::create( 2024, 4, 19, 13, 45, 0, 'Europe/London' )
		);
		$secondWeek = $resolver->resolveCompletedWindow(
			'biweekly',
			Carbon::create( 2024, 4, 26, 13, 45, 0, 'Europe/London' )
		);

		$this->assertSame( $firstWeek->start_at, $secondWeek->start_at );
		$this->assertSame( $firstWeek->end_at, $secondWeek->end_at );
	}

	public static function providerCompletedIntervals() :array {
		$referenceNow = Carbon::create( 2024, 4, 19, 13, 45, 0, 'Europe/London' );

		return [
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
