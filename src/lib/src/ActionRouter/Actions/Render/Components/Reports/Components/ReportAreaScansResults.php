<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForScans;
use FernleafSystems\Wordpress\Services\Services;

class ReportAreaScansResults extends ReportAreaBase {

	public const SLUG = 'report_area_scans_results';
	public const TEMPLATE = '/reports/areas/scans/scans_results.twig';

	protected function getRenderData() :array {

		$scanCounts = ( new BuildForScans() )->build();

		$totalResults = 0;
		foreach ( $scanCounts as $scanCount ) {
			$totalResults += $scanCount[ 'count' ];
		}

		return [
			'flags'   => [
				'has_results' => $totalResults > 0,
			],
			'hrefs'   => [
				'view_scan_results' => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS_RESULTS ),
			],
			'strings' => [
				'view_scan_results' => __( 'View Scan Results In-Full', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'scan_counts'     => $scanCounts,
				'generation_date' => Services::WpGeneral()->getTimeStringForDisplay(),
			],
		];
	}
}