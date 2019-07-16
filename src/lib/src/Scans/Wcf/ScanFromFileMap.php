<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\BaseScanFromFileMap;

/**
 * Class ScanFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class ScanFromFileMap extends BaseScanFromFileMap {

	/**
	 * @return FileScanner
	 */
	protected function getPathScanner() {
		return ( new FileScanner() )->setScanActionVO( $this->getScanActionVO() );
	}
}