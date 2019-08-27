<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oTempRs = $oAction->getNewResultsSet();

		if ( (int)$oAction->item_processing_limit > 0 ) {
			$aSlice = array_slice( $oAction->scan_items, 0, $oAction->item_processing_limit );
			$oAction->scan_items = array_slice( $oAction->scan_items, $oAction->item_processing_limit );
		}
		else {
			$aSlice = $oAction->scan_items;
			$oAction->scan_items = [];
		}

		$oAction->processed_items += count( $aSlice );

		foreach ( $aSlice as $nKey => $sItem ) {
			$oItem = $this->getItemScanner()->scan( $sItem );
			if ( $oItem instanceof Shield\Scans\Base\BaseResultItem ) {
				$oTempRs->addItem( $oItem );
			}
		}

		if ( $oTempRs->hasItems() ) {
			$aNewItems = [];
			foreach ( $oTempRs->getAllItems() as $oItem ) {
				$aNewItems[] = $oItem->getRawDataAsArray();
			}
			if ( empty( $oAction->results ) ) {
				$oAction->results = [];
			}
			$oAction->results = array_merge( $oAction->results, $aNewItems );
		}
	}

	/**
	 * @return PluginScanner
	 */
	protected function getItemScanner() {
		return ( new PluginScanner() )->setScanActionVO( $this->getScanActionVO() );
	}
}