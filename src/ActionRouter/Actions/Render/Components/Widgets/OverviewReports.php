<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Services\Services;

class OverviewReports extends OverviewBase {

	public const SLUG = 'render_widget_overview_reports';
	public const TEMPLATE = '/wpadmin/components/widget/overview_reports.twig';

	protected function getRenderData() :array {
		$con = self::con();
		/** @var ReportDB\Select $selector */
		$selector = $con->db_con->reports->getQuerySelector();
		$limit = \max( 1, (int)( $this->action_data[ 'limit' ] ?? 4 ) );

		$reports = \array_filter( \array_map(
			function ( ReportDB\Record $report ) {
				$repCon = self::con()->comps->reports;
				if ( empty( $report->content ) ) {
					return null;
				}

				$badgeClass = 'info';
				if ( $report->type === Constants::REPORT_TYPE_ALERT ) {
					$badgeClass = 'warning';
				}
				elseif ( $report->type === Constants::REPORT_TYPE_CUSTOM ) {
					$badgeClass = 'good';
				}

				return [
					'title'      => $this->truncate( $report->title, 60 ),
					'type_name'  => $repCon->getReportTypeName( $report->type ),
					'type_badge' => $badgeClass,
					'created_at' => Services::WpGeneral()->getTimeStringForDisplay( $report->created_at ),
					'href'       => $repCon->getReportURL( $report->unique_id ),
				];
			},
			$selector->setLimit( $limit )
					 ->setOrderBy( 'created_at', 'DESC' )
					 ->addWhere( 'unique_id', '', '!=' )
					 ->queryWithResult()
		) );

		return [
			'flags'   => [
				'has_reports' => !empty( $reports ),
			],
			'strings' => [
				'no_reports' => __( 'No reports generated yet.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'reports' => $reports,
			],
		];
	}
}
