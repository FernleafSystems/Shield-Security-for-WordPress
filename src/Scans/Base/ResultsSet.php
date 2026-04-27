<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ResultsSet {
	/**
	 * @var ResultItem[]
	 */
	protected array $items = [];

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
	 * @return ResultItem[]
	 */
	public function getAllItems(): array {
		return $this->items;
	}

	/**
	 * @return static
	 */
	public function getNotIgnored() {
		$res = clone $this;
		$res->setItems( [] );
		foreach ( $this->items as $item ) {
			if ( $item->VO->ignored_at == 0 ) {
				$res->addItem( $item );
			}
		}
		return $res;
	}

	public function countItems(): int {
		return \count( $this->items );
	}

	public function hasItems(): bool {
		return $this->countItems() > 0;
	}

	/**
	 * @return static
	 */
	public function setItems( array $items ) {
		$this->items = $items;
		return $this;
	}

	/**
	 * @return ResultItem[]
	 * @deprecated 22.0
	 */
	public function getItems(): array {
		return $this->getAllItems();
	}
}