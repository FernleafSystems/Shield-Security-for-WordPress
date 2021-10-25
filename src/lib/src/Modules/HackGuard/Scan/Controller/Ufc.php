<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 13.0
 */
class Ufc extends BaseForFiles {

	const SCAN_SLUG = 'ufc';

	/**
	 * @return Scans\Ufc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Ufc\Utilities\ItemActionHandler();
	}

	/**
	 * @return Scans\Ufc\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Ufc\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}