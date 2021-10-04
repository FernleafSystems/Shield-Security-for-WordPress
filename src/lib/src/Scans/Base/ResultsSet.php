<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ResultsSet {

	/**
	 * @var ResultItem[]
	 */
	protected $items;

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
	public function getItemExists( $hash ) :bool {
		return isset( $this->getAllItems()[ $hash ] );
	}

	/**
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
	public function getItems() :array {
		return $this->getAllItems();
	}

	public function countItems() :int {
		return count( $this->getItems() );
	}

	public function hasItems() :bool {
		return $this->countItems() > 0;
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
}