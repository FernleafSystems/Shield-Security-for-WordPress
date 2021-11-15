<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems extends Shield\Scans\Base\Utilities\BuildScanItems {

	public function run() :array {
		$items = Services::WpPlugins()->getInstalledPluginFiles();

		$WPT = Services::WpThemes();
		$items[] = ( $WPT->isActiveThemeAChild() ? $WPT->getCurrentParent() : $WPT->getCurrent() )->get_stylesheet();

		return $items;
	}
}