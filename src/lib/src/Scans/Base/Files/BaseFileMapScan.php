<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class BaseFileAsyncScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files
 */
abstract class BaseFileMapScan extends Base\BaseScan {

	/**
	 * @return $this
	 */
	protected function scanSlice() {
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
			$oAction->items = array_slice( $oAction->items, $oAction->item_processing_limit );
		}
		else {
			$oAction->items = [];
		}

		return $this;
	}

	/**
	 * @return BaseScanFromFileMap|mixed
	 */
	abstract protected function getScanFromFileMap();
}