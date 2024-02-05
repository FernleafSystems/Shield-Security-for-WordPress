<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Services\Services;

class BuildScanAction extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BuildScanAction {

	protected function buildScanItems() {
		$this->getScanActionVO()->items = Services::WpPlugins()->getInstalledPluginFiles();
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();
		$action->abandoned_limit = YEAR_IN_SECONDS*2;
	}
}