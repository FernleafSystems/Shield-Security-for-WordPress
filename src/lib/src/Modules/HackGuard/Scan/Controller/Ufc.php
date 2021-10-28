<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * @deprecated 13.0
 */
class Ufc extends BaseForFiles {

	const SCAN_SLUG = 'ufc';

	protected function newItemActionHandler() {
		return null;
	}

	/**
	 * @return Scans\Ufc\ScanActionVO
	 */
	public function buildScanAction() {
		return $this->getScanActionVO();
	}
}