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
	 * @return ResultItem[]
	 */
	public function getMissingItems() {
		return array_values( array_filter(
			$this->getItems(),
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->isFileMissing();
			}
		) );
	}

	/**
	 * @return array
	 */
	public function getMissingPaths() {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_fragment;
			},
			$this->getMissingItems()
		);
	}

	/**
	 * @return ResultItem[]
	 */
	public function getChecksumFailedItems() {
		return array_values( array_filter(
			$this->getItems(),
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->isChecksumFail();
			}
		) );
	}

	/**
	 * @return array
	 */
	public function getChecksumFailedPaths() {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_fragment;
			},
			$this->getChecksumFailedItems()
		);
	}

	/**
	 * @return int
	 */
	public function countChecksumFailed() {
		return count( $this->getChecksumFailedItems() );
	}

	/**
	 * @return int
	 */
	public function countMissing() {
		return count( $this->getMissingItems() );
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
}