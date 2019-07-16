<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\FileScanActionVO;

/**
 * Class ScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 * @property string[] $file_exts
 * @property string[] $scan_root_dir
 * @property string[] $paths_whitelisted
 * @property string[] $patterns_regex
 * @property string[] $patterns_simple
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