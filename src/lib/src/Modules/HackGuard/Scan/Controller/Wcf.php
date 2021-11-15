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

	protected function newItemActionHandler() {
		return null;
	}

	public function cleanStaleResultItem( $item ) {
		return true;
	}

	/**
	 * @return Scans\Wcf\ScanActionVO
	 */
	public function buildScanAction() {
		return $this->getScanActionVO();
	}
}