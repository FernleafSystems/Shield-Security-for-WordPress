<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use Carbon\Carbon;

class ReportIntervalWindowResolver {

	/**
	 * @return list<string>
	 */
	public static function supportedScheduledIntervals() :array {
		return [
			'hourly',
			'daily',
			'weekly',
			'biweekly',
			'monthly',
			'yearly',
		];
	}

	public function isSupportedScheduledInterval( string $interval ) :bool {
		return \in_array( $interval, self::supportedScheduledIntervals(), true );
	}

	public function resolveCompletedWindow( string $interval, Carbon $referenceNow ) :ReportIntervalWindow {
		if ( !$this->isSupportedScheduledInterval( $interval ) ) {
			throw new \InvalidArgumentException( 'Unsupported scheduled report interval: '.$interval );
		}

		$timezone = $referenceNow->getTimezone()->getName();
		$intervalToReport = clone $referenceNow;

		switch ( $interval ) {
			case 'hourly':
				$intervalToReport->subHour();
				$start = ( clone $intervalToReport )->startOfHour();
				$end = ( clone $intervalToReport )->endOfHour();
				break;
			case 'daily':
				$intervalToReport->subDay();
				$start = ( clone $intervalToReport )->startOfDay();
				$end = ( clone $intervalToReport )->endOfDay();
				break;
			case 'weekly':
				$intervalToReport->subWeek();
				$start = ( clone $intervalToReport )->startOfWeek();
				$end = ( clone $intervalToReport )->endOfWeek();
				break;
			case 'biweekly':
				$intervalToReport->subWeeks( 2 )->startOfWeek();
				$start = clone $intervalToReport;
				$end = ( clone $intervalToReport )->addWeek()->endOfWeek();
				break;
			case 'monthly':
				$intervalToReport->day( 15 )->subMonth();
				$start = ( clone $intervalToReport )->startOfMonth();
				$end = ( clone $intervalToReport )->endOfMonth();
				break;
			case 'yearly':
				$intervalToReport->subYear();
				$start = ( clone $intervalToReport )->startOfYear();
				$end = ( clone $intervalToReport )->endOfYear();
				break;
			default:
				throw new \InvalidArgumentException( 'Unsupported scheduled report interval: '.$interval );
		}

		return $this->newWindow( $start, $end, $timezone );
	}

	public function resolvePreviousMatchingWindow( ReportIntervalWindow $currentWindow, string $interval ) :ReportIntervalWindow {
		if ( !$this->isSupportedScheduledInterval( $interval ) ) {
			throw new \InvalidArgumentException( 'Unsupported scheduled report interval: '.$interval );
		}

		$intervalStart = Carbon::createFromTimestamp( $currentWindow->start_at, $currentWindow->timezone );

		switch ( $interval ) {
			case 'hourly':
				$start = ( clone $intervalStart )->subHour()->startOfHour();
				$end = ( clone $intervalStart )->subHour()->endOfHour();
				break;
			case 'daily':
				$start = ( clone $intervalStart )->subDay()->startOfDay();
				$end = ( clone $intervalStart )->subDay()->endOfDay();
				break;
			case 'weekly':
				$start = ( clone $intervalStart )->subWeek()->startOfWeek();
				$end = ( clone $intervalStart )->subWeek()->endOfWeek();
				break;
			case 'biweekly':
				$start = ( clone $intervalStart )->subWeeks( 2 )->startOfWeek();
				$end = ( clone $start )->addWeek()->endOfWeek();
				break;
			case 'monthly':
				$start = ( clone $intervalStart )->subMonth()->startOfMonth();
				$end = ( clone $intervalStart )->subMonth()->endOfMonth();
				break;
			case 'yearly':
				$start = ( clone $intervalStart )->subYear()->startOfYear();
				$end = ( clone $intervalStart )->subYear()->endOfYear();
				break;
			default:
				throw new \InvalidArgumentException( 'Unsupported scheduled report interval: '.$interval );
		}

		return $this->newWindow( $start, $end, $currentWindow->timezone );
	}

	public function resolveAdjacentInclusiveWindow( int $startAt, int $endAt, string $timezone = 'UTC' ) :ReportIntervalWindow {
		if ( $endAt < $startAt ) {
			throw new \InvalidArgumentException( 'Report interval end must not be earlier than the start.' );
		}

		$inclusiveSpan = $endAt - $startAt + 1;
		$previousEnd = $startAt - 1;
		$previousStart = $previousEnd - $inclusiveSpan + 1;

		return new ReportIntervalWindow( $previousStart, $previousEnd, $timezone );
	}

	private function newWindow( Carbon $start, Carbon $end, string $timezone ) :ReportIntervalWindow {
		return new ReportIntervalWindow( $start->timestamp, $end->timestamp, $timezone );
	}
}
