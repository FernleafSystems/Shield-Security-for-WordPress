<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time\CalendarIntervalWindowResolver;
use FernleafSystems\Wordpress\Services\Services;

class BuildChartData {

	use PluginControllerConsumer;

	/**
	 * @return array{
	 *   period_key:string,
	 *   period_label:string,
	 *   labels:list<string>,
	 *   series:list<array{
	 *     key:string,
	 *     label:string,
	 *     data:list<int>
	 *   }>
	 * }
	 */
	public function build( ChartRequestVO $req ) :array {
		$periodKey = $req->period_key;
		$eventKeys = $req->event_keys;
		if ( empty( $eventKeys ) ) {
			throw new \InvalidArgumentException( __( 'Select at least one event to chart.', 'wp-simple-firewall' ) );
		}

		$period = ChartOptions::periodDefinitions()[ $periodKey ];
		$buckets = $periodKey === ChartOptions::PERIOD_YEARS
			? $this->buildYearBuckets( $eventKeys )
			: $this->buildFixedBuckets( $period[ 'interval' ], $period[ 'ticks' ] );

		$eventDefinitions = ChartOptions::eventDefinitions();

		return [
			'period_key'   => $periodKey,
			'period_label' => $period[ 'label' ],
			'labels'       => \array_column( $buckets, 'label' ),
			'series'       => \array_map(
				fn( string $eventKey ) :array => [
					'key'   => $eventKey,
					'label' => $eventDefinitions[ $eventKey ][ 'label' ],
					'data'  => \array_map(
						fn( array $bucket ) :int => $this->sumEventForBucket(
							$eventKey,
							$bucket[ 'start_at' ],
							$bucket[ 'end_at' ]
						),
						$buckets
					),
				],
				$eventKeys
			),
		];
	}

	/**
	 * @return list<array{
	 *   start_at:int,
	 *   end_at:int,
	 *   label:string
	 * }>
	 */
	private function buildFixedBuckets( string $interval, int $ticks ) :array {
		$buckets = [];
		$resolver = new CalendarIntervalWindowResolver();
		$cursor = Services::Request()->carbon( true );
		switch ( $interval ) {
			case 'daily':
				$cursor->subDays( $ticks - 1 );
				break;
			case 'weekly':
				$cursor->subWeeks( $ticks - 1 );
				break;
			case 'monthly':
				$cursor->day( 15 )->subMonths( $ticks - 1 );
				break;
			default:
				throw new \InvalidArgumentException( 'Unsupported fixed chart interval.' );
		}
		$cursor = Carbon::createFromTimestamp(
			$resolver->resolveWindowContaining( $interval, $cursor )->start_at,
			\wp_timezone()
		);

		for ( $i = 0; $i < $ticks; $i++ ) {
			$window = $resolver->resolveWindowContaining( $interval, $cursor );
			$buckets[] = [
				'start_at' => $window->start_at,
				'end_at'   => $window->end_at,
				'label'    => $this->formatBucketLabel( $interval, $cursor ),
			];
			switch ( $interval ) {
				case 'daily':
					$cursor->addDay();
					break;
				case 'weekly':
					$cursor->addWeek();
					break;
				case 'monthly':
					$cursor->addMonth();
					break;
			}
		}

		return $buckets;
	}

	/**
	 * @param list<string> $eventKeys
	 * @return list<array{
	 *   start_at:int,
	 *   end_at:int,
	 *   label:string
	 * }>
	 */
	private function buildYearBuckets( array $eventKeys ) :array {
		$timezone = \wp_timezone();
		$currentYear = (int)Services::Request()->carbon( true )->format( 'Y' );
		$startYear = $currentYear;

		foreach ( $eventKeys as $eventKey ) {
			/** @var EventsDB\Select $selector */
			$selector = self::con()->db_con->events->getQuerySelector();
			$oldest = $selector->getOldestForEvent( $eventKey );
			if ( $oldest !== null ) {
				$startYear = \min(
					$startYear,
					(int)Carbon::createFromTimestamp( $oldest->created_at, $timezone )->format( 'Y' )
				);
			}
		}

		$buckets = [];
		$resolver = new CalendarIntervalWindowResolver();
		for ( $year = $startYear; $year <= $currentYear; $year++ ) {
			$yearStart = Carbon::create( $year, 1, 1, 0, 0, 0, $timezone );
			$window = $resolver->resolveWindowContaining( 'yearly', $yearStart );
			$buckets[] = [
				'start_at' => $window->start_at,
				'end_at'   => $window->end_at,
				'label'    => (string)$year,
			];
		}

		return $buckets;
	}

	private function formatBucketLabel( string $interval, Carbon $bucketStart ) :string {
		switch ( $interval ) {
			case 'daily':
			case 'weekly':
				return $bucketStart->format( 'j M Y' );

			case 'monthly':
				return $bucketStart->format( 'M Y' );

			case 'yearly':
				return $bucketStart->format( 'Y' );

			default:
				return $bucketStart->format( 'j M Y' );
		}
	}

	private function sumEventForBucket( string $eventKey, int $startAt, int $endAt ) :int {
		/** @var EventsDB\Select $selector */
		$selector = self::con()->db_con->events->getQuerySelector();
		return $selector->filterByBoundary( $startAt, $endAt )->sumEvent( $eventKey );
	}
}
