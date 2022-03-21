<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'render_custom_chart'  => [ $this, 'ajaxExec_RenderCustomChart' ],
				'render_summary_chart' => [ $this, 'ajaxExec_RenderSummaryChart' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_RenderCustomChart() :array {
		return $this->renderChart( FormParams::Retrieve() );
	}

	public function ajaxExec_RenderSummaryChart() :array {
		return $this->renderChart( $_POST );
	}

	private function renderChart( array $data ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
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

		return [
			'success' => $success,
			'message' => $msg,
			'chart'   => $chartData
		];
	}
}