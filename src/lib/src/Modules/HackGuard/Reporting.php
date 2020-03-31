<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildAlerts() {
		$aAlerts = [];

		$oMod = $this->getMod();
		/** @var Strings $oStrings */
		$oStrings = $oMod->getStrings();
		$aScanNames = $oStrings->getScanNames();

		$aCounts = array_filter( $this->countForEachScan() );
		if ( !empty( $aCounts ) ) {
			foreach ( $aCounts as $sScan => $nCount ) {
				$aCounts[ $sScan ] = [
					'count' => $nCount,
					'name'  => $aScanNames[ $sScan ],
				];
			}
			$aAlerts[] = $oMod->renderTemplate(
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

		return $aAlerts;
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