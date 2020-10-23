<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;
use FernleafSystems\Wordpress\Services\Services;

class ScanAlerts extends BaseReporter {

	/**
	 * @inheritDoc
	 */
	public function build() {
		$aAlerts = [];

		/** @var HackGuard\Strings $strings */
		$strings = $this->getMod()->getStrings();

		$rep = $this->getReport();
		$scanCounts = array_filter(
			( new HackGuard\Lib\Reports\Query\CountsPerScan() )
				->setMod( $this->getMod() )
				->run( $rep->interval_start_at, $rep->interval_end_at )
		);

		if ( !empty( $scanCounts ) ) {
			foreach ( $scanCounts as $slug => $count ) {
				$scanCounts[ $slug ] = [
					'count' => $count,
					'name'  => $strings->getScanNames()[ $slug ],
				];
			}
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/hack_protect/alert_scanresults.twig',
				[
					'vars'    => [
						'scan_counts' => $scanCounts
					],
					'strings' => [
						'title'        => __( 'New Scan Results', 'wp-simple-firewall' ),
						'view_results' => __( 'Click Here To View Scan Results Details', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
						'view_results' => $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' ),
					],
				]
			);

			$this->markAlertsAsNotified();
		}

		return $aAlerts;
	}

	private function markAlertsAsNotified() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $mod */
		$mod = $this->getMod();
		/** @var Scanner\Update $oUpdater */
		$oUpdater = $mod->getDbHandler_ScanResults()->getQueryUpdater();
		$oUpdater
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