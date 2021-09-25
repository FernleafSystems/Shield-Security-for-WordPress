<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

trait ScanControllerConsumer {

	/**
	 * @var Base
	 */
	private $oScanController;

	/**
	 * @return Base|mixed
	 */
	public function getScanController() {
		return $this->oScanController;
	}

	/**
	 * @param Base $con
	 * @return $this
	 */
	public function setScanController( $con ) {
		$this->oScanController = $con;
		return $this;
	}
}