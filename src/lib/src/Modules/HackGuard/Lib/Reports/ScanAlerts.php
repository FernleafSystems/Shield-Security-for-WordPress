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

		/** @var HackGuard\Strings $oStrings */
		$oStrings = $this->getMod()->getStrings();
		$aScanNames = $oStrings->getScanNames();

		$aScanItemCounts = array_filter( $this->countItemsForEachScan() );
		if ( !empty( $aScanItemCounts ) ) {
			foreach ( $aScanItemCounts as $sScan => $nCount ) {
				$aScanItemCounts[ $sScan ] = [
					'count' => $nCount,
					'name'  => $aScanNames[ $sScan ],
				];
			}
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/hack_protect/alert_scanresults.twig',
				[
					'vars'    => [
						'scan_counts' => $aScanItemCounts
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Scanner\Update $oUpdater */
		$oUpdater = $oMod->getDbHandler_ScanResults()->getQueryUpdater();
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

	/**
	 * @return int[] - key is scan slug
	 */
	private function countItemsForEachScan() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var Scanner\Select $oSel */
		$oSel = $oMod->getDbHandler_ScanResults()->getQuerySelector();

		$aCounts = [];

		$oRep = $this->getReport();

		foreach ( $oOpts->getScanSlugs() as $sScanSlug ) {
			$oSel->filterByScan( $sScanSlug )
				 ->filterByNotNotified()
				 ->filterByNotIgnored();
			if ( !is_null( $oRep->interval_start_at ) ) {
				$oSel->filterByCreatedAt( $oRep->interval_start_at, '>' );
			}
			if ( !is_null( $oRep->interval_end_at ) ) {
				$oSel->filterByCreatedAt( $oRep->interval_end_at, '<' );
			}
			$aCounts[ $sScanSlug ] = $oSel->count();
		}
		return $aCounts;
	}
}