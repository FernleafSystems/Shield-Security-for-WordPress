<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services;

abstract class ICWP_WPSF_Processor_HackProtect_ScanAssetsBase extends ICWP_WPSF_Processor_ScanBase {

	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';

	/**
	 * @param string $sSlug
	 * @return Services\Core\VOs\WpPluginVo|Services\Core\VOs\WpThemeVo|null
	 */
	protected function getAssetFromSlug( $sSlug ) {
		if ( Services\Services::WpPlugins()->isInstalled( $sSlug ) ) {
			$oAsset = Services\Services::WpPlugins()->getPluginAsVo( $sSlug );
		}
		elseif ( Services\Services::WpThemes()->isInstalled( $sSlug ) ) {
			$oAsset = Services\Services::WpThemes()->getThemeAsVo( $sSlug );
		}
		return $oAsset;
	}
}