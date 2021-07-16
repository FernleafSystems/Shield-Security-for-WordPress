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
	protected $items;

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
		$this->items = $aI;
		return $this;
	}

	/**
	 * @param string $hash
	 * @return BaseResultItem|null
	 */
	public function getItemByHash( $hash ) {
		return $this->getItemExists( $hash ) ? $this->getAllItems()[ $hash ] : null;
	}

	/**
	 * @param string $hash
	 * @return bool
	 */
	public function getItemExists( $hash ) {
		return isset( $this->getAllItems()[ $hash ] );
	}

	/**
	 * Ignores the "is_excluded" property on the items
	 * @return BaseResultItem[]
	 */
	public function getAllItems() :array {
		if ( !is_array( $this->items ) ) {
			$this->items = [];
		}
		return $this->items;
	}

	/**
	 * @return BaseResultItem[]
	 */
	public function getExcludedItems() :array {
		return array_values( array_filter(
			$this->getAllItems(),
			function ( $item ) {
				return $item->is_excluded;
			}
		) );
	}

	/**
	 * Honours the exclusion flags
	 * @return BaseResultItem[]
	 */
	public function getItems() :array {
		return array_values( array_filter(
			$this->getAllItems(),
			function ( $item ) {
				return !$this->isFilterExcludedItems() || !$item->is_excluded;
			}
		) );
	}

	public function countItems() :int {
		return count( $this->getItems() );
	}

	public function hasItems() :bool {
		return $this->countItems() > 0;
	}

	/**
	 * @return bool
	 */
	public function isFilterExcludedItems() {
		return (bool)$this->bFilterExcluded;
	}

	/**
	 * @param BaseResultItem $item
	 * @return $this
	 */
	public function removeItem( $item ) {
		return $this->removeItemByHash( $item->hash );
	}

	/**
	 * @param string $hash
	 * @return $this
	 */
	public function removeItemByHash( $hash ) {
		if ( $this->getItemExists( $hash ) ) {
			$items = $this->getAllItems();
			unset( $items[ $hash ] );
			$this->items = $items;
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