<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class Wcf extends BaseForFiles {

	const SCAN_SLUG = 'wcf';

	/**
	 * @return Scans\Wcf\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wcf\Utilities\ItemActionHandler();
	}

	/**
	 * @param Scans\Wcf\ResultItem $item
	 * @return bool
	 */
	protected function isResultItemStale( $item ) :bool {
		$CFH = Services::CoreFileHashes();
		return !$CFH->isCoreFile( $item->path_full ) || $CFH->isCoreFileHashValid( $item->path_full );
	}

	public function isCronAutoRepair() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isRepairFileWP();
	}

	public function isEnabled() :bool {
		return $this->getOptions()->isOpt( 'enable_core_file_integrity_scan', 'Y' );
	}

	protected function isPremiumOnly() :bool {
		return false;
	}

	/**
	 * @return Scans\Wcf\ScanActionVO
	 */
	public function buildScanAction() {
		return ( new Scans\Wcf\BuildScanAction() )
			->setScanController( $this )
			->build()
			->getScanActionVO();
	}
}