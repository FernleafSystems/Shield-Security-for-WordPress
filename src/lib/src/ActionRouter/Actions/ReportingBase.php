<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\ModCon;

abstract class ReportingBase extends BaseAction {

	public const PRIMARY_MOD = 'reporting';

	protected function renderChart( array $data ) {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		try {
			$chartData = ( new Charts\CustomChartData() )
				->setMod( $mod )
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