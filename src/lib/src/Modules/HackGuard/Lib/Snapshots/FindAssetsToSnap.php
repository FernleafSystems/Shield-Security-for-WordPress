<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class FindAssetsToSnap {

	use ModConsumer;

	/**
	 * @return WpPluginVo[]|WpThemeVo[]
	 */
	public function run() {
		$aAssets = [];

		foreach ( Services::WpPlugins()->getPluginsAsVo() as $oAsset ) {
			if ( $oAsset->active ) {
				$aAssets[] = $oAsset;
			}
		}

		$oWPT = Services::WpThemes();
		$oAsset = $oWPT->getThemeAsVo( $oWPT->getCurrent()->get_stylesheet() );
		$aAssets[] = $oAsset;

		if ( $oWPT->isActiveThemeAChild() ) {
			$oAsset = $oWPT->getThemeAsVo( $oAsset->wp_theme->get_template() );
			$aAssets[] = $oAsset;
		}

		return $aAssets;
	}
}