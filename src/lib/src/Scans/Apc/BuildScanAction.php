<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->scan_items = Services::WpPlugins()->getInstalledPluginFiles();
		$oAction->total_scan_items = count( $oAction->scan_items );
		$oAction->item_processing_limit = $oAction->is_async ? 3 : 0;
		$oAction->abandoned_limit = YEAR_IN_SECONDS*2;
	}
}