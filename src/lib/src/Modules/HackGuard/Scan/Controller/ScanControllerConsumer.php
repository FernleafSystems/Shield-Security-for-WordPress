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
	 * @param Base $oCon
	 * @return $this
	 */
	public function setScanController( $oCon ) {
		$this->oScanController = $oCon;
		return $this;
	}
}