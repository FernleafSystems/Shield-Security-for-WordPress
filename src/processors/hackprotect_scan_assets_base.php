<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services;

abstract class ICWP_WPSF_Processor_HackProtect_ScanAssetsBase extends ICWP_WPSF_Processor_ScanBase {

	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';

	/**
	 * @param string $sSlug
	 * @return null|string
	 */
	protected function getContextFromSlug( $sSlug ) {
		$sContext = null;
		if ( Services\Services::WpPlugins()->isInstalled( $sSlug ) ) {
			$sContext = self::CONTEXT_PLUGINS;
		}
		elseif ( Services\Services::WpThemes()->isInstalled( $sSlug ) ) {
			$sContext = self::CONTEXT_THEMES;
		}
		return $sContext;
	}

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

	/**
	 * TODO: move to services
	 * @param string $sContext
	 * @return Services\Core\Plugins|Services\Core\Themes
	 */
	protected function getServiceFromContext( $sContext ) {
		return ( $sContext == self::CONTEXT_THEMES ) ? Services\Services::WpThemes() : Services\Services::WpPlugins();
	}

	/**
	 * @param string $sBaseName
	 * @return bool
	 */
	public function reinstall( $sBaseName ) {
		$oExecutor = $this->getServiceFromContext( $this->getContextFromSlug( $sBaseName ) );
		return empty( $oExecutor ) ? false : $oExecutor->reinstall( $sBaseName, false );
	}
}