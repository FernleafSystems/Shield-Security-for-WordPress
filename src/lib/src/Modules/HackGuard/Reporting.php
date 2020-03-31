<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Services\Services;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildAlerts() {
		$aAlerts = [];

		/** @var Strings $oStrings */
		$oStrings = $this->getMod()->getStrings();
		$aScanNames = $oStrings->getScanNames();

		$aCounts = array_filter( $this->countForEachScan() );
		if ( !empty( $aCounts ) ) {
			foreach ( $aCounts as $sScan => $nCount ) {
				$aCounts[ $sScan ] = [
					'count' => $nCount,
					'name'  => $aScanNames[ $sScan ],
				];
			}
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/hack_protect/scan_results.twig',
				[
					'vars'    => [
						'scan_counts' => $aCounts
					],
					'strings' => [
						'title'        => __( 'New Scan Results To Report', 'wp-simple-firewall' ),
						'view_results' => __( 'Click Here To View Scan Results Details', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
						'view_results' => $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' ),
					],
				]
			);
		}

		$this->markAlertsAsNotified();

		return $aAlerts;
	}

	/**
	 * @return bool
	 */
	private function markAlertsAsNotified() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Scanner\Update $oUpdater */
		$oUpdater = $oMod->getDbHandler_ScanResults()->getQueryUpdater();
		return $oUpdater
				   ->setUpdateWheres( [
					   'ignored_at'  => 0,
					   'notified_at' => 0,
				   ] )
				   ->setUpdateData( [
					   'notified_at' => Services::Request()->ts()
				   ] )
				   ->query() !== false;
	}

	/**
	 * @return int[] - key is scan slug
	 */
	private function countForEachScan() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var Scanner\Select $oSel */
		$oSel = $oMod->getDbHandler_ScanResults()->getQuerySelector();

		$aCounts = [];

		$nFromTs = $this->getFromTS();
		$nUntilTs = $this->getUntilTS();

		foreach ( $oOpts->getScanSlugs() as $sScanSlug ) {
			$oSel->filterByScan( $sScanSlug )
				 ->filterByNotNotified()
				 ->filterByNotIgnored();
			if ( !is_null( $nFromTs ) ) {
				$oSel->filterByCreatedAt( $nFromTs, '>' );
			}
			if ( !is_null( $nUntilTs ) ) {
				$oSel->filterByCreatedAt( $nUntilTs, '<' );
			}
			$aCounts[ $sScanSlug ] = $oSel->count();
		}
		return $aCounts;
	}
}