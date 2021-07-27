<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;
use FernleafSystems\Wordpress\Services\Services;

class ScanAlerts extends BaseReporter {

	public function build() :array {
		$alerts = [];

		/** @var HackGuard\Strings $strings */
		$strings = $this->getMod()->getStrings();

		$rep = $this->getReport();
		$scanCounts = array_filter(
			( new Query\ScanCounts( $rep->interval_start_at, $rep->interval_end_at ) )
				->setMod( $this->getMod() )
				->standard()
		);

		if ( !empty( $scanCounts ) ) {
			foreach ( $scanCounts as $slug => $count ) {
				$scanCounts[ $slug ] = [
					'count' => $count,
					'name'  => $strings->getScanNames()[ $slug ],
				];
			}
			$alerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/hack_protect/alert_scanresults.twig',
				[
					'vars'    => [
						'scan_counts' => $scanCounts
					],
					'strings' => [
						'title'        => __( 'New Scan Results', 'wp-simple-firewall' ),
						'view_results' => __( 'Click Here To View Scan Results Details', 'wp-simple-firewall' ),
						'note_changes' => sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
							__( 'Depending on previous actions taken on the site or file system changes, these results may no longer be available to view.', 'wp-simple-firewall' ) ),

					],
					'hrefs'   => [
						'view_results' => $this->getCon()
											   ->getModule_Insights()
											   ->getUrl_ScansResults(),
					],
				]
			);

			$this->markAlertsAsNotified();
		}

		return $alerts;
	}

	private function markAlertsAsNotified() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Scanner\Update $updater */
		$updater = $mod->getDbHandler_ScanResults()->getQueryUpdater();
		$updater
			->setUpdateWheres( [
				'ignored_at'  => 0,
				'notified_at' => 0,
			] )
			->setUpdateData( [
				'notified_at' => Services::Request()->ts()
			] )
			->query();
	}
}