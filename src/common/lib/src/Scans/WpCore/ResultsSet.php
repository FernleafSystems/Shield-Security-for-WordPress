<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] items
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class ResultsSet extends Base\BaseResultsSet {

	/**
	 * @return array
	 */
	public function getMissing() {
		return array_values( array_filter( array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->isFileMissing() ? $oItem->path_fragment : null;
			},
			$this->getItems()
		) ) );
	}

	/**
	 * @return array
	 */
	public function getChecksumFailed() {
		return array_values( array_filter( array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->isChecksumFail() ? $oItem->path_fragment : null;
			},
			$this->getItems()
		) ) );
	}
}