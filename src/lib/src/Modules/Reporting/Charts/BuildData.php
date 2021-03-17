<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildData {

	use ModConsumer;

	public function build( SummaryChartRequestVO $req ) :array {
		$oDbhEvts =  $this->getCon()->getModule_Events()->getDbHandler_Events();

		$this->preProcessRequest( $req );

		$tick = 0;
		$carbon = Services::Request()->carbon();

		$labels = [];
		$aSeries = [];
		do {
			$labels[] = $carbon->toDateString();

			/** @var Events\Select $eventSelect */
			$eventSelect = $oDbhEvts->getQuerySelector();
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

			$aSeries[] = $eventSelect->sumEvents( $req->events );

			$tick++;
		} while ( $tick < $req->ticks );

		return [
			'data'         => [
				'labels' => [],
				'series' => [
					array_reverse( $aSeries ),
				]
			],
			'legend_names' => [],
		];
	}

	/**
	 * @param SummaryChartRequestVO $req
	 */
	protected function preProcessRequest( SummaryChartRequestVO $req ) {

		if ( empty( $req->interval ) ) {
			switch ( $req->render_location ) {
				case $req::LOCATION_STATCARD:
					$req->interval = 'daily';
					break;
				default:
					$req->interval = 'weekly';
					break;
			}
		}

		$allEvents = array_keys( $this->getCon()->loadEventsService()->getEvents() );
		if ( !empty( $req->chart_params[ 'stat_id' ] ) ) {
			switch ( $req->chart_params[ 'stat_id' ] ) {
				case 'comment_block':
					$req->events = array_filter(
						$allEvents,
						function ( $event ) {
							return strpos( $event, 'spam_block_' ) === 0;
						}
					);
					break;
				case 'bot_blocks':
					$req->events = array_filter(
						$allEvents,
						function ( $event ) {
							return strpos( $event, 'bottrack_' ) === 0;
						}
					);
					break;
				default:
					$req->events = (array)$req->chart_params[ 'stat_id' ];
					break;
			}
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