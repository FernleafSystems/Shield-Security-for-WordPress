<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

trait ScanActionConsumer {

	/**
	 * @var BaseScanActionVO
	 */
	private $oScanActionVO;

	/**
	 * @return BaseScanActionVO
	 */
	public function getScanActionVO() {
		return $this->oScanActionVO;
	}

	/**
	 * @param BaseScanActionVO $action
	 * @return $this
	 */
	public function setScanActionVO( $action ) {
		$this->oScanActionVO = $action;
		return $this;
	}
}