<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class ConvertVosToResults
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class ConvertVosToResults extends Scans\Base\BaseConvertVosToResults {

	/**
	 * @return ResultItem
	 */
	protected function getNewResultItem() {
		return new ResultItem();
	}

	/**
	 * @return ResultsSet
	 */
	protected function getNewResultSet() {
		return new ResultsSet();
	}
}