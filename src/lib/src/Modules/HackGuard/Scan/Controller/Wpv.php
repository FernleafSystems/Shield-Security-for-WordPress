<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class Wpv extends BaseForAssets {

	/**
	 * @return Scans\Wpv\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wpv\Utilities\ItemActionHandler();
	}

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isWpvulnAutoupdatesEnabled();
	}

	public function isEnabled() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isPremium() && $opts->isOpt( 'enable_wpvuln_scan', 'Y' );
	}
}