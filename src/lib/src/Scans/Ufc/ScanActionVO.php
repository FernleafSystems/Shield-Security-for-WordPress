<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\FileScanActionVO;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions
 * @property string[] $scan_dirs
 * @property string[] $exclusions
 */
class ScanActionVO extends FileScanActionVO {

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