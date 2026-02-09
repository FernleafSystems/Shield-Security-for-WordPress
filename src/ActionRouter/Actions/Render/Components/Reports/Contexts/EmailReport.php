<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\EmailBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportVO;
use FernleafSystems\Wordpress\Services\Services;

class EmailReport extends EmailBase {

	public const SLUG = 'email_report';
	public const TEMPLATE = '/email/reports/cron_alert_info_report.twig';

	protected function getBodyData() :array {
		$con = self::con();
		$WP = Services::WpGeneral();
		$common = CommonDisplayStrings::pick( [
			'report_type_label',
			'view_report_label',
			'site_url_label',
		] );

		$reports = $this->action_data[ 'reports' ];
		$firstReport = \reset( $reports );
		$isAlert = $firstReport instanceof ReportVO && $firstReport->type === Constants::REPORT_TYPE_ALERT;

		return [
			'vars'    => [
				'reports'      => \array_map(
					function ( ReportVO $rep ) use ( $con, $WP ) {
						$reportCon = $con->comps->reports;
						return [
							'type'       => $reportCon->getReportTypeName( $rep->type ),
							'generated'  => $WP->getTimeStringForDisplay( $rep->record->created_at ),
							'href'       => $reportCon->getReportURL( $rep->record->unique_id ),
							'date_start' => $WP->getTimeStringForDisplay( $rep->start_at, false ),
							'date_end'   => $WP->getTimeStringForDisplay( $rep->end_at, false ),
							'areas_data' => $rep->areas_data,
						];
					},
					$reports
				),
				'site_url'     => $this->action_data[ 'home_url' ],
				'report_date'  => $WP->getTimeStampForDisplay(),
				'detail_level' => $this->action_data[ 'detail_level' ] ?? 'detailed',
			],
			'strings' => [
				'generated'             => __( 'Date Generated', 'wp-simple-firewall' ),
				'report_type'           => $common[ 'report_type_label' ],
				'view_report'           => $common[ 'view_report_label' ],
				'please_find'           => $isAlert
					? __( 'A security alert has been generated for your site. New issues have been detected that may require your attention.', 'wp-simple-firewall' )
					: __( 'A periodic security report has been generated for your site.', 'wp-simple-firewall' ),
				'site_url'              => $common[ 'site_url_label' ],
				'report_date'           => __( 'Report Generation Date', 'wp-simple-firewall' ),
				'use_links'             => __( 'Please use links provided in each section to review the report details.', 'wp-simple-firewall' ),
				'section_stats'         => __( 'Statistics', 'wp-simple-firewall' ),
				'section_changes'       => __( 'Changes', 'wp-simple-firewall' ),
				'section_scans'         => __( 'Latest Scan Results', 'wp-simple-firewall' ),
				'section_scans_repairs' => __( 'Scan File Repairs', 'wp-simple-firewall' ),
				'view_full_report'      => __( 'View Full Report', 'wp-simple-firewall' ),
				'event'                 => __( 'Event', 'wp-simple-firewall' ),
				'current_period'        => __( 'Current', 'wp-simple-firewall' ),
				'previous_period'       => __( 'Previous', 'wp-simple-firewall' ),
				'trend'                 => __( 'Trend', 'wp-simple-firewall' ),
				'period'                => __( 'Period', 'wp-simple-firewall' ),
				'scan_type'             => __( 'Scan Type', 'wp-simple-firewall' ),
				'count'                 => __( 'Count', 'wp-simple-firewall' ),
				'new_badge'             => __( 'NEW', 'wp-simple-firewall' ),
				'and_x_more'            => __( '... and %s more', 'wp-simple-firewall' ),
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
