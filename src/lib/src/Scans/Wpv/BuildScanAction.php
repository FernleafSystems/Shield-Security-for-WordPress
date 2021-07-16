<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanAction extends Shield\Scans\Base\BaseBuildScanAction {

	protected function buildItems() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$items = array_map(
			function ( $nKey ) {
				return 'plugins';
			},
			array_flip( Services::WpPlugins()->getInstalledPluginFiles() )
		);

		$WPT = Services::WpThemes();
		$theme = $WPT->isActiveThemeAChild() ? $WPT->getCurrentParent() : $WPT->getCurrent();
		$items[ $theme->get_stylesheet() ] = 'themes';

		$action->items = $items;
	}
}