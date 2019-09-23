<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class Scan
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Scan extends Shield\Scans\Base\Files\BaseFileMapScan {

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		parent::preScan();

		/** @var ScanActionVO $oScanVO */
		$oScanVO = $this->getScanActionVO();
		$oScanVO->whitelist = ( new Utilities\Whitelist() )
			->setMod( $this->getMod() )
			->retrieve();
	}

	/**
	 * @return ScanFromFileMap
	 */
	protected function getScanFromFileMap() {
		return ( new ScanFromFileMap() )->setScanActionVO( $this->getScanActionVO() );
	}
}