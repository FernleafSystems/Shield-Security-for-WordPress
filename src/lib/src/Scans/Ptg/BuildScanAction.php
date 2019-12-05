<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->items = ( new BuildFileMap() )
			->setScanActionVO( $oAction )
			->build();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oAction->scan_depth = $oOpts->getPtgScanDepth();
		$oAction->file_exts = $oOpts->getPtgFileExtensions();
		$oAction->hashes_base_path = $oOpts->getPtgSnapsBaseDir();
	}
}