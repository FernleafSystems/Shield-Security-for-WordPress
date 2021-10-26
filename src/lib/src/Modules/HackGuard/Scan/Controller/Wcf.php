<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	DB\ResultItems\Ops\Update,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 13.0
 */
class Wcf extends BaseForFiles {

	const SCAN_SLUG = 'wcf';

	public function getScanFileExclusions() :string {
		return '';
	}

	/**
	 * Builds a regex-ready pattern for matching file names to exclude from scan if they're missing
	 */
	public function getScanExclusionsForMissingItems() :string {
		return '';
	}

	/**
	 * @return Scans\Wcf\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Wcf\Utilities\ItemActionHandler();
	}

	/**
	 * @param Scans\Wcf\ResultItem $item
	 */
	public function cleanStaleResultItem( $item ) {
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