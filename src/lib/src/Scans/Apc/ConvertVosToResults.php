<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

class ConvertVosToResults extends Shield\Scans\Base\BaseConvertVosToResults {

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