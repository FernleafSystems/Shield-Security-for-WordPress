<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] $aItems
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class ResultsSet extends Base\BaseResultsSet {

	/**
	 * @return string[]
	 */
	public function getItemsPathsFull() {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_full;
			},
			$this->getItems()
		);
	}

	/**
	 * @return string[]
	 */
	public function getItemsPathsFragments() {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_fragment;
			},
			$this->getItems()
		);
	}
}