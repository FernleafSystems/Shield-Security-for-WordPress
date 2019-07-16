<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

/**
 * Class ScannerFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class ScannerFromFileMap extends ScannerBase {

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oResultSet = new ResultsSet();
		/** @var WcfScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( !empty( $oAction->files_map ) ) {

			if ( (int)$oAction->file_scan_limit > 0 ) {
				$aSlice = array_slice( $oAction->files_map, 0, $oAction->file_scan_limit );
			}
			else {
				$aSlice = $oAction->files_map;
			}

			$oAction->processed_items += count( $aSlice );

			foreach ( $aSlice as $nKey => $sFullPath ) {
				$oItem = $this->scanPath( $sFullPath );
				if ( $oItem instanceof ResultItem ) {
					$oResultSet->addItem( $oItem );
				}
			}
		}

		return $oResultSet;
	}
}