<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class Ufc extends Base {

	/**
	 * @return Scans\Ufc\Utilities\ItemActionHandler
	 */
	protected function getItemActionHandler() {
		return new Scans\Ufc\Utilities\ItemActionHandler();
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isUfcDeleteFiles();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isUfcEnabled();
	}

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return false;
	}
}