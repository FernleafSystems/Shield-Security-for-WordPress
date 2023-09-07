<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Services\Services;

class ReportAreaScansRepairs extends ReportAreaBase {

	public const SLUG = 'report_area_scans_repairs';
	public const TEMPLATE = '/reports/areas/scans_repairs.twig';

	protected function getRenderData() :array {

		$repairs = $this->report()->areas_data[ Constants::REPORT_AREA_SCANS ][ $this->action_data[ 'results_type' ] ];
		$total = 0;
		foreach ( $repairs as $repair ) {
			$total += $repair[ 'count' ];
		}

		return [
			'flags'   => [
				'has_repairs' => $total > 0,
			],
			'hrefs'   => [
				'view_scan_results' => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ),
			],
			'strings' => [
				'view_scan_results' => __( 'View Scan Results In-Full', 'wp-simple-firewall' ),
				'no_repairs'        => __( 'There are no recently repaired files.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'scan_repairs'    => $repairs,
				'generation_date' => Services::WpGeneral()->getTimeStringForDisplay(),
			],
		];
	}
}