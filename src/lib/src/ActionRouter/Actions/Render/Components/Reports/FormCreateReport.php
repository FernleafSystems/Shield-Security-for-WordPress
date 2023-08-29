<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ReportCreateCustom;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs\Ops as AuditDB;

class FormCreateReport extends BaseRender {

	public const SLUG = 'form_create_report';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/form_create_report.twig';

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
			'ajax'    => [
				'create_report' => ActionData::BuildJson( ReportCreateCustom::class ),
			],
			'flags'   => [
				'can_run_report' => !empty( $lastAudit ) && $lastAudit->id !== $firstAudit->id,
			],
			'strings' => [
				'build_report'      => __( 'Create Report', 'wp-simple-firewall' ),
				'descriptive_title' => __( 'Provide a descriptive title', 'wp-simple-firewall' ),
				'date_range'        => __( 'Date Range', 'wp-simple-firewall' ),
				'date_start'        => __( 'Start Date', 'wp-simple-firewall' ),
				'date_end'          => __( 'End Date', 'wp-simple-firewall' ),
				'form_title'        => __( 'Report Options', 'wp-simple-firewall' ),
				'report_title'      => __( 'Report Title', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'reporting_options' => [
					[
						'title'           => __( 'Change-Tracking', 'wp-simple-firewall' ),
						'form_field_name' => 'changes_zones',
						'zones'           => $reportAreas[ Constants::REPORT_AREA_CHANGES ],
					],
					[
						'title'           => __( 'Statistics', 'wp-simple-firewall' ),
						'form_field_name' => 'statistics_zones',
						'zones'           => $reportAreas[ Constants::REPORT_AREA_STATS ],
					],
					[
						'title'           => __( 'Scan Results', 'wp-simple-firewall' ),
						'form_field_name' => 'scans_zones',
						'zones'           => $reportAreas[ Constants::REPORT_AREA_SCANS ],
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