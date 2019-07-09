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

		$aParams = $this->getAjaxFormParams();

		/** @var Shield\Databases\Events\Handler $oDbhEvts */
		$oDbhEvts = $oMod->getDbHandler();
		/** @var Shield\Databases\Events\Select $oSelEvts */
		$oSelEvts = $oDbhEvts->getQuerySelector();
		$nDays = 0;
		$aSeries_Offsenses = [];
		$aSeries_Firewall = [];
		$aLabels = [];
		$oNow = Services::Request()->carbon();
		/** @var Shield\Databases\Events\Select $oSelEvts */
		do {
			$oSelEvts = $oDbhEvts->getQuerySelector();
			$aSeries_Offsenses[] = $oSelEvts->filterByDayBoundary( $oNow->timestamp )
											->sumEvent( 'ip_offense' );
			$oSelEvts = $oDbhEvts->getQuerySelector();
			$aSeries_Firewall[] = $oSelEvts->filterByDayBoundary( $oNow->timestamp )
										   ->sumEvent( 'firewall_block' );
			$oSelEvts = $oDbhEvts->getQuerySelector();
			$aSeries_Login[] = $oSelEvts->filterByDayBoundary( $oNow->timestamp )
										   ->sumEvent( 'login_block' );
			$aLabels[] = $oNow->toDateString();
			$oNow->subDay();
			$nDays++;
		} while ( $nDays < 7 );

		return [
			'success'    => true,
			'message'    => 'asdf',
			'chart_data' => [
				'labels' => array_reverse( $aLabels ),
				'series' => [
					array_reverse( $aSeries_Offsenses ),
					array_reverse( $aSeries_Firewall ),
					array_reverse( $aSeries_Login )
				]
			]
		];
	}
}