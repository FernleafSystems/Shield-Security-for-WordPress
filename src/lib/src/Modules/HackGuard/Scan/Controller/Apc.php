<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

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
		return $this->getOptions()->isOpt( 'enabled_scan_apc', 'Y' );
	}

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return false;
	}
}