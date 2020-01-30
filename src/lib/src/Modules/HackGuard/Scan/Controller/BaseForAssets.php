<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForAssets extends Base {

	/**
	 * @param Scans\Ptg\ResultItem|Scans\Wpv\ResultItem|Scans\Apc\ResultItem $oItem
	 * @return bool
	 */
	protected function isResultItemStale( $oItem ) {
		if ( $oItem->context == 'plugins' ) {
			$oAsset = Services::WpPlugins()->getPluginAsVo( $oItem->slug );
			$bAssetExists = empty( $oAsset ) || $oAsset->active;
		}
		else {
			$oAsset = Services::WpThemes()->getThemeAsVo( $oItem->slug );
			$bAssetExists = empty( $oAsset ) || ( $oAsset->active || $oAsset->is_parent );
		}
		return !$bAssetExists;
	}
}