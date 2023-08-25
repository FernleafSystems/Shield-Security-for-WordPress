<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\{
	ReportAreaChanges,
	ReportAreaScansResults,
	ReportAreaStats
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\BaseFullPageRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportVO;
use FernleafSystems\Wordpress\Services\Services;

class SecurityReport extends BaseFullPageRender {

	use AuthNotRequired;

	public const SLUG = 'render_security_report';
	public const TEMPLATE = '/pages/report/security.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$WP = Services::WpGeneral();
		$report = ( new ReportVO() )->applyFromArray( $this->action_data[ 'report' ] );

		$areas = [];
		if ( !empty( $report->areas[ Constants::REPORT_AREA_SCANS ] ) ) {
			if ( \in_array( 'new', $report->areas[ Constants::REPORT_AREA_SCANS ] ) ) {
				$areas[ 'scans_new' ] = [
					'slug'    => 'scans_new',
					'title'   => __( 'New Scan Results', 'wp-simple-firewall' ),
					'content' => $con->action_router->render( ReportAreaScansResults::class, [
						'report'       => $this->action_data[ 'report' ],
						'results_type' => 'new'
					] ),
				];
			}
			if ( \in_array( 'current', $report->areas[ Constants::REPORT_AREA_SCANS ] ) ) {
				$areas[ 'scans_current' ] = [
					'slug'    => 'scans_current',
					'title'   => __( 'Current Scan Results', 'wp-simple-firewall' ),
					'content' => $con->action_router->render( ReportAreaScansResults::class, [
						'report'       => $this->action_data[ 'report' ],
						'results_type' => 'current'
					] ),
				];
			}
		}
		if ( !empty( $report->areas[ Constants::REPORT_AREA_CHANGES ] ) ) {
			$areas[ Constants::REPORT_AREA_CHANGES ] = [
				'slug'    => Constants::REPORT_AREA_CHANGES,
				'title'   => __( 'Changes', 'wp-simple-firewall' ),
				'content' => $con->action_router->render( ReportAreaChanges::class, [
					'report' => $this->action_data[ 'report' ],
				] ),
			];
		}
		if ( !empty( $report->areas[ Constants::REPORT_AREA_STATS ] ) ) {
			$areas[ Constants::REPORT_AREA_STATS ] = [
				'slug'    => Constants::REPORT_AREA_STATS,
				'title'   => __( 'Statistics', 'wp-simple-firewall' ),
				'content' => $con->action_router->render( ReportAreaStats::class, [
					'report' => $this->action_data[ 'report' ],
				] ),
			];
		}

		return [
			'hrefs'   => [
			],
			'strings' => [
				'report_header_title' => sprintf( __( '%s Website Security Report', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'vars'    => [
				'dates'         => [
					'generation_date'        => $WP->getTimeStringForDisplay( null, false ),
					'generation_time'        => $WP->getTimeStringForDisplay(),
					'report_date_start'      => $WP->getTimeStringForDisplay( $report->start_at, false ),
					'report_date_end'        => $WP->getTimeStringForDisplay( $report->end_at, false ),
					'report_date_full_start' => $WP->getTimeStringForDisplay( $report->start_at ),
					'report_date_full_end'   => $WP->getTimeStringForDisplay( $report->end_at ),
				],
				'site_url_host' => \parse_url( $WP->getHomeUrl(), \PHP_URL_HOST ),
				'areas'         => $areas,
			],
		];
	}
}