<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	/**
	 */
	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();

		$oAction->item_processing_limit = $oAction->is_async ? $oOpts->getFileScanLimit() : 0;
		$oAction->paths_whitelisted = $oOpts->getMalWhitelistPaths();
		$oAction->patterns_regex = $oOpts->getMalSignaturesRegex();
		$oAction->patterns_simple = $oOpts->getMalSignaturesSimple();
		$oAction->file_exts = [ 'php', 'php5' ];
		$oAction->scan_root_dir = ABSPATH;

		$oAction->files_map = ( new Shield\Scans\Mal\BuildFileMap() )
			->setScanActionVO( $oAction )
			->build();
		$oAction->total_scan_items = count( $oAction->files_map );
	}
}