<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\EmailBase;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportDataInspector;
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
		$detailLevel = $this->action_data[ 'detail_level' ] ?? 'detailed';

		return [
			'vars'    => [
				'reports'      => \array_map(
					function ( ReportVO $rep ) use ( $con, $WP, $detailLevel ) {
						$reportCon = $con->comps->reports;
						$areasData = $rep->areas_data;
						if ( isset( $areasData[ Constants::REPORT_AREA_STATS ] ) ) {
							$stats = ( new ReportDataInspector( $areasData ) )->getStatsForEmailDisplay( $detailLevel );
							if ( empty( $stats ) ) {
								unset( $areasData[ Constants::REPORT_AREA_STATS ] );
							}
							else {
								$areasData[ Constants::REPORT_AREA_STATS ] = $stats;
							}
						}

						$isHourly = ( $rep->interval ?? '' ) === 'hourly';
						return [
							'type'       => $reportCon->getReportTypeName( $rep->type ),
							'generated'  => $WP->getTimeStringForDisplay( $rep->record->created_at ),
							'href'       => $reportCon->getReportURL( $rep->record->unique_id ),
							'date_start' => $WP->getTimeStringForDisplay( $rep->start_at, $isHourly ),
							'date_end'   => $WP->getTimeStringForDisplay( $rep->end_at, $isHourly ),
							'areas_data' => $areasData,
							'overview'   => $this->buildOverviewSummary( $rep->areas_data ),
						];
					},
					$reports
				),
				'site_url'     => $this->action_data[ 'home_url' ],
				'report_date'  => $WP->getTimeStampForDisplay(),
				'detail_level' => $detailLevel,
			],
			'strings' => [
				'generated'             => __( 'Date Generated', 'wp-simple-firewall' ),
				'report_type'           => $common[ 'report_type_label' ],
				'view_report'           => $common[ 'view_report_label' ],
				'intro_text'            => $isAlert
					? __( 'A security alert has been generated. Review issues below and take action where needed.', 'wp-simple-firewall' )
					: __( "Here's your security overview for the reporting period. Review any issues below and take action where needed.", 'wp-simple-firewall' ),
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
				'change'                => __( 'Change', 'wp-simple-firewall' ),
				'period'                => __( 'Period', 'wp-simple-firewall' ),
				'scan_type'             => __( 'Scan Type', 'wp-simple-firewall' ),
				'count'                 => __( 'Count', 'wp-simple-firewall' ),
				'new_badge'             => __( 'NEW', 'wp-simple-firewall' ),
				'and_x_more'            => __( '... and %s more', 'wp-simple-firewall' ),
				'scan_issues'           => __( 'Scan Issues', 'wp-simple-firewall' ),
				'repairs'               => __( 'Repairs', 'wp-simple-firewall' ),
				'ip_offenses'           => __( 'IP Offenses', 'wp-simple-firewall' ),
				'all_clear'             => __( 'All clear', 'wp-simple-firewall' ),
				'auto_fixed'            => __( 'auto-fixed', 'wp-simple-firewall' ),
				'security_report'       => __( 'Security Report', 'wp-simple-firewall' ),
			]
		];
	}

	private function buildOverviewSummary( array $areasData ) :array {
		$inspector = new ReportDataInspector( $areasData );

		$scanTotal = $inspector->countScanResultsCurrent();
		$scanNew = $inspector->countScanResultsNew();

		$repairTotal = 0;
		foreach ( ( $areasData[ Constants::REPORT_AREA_SCANS ][ 'scan_repairs' ] ?? [] ) as $data ) {
			$repairTotal += $data[ 'count' ] ?? 0;
		}

		$ipStat = $areasData[ Constants::REPORT_AREA_STATS ][ 'security' ][ 'stats' ][ 'ip_offense' ] ?? null;

		$changesTotalCount = 0;
		$changesZoneCount = 0;
		foreach ( ( $areasData[ Constants::REPORT_AREA_CHANGES ] ?? [] ) as $zone ) {
			$t = $zone[ 'total' ] ?? 0;
			$changesTotalCount += $t;
			if ( $t > 0 ) {
				$changesZoneCount++;
			}
		}

		return [
			'scan_issues' => [ 'total' => $scanTotal, 'new' => $scanNew ],
			'repairs'     => [ 'total' => $repairTotal ],
			'ip_offenses' => $ipStat !== null && !( $ipStat[ 'is_zero_stat' ] ?? true ) ? [
				'current'     => $ipStat[ 'count_current_period' ],
				'diff_colour' => $ipStat[ 'diff_colour' ],
				'diff_symbol' => $ipStat[ 'diff_symbol_email' ],
				'diff_pct'    => $ipStat[ 'diff_percentage' ],
			] : null,
			'changes'     => [ 'total' => $changesTotalCount, 'zones' => $changesZoneCount ],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'home_url',
			'reports',
		];
	}
}
