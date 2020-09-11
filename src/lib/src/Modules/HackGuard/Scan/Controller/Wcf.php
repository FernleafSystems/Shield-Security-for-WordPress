<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Wcf extends Base {

	const SCAN_SLUG = 'wcf';

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

	public function isCronAutoRepair() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFileWP();
	}

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	protected function isPremiumOnly() :bool {
		return false;
	}
}