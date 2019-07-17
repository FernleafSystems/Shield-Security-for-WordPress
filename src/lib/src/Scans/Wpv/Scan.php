<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scan() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( empty( $oAction->scan_items ) ) {
			$oAction->ts_finish = Services::Request()->ts();
		}
		else {
			$this->scanSlice();
			if ( empty( $oAction->scan_items ) ) {
				$oAction->ts_finish = Services::Request()->ts();
			}
		}

		return $oAction;
	}

	protected function scanSlice() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( (int)$oAction->item_processing_limit > 0 ) {
			$aSlice = array_slice( $oAction->scan_items, 0, $oAction->item_processing_limit );
			$oAction->scan_items = array_slice( $oAction->scan_items, $oAction->item_processing_limit );
		}
		else {
			$aSlice = $oAction->scan_items;
			$oAction->scan_items = [];
		}

		$oAction->processed_items += count( $aSlice );

		$oTempRs = $oAction->getNewResultsSet();

		foreach ( $aSlice as $sFile => $sContext ) {
			$oNewRes = $this->getItemScanner( $sContext )->scan( $sFile );
			if ( $oNewRes instanceof Shield\Scans\Base\BaseResultsSet ) {
				( new Shield\Scans\Helpers\CopyResultsSets() )->copyTo( $oNewRes, $oTempRs );
			}
		}

		if ( $oTempRs->hasItems() ) {
			$aNewItems = [];
			foreach ( $oTempRs->getAllItems() as $oNewRes ) {
				$aNewItems[] = $oNewRes->getRawDataAsArray();
			}
			if ( empty( $oAction->results ) ) {
				$oAction->results = [];
			}
			$oAction->results = array_merge( $oAction->results, $aNewItems );
		}
	}

	/**
	 * @param string $sContext
	 * @return PluginScanner|ThemeScanner
	 */
	protected function getItemScanner( $sContext ) {
		if ( $sContext == 'plugins' ) {
			return ( new PluginScanner() )->setScanActionVO( $this->getScanActionVO() );
		}
		else {
			return ( new ThemeScanner() )->setScanActionVO( $this->getScanActionVO() );
		}
	}
}