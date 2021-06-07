<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class BuildScanAction
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->items = ( new Shield\Scans\Ufc\BuildFileMap() )
			->setScanActionVO( $oAction )
			->build();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();

		$exclusions = $opts->getOpt( 'ufc_exclusions', [] );
		$action->exclusions = is_array( $exclusions ) ? $exclusions : [];
		$action->scan_dirs = $opts->getUfcScanDirectories();
	}
}