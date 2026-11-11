<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts\{
	BuildChartData,
	ChartRequestVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\UiPreferences;

class ReportingChartTrends extends BaseAction {

	public const SLUG = 'render_chart_trends';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		try {
			$request = ( new ChartRequestVO() )->applyFromArray( $this->formParams() );
			$chart = ( new BuildChartData() )->build( $request );
			// The page opens on the chart last drawn.
			( new UiPreferences() )->setReportsTrends( $request->period_key, $request->event_keys );

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
