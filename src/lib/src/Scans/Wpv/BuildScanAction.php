<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$aItems = array_map(
			function ( $nKey ) {
				return 'plugins';
			},
			array_flip( Services::WpPlugins()->getInstalledPluginFiles() )
		);

		$oWpT = Services::WpThemes();
		$oTheme = $oWpT->isActiveThemeAChild() ? $oWpT->getCurrentParent() : $oWpT->getCurrent();
		$aItems[ $oTheme->get_stylesheet() ] = 'themes';

		$oAction->items = $aItems;
	}

	protected function setCustomFields() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oAction->item_processing_limit = $oAction->is_async ? 3 : 0;
	}
}