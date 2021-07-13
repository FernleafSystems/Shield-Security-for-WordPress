<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;

trait ScanItemConsumer {

	/**
	 * @var ResultItem
	 */
	private $oScanItem;

	/**
	 * @return ResultItem|mixed
	 */
	public function getScanItem() {
		return $this->oScanItem;
	}

	/**
	 * @param ResultItem|mixed $oItem
	 * @return $this
	 */
	public function setScanItem( $oItem ) {
		$this->oScanItem = $oItem;
		return $this;
	}
}