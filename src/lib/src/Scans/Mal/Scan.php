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
		$oScanVO->confidence_threshold = 50; // TODO from optiosn

		$aPatterns = ( new Utilities\Patterns() )
			->setMod( $this->getMod() )
			->retrieve();
		$oScanVO->patterns_simple = $aPatterns[ 'simple' ];
		$oScanVO->patterns_regex = $aPatterns[ 'regex' ];
	}

	/**
	 * @return ScanFromFileMap
	 */
	protected function getScanFromFileMap() {
		return ( new ScanFromFileMap() )->setScanActionVO( $this->getScanActionVO() );
	}
}