<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oTempRs = $oAction->getNewResultsSet();

		foreach ( $oAction->items as $nKey => $sItem ) {
			$oItem = $this->getItemScanner()->scan( $sItem );
			if ( $oItem instanceof Shield\Scans\Base\ResultItem ) {
				$oTempRs->addItem( $oItem );
			}
		}

		$aNewItems = [];
		if ( $oTempRs->hasItems() ) {
			foreach ( $oTempRs->getAllItems() as $oItem ) {
				$aNewItems[] = $oItem->getRawData();
			}
		}
		$oAction->results = $aNewItems;
	}

	/**
	 * @return PluginScanner
	 */
	protected function getItemScanner() {
		return ( new PluginScanner() )->setScanActionVO( $this->getScanActionVO() );
	}
}