<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultItem;

trait ScanItemConsumer {

	/**
	 * @var BaseResultItem
	 */
	private $oScanItem;

	/**
	 * @return BaseResultItem|mixed
	 */
	public function getScanItem() {
		return $this->oScanItem;
	}

	/**
	 * @param BaseResultItem|mixed $oItem
	 * @return $this
	 */
	public function setScanItem( $oItem ) {
		$this->oScanItem = $oItem;
		return $this;
	}
}