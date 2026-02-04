<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

trait ScanActionConsumer {

	/**
	 * @var BaseScanActionVO
	 */
	private $scanActionVO;

	/**
	 * @return BaseScanActionVO|mixed
	 */
	public function getScanActionVO() {
		return $this->scanActionVO;
	}

	/**
	 * @param BaseScanActionVO $action
	 * @return $this
	 */
	public function setScanActionVO( $action ) {
		$this->scanActionVO = $action;
		return $this;
	}
}