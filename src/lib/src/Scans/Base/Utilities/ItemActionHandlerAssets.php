<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

abstract class ItemActionHandlerAssets extends ItemActionHandler {

	/**
	 * @param string $action
	 * @return bool
	 * @throws \Exception
	 */
	public function process( string $action ) :bool {
		switch ( $action ) {

			case 'asset_deactivate':
				$success = $this->assetDeactivate();
				break;

			default:
				$success = parent::process( $action );
				break;
		}

		return $success;
	}

	/**
	 * Only plugins may be deactivated, of course.
	 * @return bool
	 * @throws \Exception
	 */
	protected function assetDeactivate() {
		/** @var Ptg\ResultItem|Wpv\ResultItem $oItem */
		$oItem = $this->getScanItem();
		$oPlugin = $this->getAssetFromSlug( $oItem->slug );
		Services::WpPlugins()->deactivate( $oPlugin->file );
		return true;
	}

	/**
	 * @param string $slug
	 * @return WpPluginVo|WpThemeVo|null
	 * @throws \Exception
	 */
	protected function getAssetFromSlug( $slug ) {
		if ( Services::WpPlugins()->isInstalled( $slug ) ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $slug );
		}
		elseif ( Services::WpThemes()->isInstalled( $slug ) ) {
			$asset = Services::WpThemes()->getThemeAsVo( $slug );
		}
		else {
			throw new \Exception( 'Items is not currently installed.' );
		}
		return $asset;
	}
}
