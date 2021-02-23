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
		/** @var Base\BaseScanActionVO $action */
		$action = $this->getScanActionVO();

		$oTempRs = $this->getScanFromFileMap()
						->setScanActionVO( $action )
						->run();

		$newItems = [];
		if ( $oTempRs->hasItems() ) {
			foreach ( $oTempRs->getAllItems() as $oItem ) {
				$newItems[] = $oItem->getRawData();
			}
		}
		$action->results = $newItems;

		return $this;
	}

	/**
	 * @return BaseScanFromFileMap|mixed
	 */
	abstract protected function getScanFromFileMap();
}