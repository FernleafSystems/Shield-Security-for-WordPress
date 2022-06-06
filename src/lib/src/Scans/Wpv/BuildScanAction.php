<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanAction extends Base\BuildScanAction {

	protected function buildScanItems() {
		$items = Services::WpPlugins()->getInstalledPluginFiles();
		$WPT = Services::WpThemes();
		$items[] = ( $WPT->isActiveThemeAChild() ? $WPT->getCurrentParent() : $WPT->getCurrent() )->get_stylesheet();

		$this->getScanActionVO()->items = $items;
	}
}