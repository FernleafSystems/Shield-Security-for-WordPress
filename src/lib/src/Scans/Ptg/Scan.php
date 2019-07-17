<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class Scan extends Shield\Scans\Base\BaseScan {

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

		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();
		$oItemScanner = $this->getItemScanner();
		foreach ( $aSlice as $sFile => $sContext ) {

			if ( $sContext == 'plugins' ) {
				$sRootDir = $oWpPlugins->getInstallationDir( $sFile );
				$aHashes = $oAction->existing_hashes_plugins[ $sFile ];
			}
			else {
				$sRootDir = $oWpThemes->getInstallationDir( $sFile );
				$aHashes = $oAction->existing_hashes_themes[ $sFile ];
			}

			$oNewRes = $oItemScanner->scan( $sRootDir, $aHashes );
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
	 * @return ItemScanner
	 */
	protected function getItemScanner() {
		return ( new ItemScanner() )->setScanActionVO( $this->getScanActionVO() );
	}
}