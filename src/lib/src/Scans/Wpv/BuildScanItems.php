<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BuildScanItems extends Shield\Scans\Base\Utilities\BuildScanItems {

	public function run() :array {
		$items = array_map(
			function ( $key ) {
				return 'plugins';
			},
			array_flip( Services::WpPlugins()->getInstalledPluginFiles() )
		);

		$WPT = Services::WpThemes();
		$theme = $WPT->isActiveThemeAChild() ? $WPT->getCurrentParent() : $WPT->getCurrent();
		$items[ $theme->get_stylesheet() ] = 'themes';

		return $items;
	}
}