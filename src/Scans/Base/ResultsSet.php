<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * @template T of ResultItem
 */
class ResultsSet {
	/**
	 * @var list<T>
	 */
	protected array $items = [];

	/**
	 * @param T $item
	 * @return $this
	 */
	public function addItem( $item ) {
		$all = $this->getAllItems();
		$all[] = $item;
		$this->items = $all;
		return $this;
	}

	/**
	 * @return list<T>
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
	 * @param list<T> $items
	 * @return static
	 */
	public function setItems( array $items ) {
		$this->items = $items;
		return $this;
	}

	/**
	 * @return list<T>
	 * @deprecated 22.0
	 */
	public function getItems(): array {
		return $this->getAllItems();
	}
}
