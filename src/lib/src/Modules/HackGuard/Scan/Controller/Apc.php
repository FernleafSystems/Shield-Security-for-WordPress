<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;

class Apc extends BaseForAssets {

	const SCAN_SLUG = 'apc';

	/**
	 * @return Scans\Apc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Apc\Utilities\ItemActionHandler();
	}

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'enabled_scan_apc', 'Y' );
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @return Scans\Apc\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Apc\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}