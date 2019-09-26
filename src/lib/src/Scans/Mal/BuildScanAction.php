<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->items = ( new Shield\Scans\Mal\BuildFileMap() )
			->setScanActionVO( $oAction )
			->build();
	}

	/**
	 */
	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();

		$oAction->paths_whitelisted = $oOpts->getMalWhitelistPaths();
		$oAction->file_exts = [ 'php', 'php5', 'php7' ];
	}
}