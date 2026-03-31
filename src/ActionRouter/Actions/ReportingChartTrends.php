<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\{
	BuildChartData,
	ChartRequestVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class ReportingChartTrends extends BaseAction {

	public const SLUG = 'render_chart_trends';

	protected function exec() {
		try {
			$chart = ( new BuildChartData() )
				->build( ( new ChartRequestVO() )->applyFromArray( FormParams::Retrieve() ) );

			$this->response()
				 ->setPayload( [
					 'message' => '',
					 'chart'   => $chart,
				 ] )
				 ->setPayloadSuccess( true );
		}
		catch ( \Throwable $e ) {
			$this->response()
				 ->setPayload( [
					 'message' => sprintf( '%s: %s', __( 'Error', 'wp-simple-firewall' ), $e->getMessage() ),
					 'chart'   => [],
				 ] )
				 ->setPayloadSuccess( false );
		}
	}
}
