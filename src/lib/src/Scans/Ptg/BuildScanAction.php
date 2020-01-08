<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield;

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
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		$oAction->file_exts = $oOpts->getPtgFileExtensions();
	}
}