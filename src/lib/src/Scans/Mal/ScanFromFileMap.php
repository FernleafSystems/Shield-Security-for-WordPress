<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\BaseScanFromFileMap;

class ScanFromFileMap extends BaseScanFromFileMap {

	/**
	 * @return FileScanner
	 */
	protected function getFileScanner() {
		return new FileScanner();
	}
}