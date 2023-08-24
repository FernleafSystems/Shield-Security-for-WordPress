<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportGenerator;
use FernleafSystems\Wordpress\Services\Services;

class CreateNewAdHoc extends BaseRender {

	public const SLUG = 'report_create_new_adhoc';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/display_new_adhoc.twig';

	protected function getRenderData() :array {
		$form = FormParams::Retrieve();
		$reportID = ( new ReportGenerator() )->adHoc(
			$this->start(),
			$this->end(),
			[
				'areas' => [
					'changes'    => $form[ 'changes_zones' ] ?? [],
					'statistics' => $form[ 'statistics_zones' ] ?? [],
					'scans'      => $form[ 'scans_zones' ] ?? [],
				]
			]
		);
		return [
			'content' => [
				'reports_table' => self::con()->action_router->render( AllReportsTable::class, [
					'active_id' => $reportID,
				] ),
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