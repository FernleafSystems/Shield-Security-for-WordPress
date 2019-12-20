<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class Apc extends BaseForAssets {

	/**
	 * @return Scans\Apc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Apc\Utilities\ItemActionHandler();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isApcEnabled();
	}

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return false;
	}
}