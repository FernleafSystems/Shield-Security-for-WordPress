<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\EmailBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportVO;
use FernleafSystems\Wordpress\Services\Services;

class EmailReport extends EmailBase {

	public const SLUG = 'email_report';
	public const TEMPLATE = '/email/reports/cron_alert_info_report.twig';

	protected function getBodyData() :array {
		$common = CommonDisplayStrings::pick( [
			'report_type_label',
			'view_report_label',
			'site_url_label'
		] );
		return [
			'vars'    => [
				'reports'     => \array_map(
					function ( ReportVO $rep ) {
						$reportCon = self::con()->comps->reports;
						return [
							'type'      => $reportCon->getReportTypeName( $rep->type ),
							'generated' => Services::WpGeneral()->getTimeStringForDisplay( $rep->record->created_at ),
							'href'      => $reportCon->getReportURL( $rep->record->unique_id ),
						];
					},
					$this->action_data[ 'reports' ]
				),
				'site_url'    => $this->action_data[ 'home_url' ],
				'report_date' => Services::WpGeneral()->getTimeStampForDisplay(),
			],
			'strings' => [
				'generated'   => __( 'Date Generated', 'wp-simple-firewall' ),
				'report_type' => $common[ 'report_type_label' ],
				'view_report' => $common[ 'view_report_label' ],
				'please_find' => __( 'At least 1 security report has been generated for your site.', 'wp-simple-firewall' ),
				'depending'   => __( 'Depending on your settings, these reports may contain a combination of alerts, statistics and other information.', 'wp-simple-firewall' ),
				'site_url'    => $common[ 'site_url_label' ],
				'report_date' => __( 'Report Generation Date', 'wp-simple-firewall' ),
				'use_links'   => __( 'Please use links provided in each section to review the report details.', 'wp-simple-firewall' ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'home_url',
			'reports',
		];
	}
}
