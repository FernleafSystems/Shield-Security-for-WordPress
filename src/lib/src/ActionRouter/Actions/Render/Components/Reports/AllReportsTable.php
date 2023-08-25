<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops as ReportDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Services\Services;

class AllReportsTable extends BaseRender {

	public const SLUG = 'render_all_reports_table';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/all_reports_table.twig';

	protected function getRenderData() :array {
		/** @var ReportDB\Select $selector */
		$selector = self::con()->getModule_Plugin()->getDbH_ReportLogs()->getQuerySelector();
		$reports = $selector->addWhere( 'unique_id', '', '!=' )->queryWithResult();
		$activeID = $this->action_data[ 'active_id' ] ?? '';
		return [
			'vars' => [
				'reports' => \array_map(
					function ( ReportDB\Record $report ) use ( $activeID ) {
						$repCon = self::con()
									  ->getModule_Plugin()
									  ->getReportingController();
						$type = $repCon->getReportTypeName( $report->type );
						return [
							'href'       => $repCon->getReportURL( $report->unique_id ),
							'is_active'  => $report->unique_id === $activeID,
							'unique_id'  => $report->unique_id,
							'type'       => $report->type === Constants::REPORT_TYPE_CUSTOM ? $type : sprintf( '%s (%s)', $type, $report->interval_length ),
							'created_at' => Services::WpGeneral()->getTimeStringForDisplay( $report->created_at ),
						];
					},
					$reports
				),
			],
		];
	}
}