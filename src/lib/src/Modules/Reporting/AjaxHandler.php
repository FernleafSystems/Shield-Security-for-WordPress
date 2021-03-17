<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'render_chart':
				$response = $this->ajaxExec_RenderChart();
				break;

			case 'render_summary_chart':
				$response = $this->ajaxExec_RenderSummaryChart();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_RenderChart() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$aParams = $this->getAjaxFormParams();
		$chartReq = new Charts\SummaryChartRequestVO();
		$chartReq->render_location = $aParams[ 'render_location' ];
		$chartReq->chart_params = $aParams[ 'chart_params' ];

		return [
			'success' => true,
			'message' => 'no message',
			'chart'   => ( new Charts\BuildData() )
				->setMod( $mod )
				->build( $chartReq )
		];
	}

	private function ajaxExec_RenderSummaryChart() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();
		$oChartReq = new Charts\SummaryChartRequestVO();
		$oChartReq->render_location = $req->post( 'render_location' );
		$oChartReq->chart_params = $req->post( 'chart_params' );

		$aChart = ( new Charts\BuildData() )
			->setMod( $mod )
			->build( $oChartReq );

		return [
			'success' => true,
			'message' => 'no message',
			'chart'   => $aChart
		];
	}
}