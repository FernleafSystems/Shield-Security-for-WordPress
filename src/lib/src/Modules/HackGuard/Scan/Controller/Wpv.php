<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class Wpv extends BaseForAssets {

	/**
	 * @return Scans\Wpv\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wpv\Utilities\ItemActionHandler();
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isWpvulnAutoupdatesEnabled();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isPremium() && $oOpts->isOpt( 'enable_wpvuln_scan', 'Y' );
	}
}