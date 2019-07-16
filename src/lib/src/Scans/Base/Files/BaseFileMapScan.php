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
	 * @throws \Exception
	 */
	protected function scan() {
		/** @var Base\BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( empty( $oAction->scan_items ) ) {
			$oAction->ts_finish = Services::Request()->ts();
		}
		else {
			$this->scanFileMapSlice();
			if ( empty( $oAction->scan_items ) ) {
				$oAction->ts_finish = Services::Request()->ts();
			}
		}
	}

	/**
	 * @return $this
	 */
	protected function scanFileMapSlice() {
		/** @var Base\BaseScanActionVO $oAction */
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

		if ( $oAction->item_processing_limit > 0 ) {
			$oAction->scan_items = array_slice( $oAction->scan_items, $oAction->item_processing_limit );
		}
		else {
			$oAction->scan_items = [];
		}

		return $this;
	}

	/**
	 * @return BaseScanFromFileMap|mixed
	 */
	abstract protected function getScanFromFileMap();
}