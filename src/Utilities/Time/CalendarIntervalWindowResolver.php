<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time;

use Carbon\Carbon;

class CalendarIntervalWindowResolver {

	private const FORTNIGHT_ANCHOR = '1970-01-05 00:00:00';

	/**
	 * @return list<string>
	 */
	public static function supportedIntervals() :array {
		return [
			'daily',
			'weekly',
			'biweekly',
			'monthly',
			'yearly',
		];
	}

	public function isSupportedInterval( string $interval ) :bool {
		return \in_array( $interval, self::supportedIntervals(), true );
	}

	public function resolveCompletedWindow( string $interval, Carbon $referenceNow ) :CalendarIntervalWindow {
		$this->assertSupported( $interval );

		$timezone = $referenceNow->getTimezone()->getName();
		$intervalToReport = clone $referenceNow;

		switch ( $interval ) {
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
				$start = $this->resolveCurrentFortnightStart( $intervalToReport )->subWeeks( 2 );
				$end = ( clone $start )->addWeeks( 2 )->subSecond();
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
				throw new \InvalidArgumentException( 'Unsupported calendar interval: '.$interval );
		}

		return $this->newWindow( $start, $end, $timezone );
	}

	public function resolveWindowContaining( string $interval, Carbon $reference ) :CalendarIntervalWindow {
		$this->assertSupported( $interval );
		$timezone = $reference->getTimezone()->getName();

		switch ( $interval ) {
			case 'daily':
				$start = ( clone $reference )->startOfDay();
				$end = ( clone $reference )->endOfDay();
				break;
			case 'weekly':
				$start = ( clone $reference )->startOfWeek();
				$end = ( clone $reference )->endOfWeek();
				break;
			case 'biweekly':
				$start = $this->resolveCurrentFortnightStart( clone $reference );
				$end = ( clone $start )->addWeeks( 2 )->subSecond();
				break;
			case 'monthly':
				$start = ( clone $reference )->startOfMonth();
				$end = ( clone $reference )->endOfMonth();
				break;
			case 'yearly':
				$start = ( clone $reference )->startOfYear();
				$end = ( clone $reference )->endOfYear();
				break;
			default:
				throw new \InvalidArgumentException( 'Unsupported calendar interval: '.$interval );
		}

		return $this->newWindow( $start, $end, $timezone );
	}

	public function resolvePreviousMatchingWindow(
		CalendarIntervalWindow $currentWindow,
		string $interval
	) :CalendarIntervalWindow {
		$this->assertSupported( $interval );

		$intervalStart = Carbon::createFromTimestamp( $currentWindow->start_at, $currentWindow->timezone );

		switch ( $interval ) {
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
				$end = ( clone $start )->addWeeks( 2 )->subSecond();
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
				throw new \InvalidArgumentException( 'Unsupported calendar interval: '.$interval );
		}

		return $this->newWindow( $start, $end, $currentWindow->timezone );
	}

	public function resolveAdjacentInclusiveWindow(
		int $startAt,
		int $endAt,
		string $timezone = 'UTC'
	) :CalendarIntervalWindow {
		if ( $endAt < $startAt ) {
			throw new \InvalidArgumentException( 'Calendar interval end must not be earlier than the start.' );
		}

		$inclusiveSpan = $endAt - $startAt + 1;
		$previousEnd = $startAt - 1;

		return new CalendarIntervalWindow(
			$previousEnd - $inclusiveSpan + 1,
			$previousEnd,
			$timezone
		);
	}

	private function resolveCurrentFortnightStart( Carbon $reference ) :Carbon {
		$weekStart = ( clone $reference )->startOfWeek();
		$anchor = Carbon::parse( self::FORTNIGHT_ANCHOR, $reference->getTimezone() );
		$weeksSinceAnchor = (int)\floor( $anchor->diffInDays( $weekStart, false ) / 7 );

		return $weekStart->subWeeks( $weeksSinceAnchor % 2 );
	}

	private function assertSupported( string $interval ) :void {
		if ( !$this->isSupportedInterval( $interval ) ) {
			throw new \InvalidArgumentException( 'Unsupported calendar interval: '.$interval );
		}
	}

	private function newWindow( Carbon $start, Carbon $end, string $timezone ) :CalendarIntervalWindow {
		return new CalendarIntervalWindow( $start->timestamp, $end->timestamp, $timezone );
	}
}
