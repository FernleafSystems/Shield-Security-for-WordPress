<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

/**
 * Class ScannerFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class ScannerFromFileMap extends ScannerBase {

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oResultSet = new ResultsSet();
		/** @var MalScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( !empty( $oAction->files_map ) ) {

			if ( (int)$oAction->file_scan_limit > 0 ) {
				$aSlice = array_slice( $oAction->files_map, 0, $oAction->file_scan_limit );
			}
			else {
				$aSlice = $oAction->files_map;
			}

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