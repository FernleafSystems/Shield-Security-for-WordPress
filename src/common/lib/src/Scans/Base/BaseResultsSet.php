<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ResultsSet
 * @property BaseResultItem[] items
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
class BaseResultsSet {

	use StdClassAdapter;

	/**
	 * @param BaseResultItem $oItem
	 * @return $this
	 */
	public function addItem( $oItem ) {
		$aI = $this->getItems();
		$aI[] = $oItem;
		$this->items = $aI;
		return $this;
	}

	/**
	 * @return BaseResultItem[]
	 */
	public function getItems() {
		if ( !is_array( $this->items ) ) {
			$this->items = array();
		}
		return $this->items;
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
}