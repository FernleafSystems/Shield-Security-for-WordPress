<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
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
						fn( array $bucket ) :int => $this->sumEventForBucket( $eventKey, $bucket[ 'interval' ], $bucket[ 'timestamp' ] ),
						$buckets
					),
				],
				$eventKeys
			),
		];
	}

	/**
	 * @return list<array{
	 *   interval:'daily'|'weekly'|'monthly'|'yearly',
	 *   timestamp:int,
	 *   label:string
	 * }>
	 */
	private function buildFixedBuckets( string $interval, int $ticks ) :array {
		$buckets = [];
		$cursor = Services::Request()->carbon();
		switch ( $interval ) {
			case 'daily':
				$cursor->subDays( $ticks - 1 )->startOfDay();
				break;
			case 'weekly':
				$cursor->subWeeks( $ticks - 1 )->startOfWeek();
				break;
			case 'monthly':
				$cursor->subMonths( $ticks - 1 )->startOfMonth();
				break;
			default:
				throw new \InvalidArgumentException( 'Unsupported fixed chart interval.' );
		}

		for ( $i = 0; $i < $ticks; $i++ ) {
			$buckets[] = [
				'interval'  => $interval,
				'timestamp' => $cursor->timestamp,
				'label'     => $this->formatBucketLabel( $interval, $cursor ),
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
	 *   interval:'yearly',
	 *   timestamp:int,
	 *   label:string
	 * }>
	 */
	private function buildYearBuckets( array $eventKeys ) :array {
		$currentYear = (int)Services::Request()->carbon()->format( 'Y' );
		$startYear = $currentYear;

		foreach ( $eventKeys as $eventKey ) {
			/** @var EventsDB\Select $selector */
			$selector = self::con()->db_con->events->getQuerySelector();
			$oldest = $selector->getOldestForEvent( $eventKey );
			if ( $oldest !== null ) {
				$startYear = \min( $startYear, (int)Carbon::createFromTimestamp( $oldest->created_at )->format( 'Y' ) );
			}
		}

		$buckets = [];
		for ( $year = $startYear; $year <= $currentYear; $year++ ) {
			$timestamp = Carbon::create( $year, 1, 1, 0, 0, 0 )->timestamp;
			$buckets[] = [
				'interval'  => 'yearly',
				'timestamp' => $timestamp,
				'label'     => (string)$year,
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

	private function sumEventForBucket( string $eventKey, string $interval, int $timestamp ) :int {
		/** @var EventsDB\Select $selector */
		$selector = self::con()->db_con->events->getQuerySelector();
		$selector->filterByEvent( $eventKey );

		switch ( $interval ) {
			case 'daily':
				$selector->filterByBoundary_Day( $timestamp );
				break;
			case 'weekly':
				$selector->filterByBoundary_Week( $timestamp );
				break;
			case 'monthly':
				$selector->filterByBoundary_Month( $timestamp );
				break;
			case 'yearly':
				$selector->filterByBoundary_Year( $timestamp );
				break;
			default:
				throw new \InvalidArgumentException( 'Unsupported chart interval.' );
		}

		return $selector->sumEvent( $eventKey );
	}
}
