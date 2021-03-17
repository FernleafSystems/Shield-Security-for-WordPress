<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BaseBuildChartData {

	use ModConsumer;
	use ChartRequestConsumer;

	protected function buildDataForEvents( array $events ) :array {
		$req = $this->getChartRequest();
		$dbhEvents = $this->getCon()->getModule_Events()->getDbHandler_Events();

		$tick = 0;
		$carbon = Services::Request()->carbon();

		$labels = [];
		$dataSeries = [];
		do {
			$labels[] = $carbon->toDateString();

			/** @var Events\Select $eventSelect */
			$eventSelect = $dbhEvents->getQuerySelector();
			switch ( $req->interval ) {
				case 'hourly':
					$eventSelect->filterByBoundary_Hour( $carbon->timestamp );
					$carbon->subHour();
					break;
				case 'daily':
					$eventSelect->filterByBoundary_Day( $carbon->timestamp );
					$carbon->subDay();
					break;
				case 'weekly':
					$eventSelect->filterByBoundary_Week( $carbon->timestamp );
					$carbon->subWeek();
					break;
				case 'monthly':
					$eventSelect->filterByBoundary_Month( $carbon->timestamp );
					$carbon->subMonth();
					break;
				case 'yearly':
					$eventSelect->filterByBoundary_Year( $carbon->timestamp );
					$carbon->subYear();
					break;
			}

			$dataSeries[] = $eventSelect->sumEvents( $events );

			$tick++;
		} while ( $tick < $req->ticks );

		return [
			'data'         => [
				'labels' => [],
				'series' => [
					array_reverse( $dataSeries ),
				]
			],
			'legend_names' => [],
		];
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