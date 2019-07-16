<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BaseFileAsyncScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files
 */
abstract class BaseFileMapScan extends Base\BaseScan {

	/**
	 * @return Base\ScanActionVO
	 * @throws \Exception
	 */
	protected function scan() {
		/** @var FileScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( empty( $oAction->files_map ) ) {
			$oAction->ts_finish = Services::Request()->ts();
		}
		else {
			$this->scanFileMapSlice();
			if ( empty( $oAction->files_map ) ) {
				$oAction->ts_finish = Services::Request()->ts();
			}
		}

		return $oAction;
	}

	/**
	 * @return $this
	 */
	protected function scanFileMapSlice() {
		/** @var FileScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oTempRs = $this->getScanFromFileMap()
			->setScanActionVO( $oAction )
			->run();

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

		if ( $oAction->file_scan_limit > 0 ) {
			$oAction->files_map = array_slice( $oAction->files_map, $oAction->file_scan_limit );
		}
		else {
			$oAction->files_map = [];
		}

		return $this;
	}

	/**
	 * @return BaseScanFromFileMap|mixed
	 */
	abstract protected function getScanFromFileMap();
}