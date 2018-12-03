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
	protected $aItems;

	/**
	 * @var bool
	 */
	protected $bFilterExcluded = true;

	/**
	 * @param BaseResultItem $oItem
	 * @return $this
	 */
	public function addItem( $oItem ) {
		$aI = $this->getAllItems();
		if ( !isset( $oItem->hash ) ) {
			$oItem->hash = $oItem->generateHash();
		}
		$aI[ $oItem->hash ] = $oItem;
		$this->aItems = $aI;
		return $this;
	}

	/**
	 * @param string $sHash
	 * @return BaseResultItem|null
	 */
	public function getItemByHash( $sHash ) {
		return $this->getItemExists( $sHash ) ? $this->getAllItems()[ $sHash ] : null;
	}

	/**
	 * @param string $sHash
	 * @return bool
	 */
	public function getItemExists( $sHash ) {
		return isset( $this->getAllItems()[ $sHash ] );
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
	 * @param string $sHash
	 * @return $this
	 */
	public function removeItem( $sHash ) {
		if ( $this->getItemExists( $sHash ) ) {
			$aItems = $this->getAllItems();
			unset( $aItems[ $sHash ] );
			$this->aItems = $aItems;
		}
		return $this;
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