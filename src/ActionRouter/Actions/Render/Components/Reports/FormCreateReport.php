<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Services\Services;

class FormCreateReport extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'form_create_report';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/form_create_report.twig';

	protected function getRenderData() :array {
		$reportCon = self::con()->comps->reports;
		$reportAreas = $reportCon->getReportAreas();
		return Services::DataManipulation()->mergeArraysRecursive(
			$reportCon->getCreateReportFormVars(),
			[
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
							'title'           => __( 'Scans', 'wp-simple-firewall' ),
							'form_field_name' => 'scans_zones',
							'zones'           => $reportAreas[ Constants::REPORT_AREA_SCANS ],
						],
					],
				],
			]
		);
	}
}