<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	ReportDataInspector,
	ResolveReportViewContracts
};

class EmailReportInfo extends EmailReportBase {

	public const SLUG = 'email_report_info';
	public const TEMPLATE = '/email/reports/info_report.twig';

	protected function getBodyData() :array {
		$report = $this->report();
		$areasData = $report->areas_data;
		$inspector = new ReportDataInspector( $areasData );

		$stats = $inspector->getStatsForEmailDisplay( $this->detailLevel() );
		if ( empty( $stats ) ) {
			unset( $areasData[ Constants::REPORT_AREA_STATS ] );
		}
		else {
			$areasData[ Constants::REPORT_AREA_STATS ] = $stats;
		}

		$changes = $inspector->getChangesForEmailDisplay();
		if ( empty( $changes ) ) {
			unset( $areasData[ Constants::REPORT_AREA_CHANGES ] );
		}
		else {
			$areasData[ Constants::REPORT_AREA_CHANGES ] = $changes;
		}

		$scanRepairs = $inspector->getScanRepairsForEmailDisplay();
		if ( empty( $scanRepairs ) ) {
			unset( $areasData[ Constants::REPORT_AREA_SCANS ][ 'scan_repairs' ] );
		}
		else {
			$areasData[ Constants::REPORT_AREA_SCANS ][ 'scan_repairs' ] = $scanRepairs;
		}

		return [
			'vars'    => [
				'site_url'       => $this->action_data[ 'home_url' ],
				'report'         => \array_merge(
					$this->buildReportMeta( $report ),
					[ 'areas_data' => $areasData ]
				),
				'info_headline'  => ( new ResolveReportViewContracts() )->infoHeadline( $report ),
				'detail_level'   => $this->detailLevel(),
			],
			'strings' => \array_merge(
				$this->commonStrings(),
				[
					'intro_text'            => __( 'Current alert status is shown first, followed by detailed security activity for this reporting period.', 'wp-simple-firewall' ),
					'section_stats'         => __( 'Statistics', 'wp-simple-firewall' ),
					'section_changes'       => __( 'Changes', 'wp-simple-firewall' ),
					'section_scans'         => __( 'Latest Scan Results', 'wp-simple-firewall' ),
					'section_scans_repairs' => __( 'Scan File Repairs', 'wp-simple-firewall' ),
					'event'                 => __( 'Event', 'wp-simple-firewall' ),
					'current_period'        => __( 'Current', 'wp-simple-firewall' ),
					'previous_period'       => __( 'Previous', 'wp-simple-firewall' ),
					'change'                => __( 'Change', 'wp-simple-firewall' ),
					'all_clear'             => __( 'All clear', 'wp-simple-firewall' ),
				]
			),
		];
	}
}
