<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
class BaseResultsSet {

	/**
	 * @var BaseResultItem[]
	 */
	protected $aItems = true;

	/**
	 * @var bool
	 */
	protected $bFilterExcluded = true;

	/**
	 * @param BaseResultItem $oItem
	 * @return $this
	 */
	public function addItem( $oItem ) {
		$aI = $this->getItems();
		if ( !isset( $oItem->hash ) ) {
			$oItem->hash = $oItem->generateHash();
		}
		$aI[ $oItem->hash ] = $oItem;
		$this->aItems = $aI;
		return $this;
	}

	/**
	 * Ignores the "is_excluded" property on the items
	 * @return BaseResultItem[]
	 */
	public function getAllItems() {
		if ( !is_array( $this->aItems ) ) {
			$this->aItems = array();
		}
		return $this->aItems;
	}

	/**
	 * @return BaseResultItem[]
	 */
	public function getExcludedItems() {
		return array_values( array_filter(
			$this->getAllItems(),
			function ( $oItem ) {
				/** @var BaseResultItem $oItem */
				return $oItem->is_excluded;
			}
		) );
	}

	/**
	 * Honours the exclusion flags
	 * @return BaseResultItem[]
	 */
	public function getItems() {
		return array_values( array_filter(
			$this->getAllItems(),
			function ( $oItem ) {
				/** @var BaseResultItem $oItem */
				return !$this->isFilterExcludedItems() || !$oItem->is_excluded;
			}
		) );
	}

	/**
	 * @return int
	 */
	public function countItems() {
		return count( $this->getItems() );
	}

	/**
	 * @return bool
	 */
	public function hasItems() {
		return $this->countItems() > 0;
	}

	/**
	 * @return bool
	 */
	public function isFilterExcludedItems() {
		return (bool)$this->bFilterExcluded;
	}

	/**
	 * @param bool $bFilterExcluded
	 * @return $this
	 */
	public function setFilterExcludedItems( $bFilterExcluded ) {
		$this->bFilterExcluded = $bFilterExcluded;
		return $this;
	}
}