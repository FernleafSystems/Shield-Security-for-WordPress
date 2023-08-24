<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs\Ops as AuditDB;
use FernleafSystems\Wordpress\Services\Services;

class PageReportFormCreateNew extends BaseRender {

	public const SLUG = 'render_report_form_create_new';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/page_create_new.twig';

	protected function getRenderData() :array {
		$req = Services::Request();
		$dbh = $this->con()->getModule_AuditTrail()->getDbH_Logs();

		/** @var AuditDB\Record $firstAudit */
		$firstAudit = $dbh->getQuerySelector()
						  ->setOrderBy( 'created_at', 'ASC', true )
						  ->first();
		$lastAudit = $dbh->getQuerySelector()
						 ->setOrderBy( 'created_at', 'DESC', true )
						 ->first();

		$reportAreas = self::con()->getModule_Plugin()->getReportingController()->getReportAreas();

		$c = $req->carbon( true );
		return [
			'content' => [
				'reports_table' => self::con()->action_router->render( AllReportsTable::class ),
			],
			'ajax'    => [
				'report_create_slug' => CreateNewAdHoc::SLUG,
			],
			'flags'   => [
				'can_run_report' => !empty( $lastAudit ) && $lastAudit->id !== $firstAudit->id,
			],
			'strings' => [
				'build_report' => __( 'Create New Report', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'reporting_options' => [
					[
						'title'           => __( 'Change-Tracking Zone', 'wp-simple-firewall' ),
						'form_field_name' => 'changes_zones',
						'zones'           => $reportAreas[ 'changes' ],
					],
					[
						'title'           => __( 'Statistics', 'wp-simple-firewall' ),
						'form_field_name' => 'statistics_zones',
						'zones'           => $reportAreas[ 'statistics' ],
					],
					[
						'title'           => __( 'Scan Results', 'wp-simple-firewall' ),
						'form_field_name' => 'scans_zones',
						'zones'           => $reportAreas[ 'scans' ],
					],
				],
				'earliest_date'     => empty( $firstAudit ) ? $req->ts() :
					$c->setTimestamp( $firstAudit->created_at )->toIso8601String(),
				'latest_date'       => empty( $lastAudit ) ? $req->ts() :
					$c->setTimestamp( $lastAudit->created_at )->toIso8601String()
			],
		];
	}
}