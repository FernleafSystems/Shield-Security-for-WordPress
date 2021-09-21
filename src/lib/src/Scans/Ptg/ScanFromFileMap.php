<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\BaseScanFromFileMap;

/**
 * Class ScanFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class ScanFromFileMap extends BaseScanFromFileMap {

	/**
	 * @var FileScanner
	 */
	private $fileScanner;

	/**
	 * @return FileScanner
	 */
	protected function getFileScanner() {
		if ( empty( $this->fileScanner ) ) {
			$this->fileScanner = ( new FileScanner() )
				->setMod( $this->getMod() )
				->setScanActionVO( $this->getScanActionVO() );
		}
		return $this->fileScanner;
	}
}