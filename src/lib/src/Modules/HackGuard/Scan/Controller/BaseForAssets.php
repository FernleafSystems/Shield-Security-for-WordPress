<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForAssets extends Base {

	/**
	 * @param Scans\Ptg\ResultItem|Scans\Wpv\ResultItem|Scans\Apc\ResultItem $item
	 * @return bool
	 */
	protected function isResultItemStale( $item ) {
		if ( $item->context == 'plugins' ) {
			$oAsset = Services::WpPlugins()->getPluginAsVo( $item->slug );
			$bAssetExists = empty( $oAsset ) || $oAsset->active;
		}
		else {
			$oAsset = Services::WpThemes()->getThemeAsVo( $item->slug );
			$bAssetExists = empty( $oAsset ) || ( $oAsset->active || $oAsset->is_parent );
		}
		return !$bAssetExists;
	}
}