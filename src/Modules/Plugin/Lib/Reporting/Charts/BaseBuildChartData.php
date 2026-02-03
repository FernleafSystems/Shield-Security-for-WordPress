<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BaseBuildChartData {

	use PluginControllerConsumer;
	use ChartRequestConsumer;

	protected array $labels = [];

	/**
	 * @throws \InvalidArgumentException
	 */
	public function build() :array {
		$req = $this->getChartRequest();
		$this->preProcessRequest();

		$allSeries = [];
		if ( $req->combine_events ) {
			$allSeries[] = $this->buildDataForEvents( $req->events );
		}
		else {
			foreach ( $req->events as $event ) {
				$allSeries[] = $this->buildDataForEvents( [ $event ] );
			}
		}

		return [
			'title'  => $this->buildTitle(),
			'legend' => $this->buildLegend(),
			'data'   => [
				'labels' => $this->labels,
				'series' => $allSeries,
			],
		];
	}

	protected function buildLegend() :array {
		$req = $this->getChartRequest();
		$legend = [];
		if ( !$req->combine_events ) {
			foreach ( $req->events as $event ) {
				$legend[] = self::con()->comps->events->getEventName( $event );
			}
		}
		return $legend;
	}

	protected function buildTitle() :string {
		$req = $this->getChartRequest();
		switch ( $req->interval ) {
			case 'hourly':
				$counter = 'hours';
				break;
			case 'weekly':
				$counter = 'weeks';
				break;
			case 'monthly':
				$counter = 'months';
				break;
			case 'yearly':
				$counter = 'years';
				break;
			default:
			case 'daily':
				$counter = 'days';
				break;
		}
		return sprintf( 'Chart showing events from the past %s %s', $req->ticks, $counter );
	}

	protected function buildDataForEvents( array $events ) :array {
		$req = $this->getChartRequest();

		$tick = 0;
		$carbon = Services::Request()->carbon();

		$labels = [];
		$dataSeries = [];
		do {
			/** @var EventsDB\Select $eventSelect */
			$eventSelect = self::con()->db_con->events->getQuerySelector();
			switch ( $req->interval ) {
				case 'hourly':
					$eventSelect->filterByBoundary_Hour( $carbon->timestamp );
					$labels[] = $carbon->format( 'H' );
					$carbon->subHour();
					break;
				case 'daily':
					$eventSelect->filterByBoundary_Day( $carbon->timestamp );
					$labels[] = $carbon->format( 'Y-m-d' );
					$carbon->subDay();
					break;
				case 'weekly':
					$eventSelect->filterByBoundary_Week( $carbon->timestamp );
					$labels[] = ( clone $carbon )->startOfWeek()->format( 'Y-m-d' );
					$carbon->subWeek();
					break;
				case 'monthly':
					$eventSelect->filterByBoundary_Month( $carbon->timestamp );
					$labels[] = $carbon->format( 'Y-m' );
					$carbon->subMonth();
					break;
				case 'yearly':
					$eventSelect->filterByBoundary_Year( $carbon->timestamp );
					$labels[] = $carbon->format( 'Y' );
					$carbon->subYear();
					break;
			}

			$dataSeries[] = $eventSelect->sumEvents( $events );
		} while ( ++$tick < $req->ticks );

		if ( empty( $this->labels ) ) {
			$this->labels = $labels;
		}

		return $dataSeries;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	protected function preProcessRequest() {
		$req = $this->getChartRequest();

		if ( empty( $req->events ) ) {
			throw new \InvalidArgumentException( 'No events selected - please select at least 1 event.' );
		}

		if ( empty( $req->ticks ) ) {
			switch ( $req->interval ) {
				case 'daily':
					$req->ticks = 7;
					break;
				case 'weekly':
					$req->ticks = 8;
					break;
				default:
					$req->ticks = 12;
					break;
			}
		}
	}
}