<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

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
	 * @param BaseScanActionVO $oScanActionVO
	 * @return $this
	 */
	public function setScanActionVO( $oScanActionVO ) {
		$this->oScanActionVO = $oScanActionVO;
		return $this;
	}
}