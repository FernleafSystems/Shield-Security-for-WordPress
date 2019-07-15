<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AsyncActions;

trait ScanActionConsumer {

	/**
	 * @var ScanActionVO
	 */
	private $oScanActionVO;

	/**
	 * @return ScanActionVO
	 */
	public function getScanActionVO() {
		return $this->oScanActionVO;
	}

	/**
	 * @param ScanActionVO $oScanActionVO
	 * @return $this
	 */
	public function setScanActionVO( $oScanActionVO ) {
		$this->oScanActionVO = $oScanActionVO;
		return $this;
	}
}