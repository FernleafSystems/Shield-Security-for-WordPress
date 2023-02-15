<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

abstract class ReportingBase extends BaseAction {

	protected function renderChart( array $data ) {
		try {
			$chartData = ( new Charts\CustomChartData() )
				->setMod( $this->getCon()->getModule_Plugin() )
				->setChartRequest( ( new Charts\CustomChartRequestVO() )->applyFromArray( $data ) )
				->build();
			$msg = 'No message';
			$success = true;
		}
		catch ( \Exception $e ) {
			$msg = sprintf( '%s: %s', __( 'Error', 'wp-simple-firewall' ), $e->getMessage() );
			$success = false;
			$chartData = [];
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
			'chart'   => $chartData
		];
	}
}