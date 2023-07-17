<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Strings;
use FernleafSystems\Wordpress\Services\Services;

class AlertScanResults extends BaseBuilderForScans {

	public const SLUG = 'alert_scan_results';
	public const TEMPLATE = '/components/reports/components/alert_scanresults.twig';

	protected function getRenderData() :array {
		$con = $this->con();

		$c = new Counts( RetrieveCount::CONTEXT_NOT_YET_NOTIFIED );
		$counts = \array_filter( [
			Apc::SCAN_SLUG => $c->countAbandoned(),
			Afs::SCAN_SLUG => $c->countThemeFiles() + $c->countPluginFiles() + $c->countMalware() + $c->countWPFiles(),
			Wpv::SCAN_SLUG => $c->countVulnerableAssets(),
		] );

		$scanCounts = [];
		if ( !empty( $counts ) ) {
			/** @var Strings $strings */
			$strings = $con->getModule_HackGuard()->getStrings();
			foreach ( $counts as $slug => $count ) {
				$scanCounts[ $slug ] = [
					'count' => $count,
					'name'  => $strings->getScanName( $slug ),
				];
			}
			$this->markAlertsAsNotified();
		}

		return [
			'flags'   => [
				'render_required' => !empty( $counts ),
			],
			'hrefs'   => [
				'view_results' => $con->plugin_urls->adminTopNav( PluginURLs::NAV_SCANS_RESULTS ),
			],
			'strings' => [
				'title'        => __( 'New Scan Results', 'wp-simple-firewall' ),
				'view_results' => __( 'Click Here To View Scan Results Details', 'wp-simple-firewall' ),
				'note_changes' => sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
					__( 'Depending on previous actions taken on the site or file system changes, these results may no longer be available to view.', 'wp-simple-firewall' ) ),

			],
			'vars'    => [
				'scan_counts' => $scanCounts
			],
		];
	}

	private function markAlertsAsNotified() {
		$this->con()
			 ->getModule_HackGuard()
			 ->getDbH_ResultItems()
			 ->getQueryUpdater()
			 ->setUpdateWheres( [
				 'notified_at' => 0,
			 ] )
			 ->setUpdateData( [
				 'notified_at' => Services::Request()->ts()
			 ] )
			 ->query();
	}
}