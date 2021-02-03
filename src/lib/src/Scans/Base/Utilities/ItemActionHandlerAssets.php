<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Services;

abstract class ItemActionHandlerAssets extends ItemActionHandler {

	/**
	 * @param string $action
	 * @return bool
	 * @throws \Exception
	 */
	public function process( $action ) {
		switch ( $action ) {

			case 'asset_deactivate':
				$bSuccess = $this->assetDeactivate();
				break;

			default:
				$bSuccess = parent::process( $action );
				break;
		}

		return $bSuccess;
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
	 * @param string $sSlug
	 * @return VOs\WpPluginVo|VOs\WpThemeVo|null
	 * @throws \Exception
	 */
	protected function getAssetFromSlug( $sSlug ) {
		if ( Services::WpPlugins()->isInstalled( $sSlug ) ) {
			$oAsset = Services::WpPlugins()->getPluginAsVo( $sSlug );
		}
		elseif ( Services::WpThemes()->isInstalled( $sSlug ) ) {
			$oAsset = Services::WpThemes()->getThemeAsVo( $sSlug );
		}
		else {
			throw new \Exception( 'Items is not currently installed.' );
		}
		return $oAsset;
	}
}
