<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

abstract class BaseFileMapScan extends Base\BaseScan {

	/**
	 * @return $this
	 */
	protected function scanSlice() {
		$action = $this->getScanActionVO();

		$action->results = array_map(
			function ( $item ) {
				return $item->getRawData();
			},
			// run the scan and get results:
			$this->getScanFromFileMap()
				 ->setScanActionVO( $action )
				 ->run()
				 ->getAllItems()
		);

		return $this;
	}

	/**
	 * @return BaseScanFromFileMap|mixed
	 */
	abstract protected function getScanFromFileMap();
}