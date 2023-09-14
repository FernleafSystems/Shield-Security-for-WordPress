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
		$all[] = $item;
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
		if ( !\is_array( $this->items ) ) {
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

	/**
	 * @return static
	 */
	public function getNotIgnored() {
		$res = clone $this;
		$res->setItems( [] );
		foreach ( $this->getItems() as $item ) {
			if ( $item->VO->ignored_at == 0 ) {
				$res->addItem( $item );
			}
		}
		return $res;
	}

	public function countItems() :int {
		return \count( $this->getItems() );
	}

	public function hasItems() :bool {
		return $this->countItems() > 0;
	}

	/**
	 * @return static
	 */
	public function setItems( array $items ) {
		$this->items = $items;
		return $this;
	}
}