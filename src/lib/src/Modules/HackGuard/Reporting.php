<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;

class Reporting extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildAlerts( $nFromTs = null, $nUntilTs = null ) {
		$aAlerts = [];

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_ScanResults();
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		/** @var Scanner\Select $oSel */
		$oSel = $oDbH->getQuerySelector();
		foreach ( $oOpts->getScanSlugs() as $sScanSlug ) {
			$oScanCon = $oMod->getScanCon( $sScanSlug );
			$oSel->filterByScan( $sScanSlug )
				 ->filterByNotNotified()
				 ->filterByNotIgnored();
			if ( is_int( $nFromTs ) && $nFromTs >= 0 ) {
				$oSel->filterByCreatedAt( $nFromTs, '>' );
			}
			if ( is_int( $nUntilTs ) && $nUntilTs >= 0 ) {
				$oSel->filterByCreatedAt( $nUntilTs, '<' );
			}
			$nCount = $oSel->count();
			if ( $nCount > 0 ) {
				$aAlerts[] = $sScanSlug.': '.$nCount;
			}
		}

		return $aAlerts;
	}
}