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

	/**
	 * @return int
	 */
	public function countChecksumFailed() {
		return count( $this->getChecksumFailed() );
	}

	/**
	 * @return int
	 */
	public function countMissing() {
		return count( $this->getMissing() );
	}

	/**
	 * @return bool
	 */
	public function hasChecksumFailed() {
		return $this->countChecksumFailed() > 0;
	}

	/**
	 * @return bool
	 */
	public function hasMissing() {
		return $this->countMissing() > 0;
	}

	/**
	 * @return int
	 */
	public function countResults() {
		return count( $this->getItems() );
	}

	/**
	 * @return bool
	 */
	public function hasResults() {
		return $this->countResults() > 0;
	}
}