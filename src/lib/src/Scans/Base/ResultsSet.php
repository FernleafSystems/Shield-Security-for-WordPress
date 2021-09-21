<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ResultsSet {

	/**
	 * @var ResultItem[]
	 */
	protected $items;

	/**
	 * @var bool
	 */
	protected $bFilterExcluded = true;

	/**
	 * @param ResultItem $item
	 * @return $this
	 */
	public function addItem( $item ) {
		$all = $this->getAllItems();
		if ( !isset( $item->hash ) ) {
			$item->hash = $item->generateHash();
		}
		$all[ $item->hash ] = $item;
		$this->items = $all;
		return $this;
	}

	/**
	 * @param string $hash
	 * @return ResultItem|null
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
	 * @return ResultItem[]
	 */
	public function getAllItems() :array {
		if ( !is_array( $this->items ) ) {
			$this->items = [];
		}
		return $this->items;
	}

	/**
	 * @return ResultItem[]
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
	 * @return ResultItem[]
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
	 * @param ResultItem $item
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