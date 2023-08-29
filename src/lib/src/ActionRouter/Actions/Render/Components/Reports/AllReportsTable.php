<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops as ReportDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Services\Services;

class AllReportsTable extends BaseRender {

	public const SLUG = 'render_all_reports_table';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/table_all_reports.twig';

	protected function getRenderData() :array {
		/** @var ReportDB\Select $selector */
		$selector = self::con()->getModule_Plugin()->getDbH_ReportLogs()->getQuerySelector();
		$activeID = $this->action_data[ 'active_id' ] ?? '';
		return [
			'hrefs'   => [
				'create_custom_report' => self::con()->plugin_urls->offCanvasTrigger( 'renderReportCreate()' ),
			],
			'strings' => [
				'create_custom_report' => __( 'Create Custom Report', 'wp-simple-firewall' ),
				'view_report'          => __( 'View Report', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'reports' => \array_filter( \array_map(
					function ( ReportDB\Record $report ) use ( $activeID ) {
						$repCon = self::con()
									  ->getModule_Plugin()
									  ->getReportingController();
						return empty( $report->content ) ? null
							: [
								'href'       => $repCon->getReportURL( $report->unique_id ),
								'is_active'  => $report->unique_id === $activeID,
								'unique_id'  => $report->unique_id,
								'type'       => $repCon->getReportTypeName( $report->type ),
								'title'      => $report->title,
								'class'      => $report->type === Constants::REPORT_TYPE_INFO ? 'info' :
									( $report->type === Constants::REPORT_TYPE_ALERT ? 'warning' : 'light' ),
								'created_at' => Services::WpGeneral()->getTimeStringForDisplay( $report->created_at ),
							];
					},
					$selector->addWhere( 'unique_id', '', '!=' )->queryWithResult()
				) ),
			],
		];
	}
}