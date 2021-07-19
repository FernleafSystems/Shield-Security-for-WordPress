<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->items = ( new Shield\Scans\Wcf\BuildFileMap() )
			->setMod( $this->getMod() )
			->setScanActionVO( $action )
			->build();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$oAction->exclusions_missing_regex = $oOpts->getWcfMissingExclusions();
		$oAction->exclusions_files_regex = $oOpts->getWcfFileExclusions();
	}
}