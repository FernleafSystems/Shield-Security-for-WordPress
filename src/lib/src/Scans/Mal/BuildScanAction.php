<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->items = ( new BuildFileMap() )
			->setMod( $this->getMod() )
			->setScanActionVO( $oAction )
			->build();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$oAction->paths_whitelisted = $oOpts->getMalWhitelistPaths();
		$oAction->file_exts = [ 'php', 'php5', 'php7' ];
	}
}