<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class FindAssetsToSnap extends Base {

	/**
	 * @return WpPluginVo[]|WpThemeVo[]
	 */
	public function run() {
		$aAssets = [];

		foreach ( Services::WpPlugins()->getPluginsAsVo() as $oAsset ) {
			if ( $oAsset->active ) {
				$aMeta = ( new Store( $oAsset ) )->getSnapMeta();
				if ( empty( $aMeta ) || $oAsset->Version !== $aMeta[ 'version' ] ) {
					$aAssets[] = $oAsset;
				}
			}
		}

		$oWPT = Services::WpThemes();
		$oAsset = $oWPT->getThemeAsVo( $oWPT->getCurrent()->get_stylesheet() );
		$aMeta = ( new Store( $oAsset ) )->getSnapMeta();
		if ( $oAsset->version !== $aMeta[ 'version' ] ) {
			$aAssets[] = $oAsset;
		}

		if ( $oWPT->isActiveThemeAChild() ) {
			$oAsset = $oWPT->getThemeAsVo( $oAsset->wp_theme->get_template() );
			$aMeta = ( new Store( $oAsset ) )->getSnapMeta();
			if ( empty( $aMeta ) || $oAsset->Version !== $aMeta[ 'version' ] ) {
				$aAssets[] = $oAsset;
			}
		}

		return $aAssets;
	}
}