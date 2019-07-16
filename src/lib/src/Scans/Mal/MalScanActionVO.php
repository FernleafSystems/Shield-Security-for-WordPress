<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\FileScanActionVO;

/**
 * Class MalScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 * @property string[] $paths_whitelisted
 * @property string[] $patterns_regex
 * @property string[] $patterns_simple
 */
class MalScanActionVO extends FileScanActionVO {

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