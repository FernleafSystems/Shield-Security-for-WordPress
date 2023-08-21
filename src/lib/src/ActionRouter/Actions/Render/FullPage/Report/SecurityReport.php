<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports\ReportVO;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\{
	ReportAreaChanges,
	ReportAreaScansCurrentResults,
	ReportAreaStats
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\BaseFullPageRender;
use FernleafSystems\Wordpress\Services\Services;

class SecurityReport extends BaseFullPageRender {

	public const SLUG = 'render_security_report';
	public const TEMPLATE = '/pages/report/security.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$WP = Services::WpGeneral();
		$report = ( new ReportVO() )->applyFromArray( $this->action_data[ 'report' ] );

		$areas = [];
		if ( \in_array( 'scans_current', $report->areas ) ) {
			$areas[ 'scans_current' ] = [
				'slug'    => 'scans_current',
				'title'   => __( 'Current Scan Results', 'wp-simple-firewall' ),
				'content' => $con->action_router->render( ReportAreaScansCurrentResults::class ),
			];
		}
		if ( !empty( $report->areas[ 'change_zones' ] ) ) {
			$areas[ 'changes' ] = [
				'slug'    => 'changes',
				'title'   => __( 'Changes', 'wp-simple-firewall' ),
				'content' => $con->action_router->render( ReportAreaChanges::class, [
					'report' => $this->action_data[ 'report' ],
				] ),
			];
		}
		if ( \in_array( 'statistics', $report->areas ) ) {
			$areas[ 'statistics' ] = [
				'slug'    => 'statistics',
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
					'report_date_start'      => $WP->getTimeStringForDisplay( $report->interval_start_at, false ),
					'report_date_end'        => $WP->getTimeStringForDisplay( $report->interval_end_at, false ),
					'report_date_full_start' => $WP->getTimeStringForDisplay( $report->interval_start_at ),
					'report_date_full_end'   => $WP->getTimeStringForDisplay( $report->interval_end_at ),
				],
				'site_url_host' => \parse_url( $WP->getHomeUrl(), \PHP_URL_HOST ),
				'areas'         => $areas,
			],
		];
	}
}