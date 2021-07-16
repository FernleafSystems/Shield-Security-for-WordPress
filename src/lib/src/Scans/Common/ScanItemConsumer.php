<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;

trait ScanItemConsumer {

	/**
	 * @var ResultItem
	 */
	private $scanItem;

	/**
	 * @return ResultItem|mixed
	 */
	public function getScanItem() {
		return $this->scanItem;
	}

	/**
	 * @param ResultItem|mixed $item
	 * @return $this
	 */
	public function setScanItem( $item ) {
		$this->scanItem = $item;
		return $this;
	}
}