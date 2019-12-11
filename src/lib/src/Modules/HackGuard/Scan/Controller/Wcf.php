<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class Wcf extends Base {

	/**
	 * @return Scans\Wcf\Utilities\ItemActionHandler
	 */
	protected function getItemActionHandler() {
		return new Scans\Wcf\Utilities\ItemActionHandler();
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isWcfScanAutoRepair();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isWcfScanEnabled();
	}

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return false;
	}
}