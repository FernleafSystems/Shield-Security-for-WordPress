<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class Ufc extends Base {

	/**
	 * @return Scans\Ufc\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Ufc\Utilities\ItemActionHandler();
	}

	/**
	 * @param Scans\Mal\ResultItem $oItem
	 * @return bool
	 */
	protected function isResultItemStale( $oItem ) {
		return !Services::WpFs()->exists( $oItem->path_full );
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
		return $oOpts->getUnrecognisedFileScannerOption() != 'disabled';
	}

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return false;
	}
}