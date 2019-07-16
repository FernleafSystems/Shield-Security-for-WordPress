<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\FileScanActionVO;

/**
 * Class WcfScanActionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions
 * @property bool     $is_exclude_plugins_themes
 * @property string   $exclusions_missing_regex
 * @property string   $exclusions_files_regex
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