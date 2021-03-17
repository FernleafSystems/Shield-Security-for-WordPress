<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'render_custom_chart':
				$response = $this->ajaxExec_RenderCustomChart();
				break;

			case 'render_summary_chart':
				$response = $this->ajaxExec_RenderSummaryChart();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_RenderCustomChart() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$params = $this->getAjaxFormParams();
		try {
			$chartData = ( new Charts\CustomChartData() )
				->setMod( $mod )
				->setChartRequest( ( new Charts\CustomChartRequestVO() )->applyFromArray( $params ) )
				->build();
			$msg = 'No message';
			$success = true;
		}
		catch ( \Exception $e ) {
			$msg = sprintf( '%s: %s', __( 'Error', 'wp-simple-firewall' ), $e->getMessage() );
			$success = false;
			$chartData = [];
		}

		return [
			'success' => $success,
			'message' => $msg,
			'chart'   => $chartData
		];
	}

	private function ajaxExec_RenderSummaryChart() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			$chartData = ( new Charts\SummaryChartData() )
				->setMod( $mod )
				->setChartRequest( ( new Charts\SummaryChartRequestVO() )->applyFromArray( $_POST ) )
				->build();
			$msg = 'No message';
			$success = true;
		}
		catch ( \Exception $e ) {
			$msg = sprintf( '%s: %s', __( 'Error', 'wp-simple-firewall' ), $e->getMessage() );
			$success = false;
			$chartData = [];
		}

		return [
			'success' => $success,
			'message' => $msg,
			'chart'   => $chartData
		];
	}
}