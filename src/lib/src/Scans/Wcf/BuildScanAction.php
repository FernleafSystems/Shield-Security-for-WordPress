<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();

		$oAction->item_processing_limit = $oAction->is_async ? $oOpts->getFileScanLimit() : 0;
		$oAction->exclusions_missing_regex = $oOpts->getWcfMissingExclusions();
		$oAction->exclusions_files_regex = $oOpts->getWcfFileExclusions();
		$oAction->files_map = ( new Shield\Scans\Wcf\BuildFileMap() )
			->setScanActionVO( $oAction )
			->build();
		$oAction->total_scan_items = count( $oAction->files_map );
	}
}