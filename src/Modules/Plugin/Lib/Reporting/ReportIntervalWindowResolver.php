<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time\{
	CalendarIntervalWindow,
	CalendarIntervalWindowResolver
};

class ReportIntervalWindowResolver {

	/**
	 * @return list<string>
	 */
	public static function supportedScheduledIntervals() :array {
		return CalendarIntervalWindowResolver::supportedIntervals();
	}

	public function isSupportedScheduledInterval( string $interval ) :bool {
		return \in_array( $interval, self::supportedScheduledIntervals(), true );
	}

	public function resolveCompletedWindow( string $interval, Carbon $referenceNow ) :CalendarIntervalWindow {
		$this->assertScheduledInterval( $interval );
		return $this->calendarResolver()->resolveCompletedWindow( $interval, $referenceNow );
	}

	public function resolvePreviousMatchingWindow(
		CalendarIntervalWindow $currentWindow,
		string $interval
	) :CalendarIntervalWindow {
		$this->assertScheduledInterval( $interval );
		return $this->calendarResolver()->resolvePreviousMatchingWindow( $currentWindow, $interval );
	}

	public function resolveAdjacentInclusiveWindow(
		int $startAt,
		int $endAt,
		string $timezone = 'UTC'
	) :CalendarIntervalWindow {
		return $this->calendarResolver()->resolveAdjacentInclusiveWindow( $startAt, $endAt, $timezone );
	}

	private function assertScheduledInterval( string $interval ) :void {
		if ( !$this->isSupportedScheduledInterval( $interval ) ) {
			throw new \InvalidArgumentException( 'Unsupported scheduled report interval: '.$interval ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal: developer-error guard; CreateReportVO validates the interval with isSupportedScheduledInterval() first, so it isn't shown.
		}
	}

	private function calendarResolver() :CalendarIntervalWindowResolver {
		return new CalendarIntervalWindowResolver();
	}
}
