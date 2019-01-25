<?php

use FernleafSystems\Wordpress\Plugin\Shield,
	FernleafSystems\Wordpress\Services;

abstract class ICWP_WPSF_Processor_HackProtect_ScanAssetsBase extends ICWP_WPSF_Processor_ScanBase {

	const CONTEXT_PLUGINS = 'plugins';
	const CONTEXT_THEMES = 'themes';

	/**
	 * Only plugins may be deactivated, of course.
	 * @param Shield\Scans\Ptg\ResultItem|Shield\Scans\Wpv\ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function assetDeactivate( $oItem ) {
		$oWpPlugins = $this->loadWpPlugins();
		if ( !$oWpPlugins->isInstalled( $oItem->slug ) ) {
			throw new \Exception( 'Items is not currently installed.' );
		}
		$oWpPlugins->deactivate( $oItem->slug );
		return true;
	}

	/**
	 * @param Shield\Scans\Ptg\ResultItem|Shield\Scans\Wpv\ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function assetReinstall( $oItem ) {
		$this->reinstall( $oItem->slug );
		return true;
	}

	/**
	 * @param string $sSlug
	 * @return null|string
	 */
	protected function getContextFromSlug( $sSlug ) {
		$sContext = null;
		if ( Services\Services::WpPlugins()->isInstalled( $sSlug ) ) {
			$sContext = self::CONTEXT_PLUGINS;
		}
		else if ( Services\Services::WpThemes()->isInstalled( $sSlug ) ) {
			$sContext = self::CONTEXT_THEMES;
		}
		return $sContext;
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