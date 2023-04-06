<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;

trait ScanItemConsumer {

	/**
	 * @var ResultItem
	 */
	private $scanResultItem;

	/**
	 * @return ResultItem|mixed
	 */
	public function getScanItem() {
		return $this->scanResultItem;
	}

	/**
	 * @param ResultItem|mixed $item
	 * @return $this
	 */
	public function setScanItem( $item ) {
		$this->scanResultItem = $item;
		return $this;
	}
}