<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class FindAssetsToSnap {

	use PluginControllerConsumer;

	/**
	 * @return WpPluginVo[]|WpThemeVo[]
	 */
	public function run() :array {
		$assets = [];

		foreach ( Services::WpPlugins()->getPluginsAsVo() as $asset ) {
			if ( $asset->active ) {
				$assets[] = $asset;
			}
		}

		$WPT = Services::WpThemes();
		$asset = $WPT->getThemeAsVo( $WPT->getCurrent()->get_stylesheet() );
		$assets[] = $asset;

		if ( $WPT->isActiveThemeAChild() ) {
			$asset = $WPT->getThemeAsVo( $asset->wp_theme->get_template() );
			$assets[] = $asset;
		}

		return \array_filter( $assets );
	}
}