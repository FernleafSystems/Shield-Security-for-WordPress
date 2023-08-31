<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ReportTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Report\Ops as ReportDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Services\Services;

class ReportsTable extends BaseRender {

	public const SLUG = 'render_reports_table';
	public const TEMPLATE = '/wpadmin/components/reports/table_reports.twig';

	protected function getRenderData() :array {
		/** @var ReportDB\Select $selector */
		$selector = self::con()->getModule_Plugin()->getDbH_ReportLogs()->getQuerySelector();
		$limit = $this->action_data[ 'reports_limit' ] ?? 0;
		if ( $limit > 0 ) {
			$selector->setLimit( $limit );
		}
		$activeID = $this->action_data[ 'active_id' ] ?? '';

		$reports = \array_filter( \array_map(
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
							( $report->type === Constants::REPORT_TYPE_ALERT ? 'warning' : 'dark' ),
						'created_at' => Services::WpGeneral()->getTimeStringForDisplay( $report->created_at ),
						'actions'    => [
							'delete' => [
								'title'   => __( 'Delete' ),
								'classes' => [ 'btn-danger' ],
								'svg'     => self::con()->svgs->raw( 'trash3-fill.svg' ),
								'data'    => ActionData::Build( ReportTableAction::class, false, [
									'report_action' => 'delete',
									'rid'           => $report->id,
									'confirm'       => true,
								] ),
							],
						],
					];
			},
			$selector->addWhere( 'unique_id', '', '!=' )->queryWithResult()
		) );

		return [
			'flags' => [
				'has_reports' => \count( $reports ) > 0,
			],
			'vars'  => [
				'reports' => $reports,
			],
		];
	}
}