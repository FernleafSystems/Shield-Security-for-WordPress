<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\BaseScanFromFileMap;

class ScanFromFileMap extends BaseScanFromFileMap {

	/**
	 * @return FileScanner
	 */
	protected function getFileScanner() {
		return new FileScanner();
	}
}