<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->items = ( new Shield\Scans\Wcf\BuildScanItems() )
			->setMod( $this->getMod() )
			->setScanActionVO( $action )
			->build();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();

		$action->exclusions_missing_regex = $opts->getWcfMissingExclusions();
		$action->exclusions_files_regex = $opts->getWcfFileExclusions();
	}
}