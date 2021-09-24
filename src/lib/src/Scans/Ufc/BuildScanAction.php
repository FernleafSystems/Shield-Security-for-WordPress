<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class BuildScanAction extends Base\BuildScanAction {

	protected function buildItems() {
		$this->getScanActionVO()->items = ( new BuildScanItems() )
			->setMod( $this->getScanController()->getMod() )
			->setScanActionVO( $this->getScanActionVO() )
			->run();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $opts */
		$opts = $this->getScanController()->getOptions();

		$exclusions = $opts->getOpt( 'ufc_exclusions', [] );
		$action->exclusions = is_array( $exclusions ) ? $exclusions : [];
		$action->scan_dirs = $opts->getUfcScanDirectories();
	}
}