<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class Wcf extends Base {

	/**
	 * @return Scans\Wcf\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wcf\Utilities\ItemActionHandler();
	}

	/**
	 * @param Scans\Wcf\ResultItem $oItem
	 * @return bool
	 */
	protected function isResultItemStale( $oItem ) {
		$oCFH = Services::CoreFileHashes();
		return !$oCFH->isCoreFile( $oItem->path_full )
			   || Services::CoreFileHashes()->isCoreFileHashValid( $oItem->path_full );
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isRepairFileWP();
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	/**
	 * @return bool
	 */
	protected function isPremiumOnly() {
		return false;
	}
}