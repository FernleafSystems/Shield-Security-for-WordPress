<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;

class Scan extends Shield\Scans\Base\Files\BaseFileMapScan {

	/**
	 * @return ScanFromFileMap
	 */
	protected function getScanFromFileMap() {
		return ( new ScanFromFileMap() )
			->setMod( $this->getMod() )
			->setScanActionVO( $this->getScanActionVO() );
	}
}