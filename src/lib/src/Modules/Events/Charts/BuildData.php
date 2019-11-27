<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Charts;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

class BuildData {

	use ModConsumer;

	/**
	 * @param ChartRequestVO $oReq
	 * @return array
	 */
	public function build( ChartRequestVO $oReq ) {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();

		$this->preProcessRequest( $oReq );

		/** @var Events\Handler $oDbhEvts */
		$oDbhEvts = $oMod->getDbHandler_Events();

		$nTick = 0;
		$oTime = new Carbon();

		$aLabels = [];
		$aSeries = [];
		do {
			$aLabels[] = $oTime->toDateString();

			/** @var Events\Select $oSelEvts */
			$oSelEvts = $oDbhEvts->getQuerySelector();
			switch ( $oReq->interval ) {
				case 'hourly':
					$oSelEvts->filterByBoundary_Hour( $oTime->timestamp );
					$oTime->subHour();
					break;
				case 'daily':
					$oSelEvts->filterByBoundary_Day( $oTime->timestamp );
					$oTime->subDay();
					break;
				case 'weekly':
					$oSelEvts->filterByBoundary_Week( $oTime->timestamp );
					$oTime->subWeek();
					break;
				case 'monthly':
					$oSelEvts->filterByBoundary_Month( $oTime->timestamp );
					$oTime->subMonth();
					break;
				case 'yearly':
					$oSelEvts->filterByBoundary_Year( $oTime->timestamp );
					$oTime->subYear();
					break;
			}

			$aSeries[] = $oSelEvts->sumEvents( $oReq->events );

			$nTick++;
		} while ( $nTick < $oReq->ticks );

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
	 * @param ChartRequestVO $oReq
	 */
	protected function preProcessRequest( ChartRequestVO $oReq ) {

		if ( empty( $oReq->interval ) ) {
			switch ( $oReq->render_location ) {
				case $oReq::LOCATION_STATCARD:
					$oReq->interval = 'daily';
					break;
				default:
					$oReq->interval = 'weekly';
					break;
			}
		}

		$aAll = array_keys( $this->getCon()->getAllEvents() );
		if ( !empty( $oReq->chart_params[ 'stat_id' ] ) ) {
			switch ( $oReq->chart_params[ 'stat_id' ] ) {
				case 'comment_block':
					$oReq->events = array_filter(
						$aAll,
						function ( $sEvent ) {
							return strpos( $sEvent, 'spam_block_' ) === 0;
						}
					);
					break;
				case 'bot_blocks':
					$oReq->events = array_filter(
						$aAll,
						function ( $sEvent ) {
							return strpos( $sEvent, 'bottrack_' ) === 0;
						}
					);
					break;
				default:
					$oReq->events = (array)$oReq->chart_params[ 'stat_id' ];
					break;
			}
		}

		if ( empty( $oReq->ticks ) ) {
			switch ( $oReq->interval ) {
				case 'daily':
					$oReq->ticks = 14;
					break;
				case 'weekly':
					$oReq->ticks = 12;
					break;
				default:
					$oReq->ticks = 10;
					break;
			}
		}
	}
}