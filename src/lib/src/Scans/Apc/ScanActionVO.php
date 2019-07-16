<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class WcfScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions
 * @property string[] $scan_items
 * @property int      $abandoned_limit
 */
class ScanActionVO extends Shield\Scans\Base\BaseScanActionVO {

	/**
	 * @return ResultItem
	 */
	public function getNewResultItem() {
		return new ResultItem();
	}

	/**
	 * @return ResultsSet
	 */
	public function getNewResultsSet() {
		return new ResultsSet();
	}
}