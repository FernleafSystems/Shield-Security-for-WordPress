<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Services\Services;

class CreateNewAdHoc extends BaseRender {

	public const SLUG = 'report_create_new_adhoc';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/display_new_adhoc.twig';

	protected function getRenderData() :array {
		$reportCon = self::con()->getModule_Plugin()->getReportingController();
		$form = FormParams::Retrieve();
		$reportID = $reportCon->newReport(
			$this->start(),
			$this->end(),
			[
				'areas' => [
					'scans_current' => ( $form[ 'include_scan_current_summary' ] ?? false ) === 'Y',
					'change_zones'  => $form[ 'change_zones' ] ?? [],
					'statistics'    => ( $form[ 'include_statistics' ] ?? false ) === 'Y',
				]
			]
		);
		return [
			'hrefs'   => [
				'view_report' => $reportCon->viewReportURL( $reportID ),
			],
			'strings' => [
				'title'    => __( 'Important Alerts', 'wp-simple-firewall' ),
				'subtitle' => __( 'The following is a collection of the latest alerts since your previous report.', 'wp-simple-firewall' ),
			],
		];
	}

	private function start() :int {
		$date = \explode( '-', FormParams::Retrieve()[ 'start_date' ] );
		return Services::Request()
					   ->carbon( true )
					   ->setDate( $date[ 0 ], $date[ 1 ], $date[ 2 ] )
					   ->startOfDay()->timestamp;
	}

	private function end() :int {
		$date = \explode( '-', FormParams::Retrieve()[ 'end_date' ] );
		return Services::Request()
					   ->carbon( true )
					   ->setDate( $date[ 0 ], $date[ 1 ], $date[ 2 ] )
					   ->endOfDay()->timestamp;
	}
}