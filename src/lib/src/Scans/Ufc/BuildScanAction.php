<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * Class BuildScanAction
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc
 */
class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->items = ( new Shield\Scans\Ufc\BuildScanItems() )
			->setMod( $this->getMod() )
			->setScanActionVO( $action )
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