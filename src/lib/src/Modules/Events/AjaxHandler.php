<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {

		switch ( $sAction ) {
			case 'render_chart':
				$aResponse = $this->ajaxExec_RenderChart();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	public function ajaxExec_RenderChart() {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		/** @var Strings $oStrs */
		$oStrs = $oMod->getStrings();

		$aParams = $this->getAjaxFormParams();
		$sEvent = $aParams[ 'event' ];

		/** @var Shield\Databases\Events\Handler $oDbhEvts */
		$oDbhEvts = $oMod->getDbHandler();
		$nDays = 0;
		$aSeries = [];
		$aLabels = [];
		$oNow = Services::Request()->carbon();

		do {
			/** @var Shield\Databases\Events\Select $oSelEvts */
			$oSelEvts = $oDbhEvts->getQuerySelector();
			$aSeries[] = $oSelEvts->filterByBoundary_Day( $oNow->timestamp )
								  ->sumEvent( $sEvent );
			$aLabels[] = $oNow->toDateString();
			$oNow->subDay();
			$nDays++;
		} while ( $nDays < 7 );

		return [
			'success' => true,
			'message' => 'asdf',
			'chart'   => [
				'data'         => [
					'labels' => array_reverse( $aLabels ),
					'series' => [
						array_reverse( $aSeries ),
					]
				],
				'legend_names' => [ 'Total Offenses' ],
			]
		];
	}
}