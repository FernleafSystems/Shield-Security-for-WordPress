<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
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

			case 'render_chart_post':
				$aResponse = $this->ajaxExec_RenderChartPost();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	private function ajaxExec_RenderChart() {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();

		$aParams = $this->getAjaxFormParams();
		$oReq = new Events\Charts\ChartRequestVO();
		$oReq->render_location = $aParams[ 'render_location' ];
		$oReq->chart_params = $aParams[ 'chart_params' ];
		$aChart = ( new Events\Charts\BuildData() )
			->setMod( $oMod )
			->build( $oReq );

		return [
			'success' => true,
			'message' => 'no message',
			'chart'   => $aChart
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_RenderChartPost() {
		/** @var \ICWP_WPSF_FeatureHandler_Events $oMod */
		$oMod = $this->getMod();
		$oReq = Services::Request();
		$oChartReq = new Events\Charts\ChartRequestVO();
		$oChartReq->render_location = $oReq->post( 'render_location' );
		$oChartReq->chart_params = $oReq->post( 'chart_params' );
		error_log( var_export( $oChartReq->getRawDataAsArray(),true ) );
		$aChart = ( new Events\Charts\BuildData() )
			->setMod( $oMod )
			->build( $oChartReq );

		return [
			'success' => true,
			'message' => 'no message',
			'chart'   => $aChart
		];
	}
}