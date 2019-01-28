<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] $aItems
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class ResultsSet extends Base\BaseResultsSet {

	/**
	 * @param ResultItem[] $aItems
	 * @return string[]
	 */
	public function filterItemsForPaths( $aItems ) {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_fragment;
			},
			$aItems
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
				return $oItem->is_checksumfail;
			}
		) );
	}

	/**
	 * @return string[]
	 */
	public function getChecksumFailedPaths() {
		return $this->filterItemsForPaths( $this->getChecksumFailedItems() );
	}

	/**
	 * @return ResultItem[]
	 */
	public function getMissingItems() {
		return array_values( array_filter(
			$this->getItems(),
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->is_missing;
			}
		) );
	}

	/**
	 * @return string[]
	 */
	public function getMissingPaths() {
		return $this->filterItemsForPaths( $this->getMissingItems() );
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