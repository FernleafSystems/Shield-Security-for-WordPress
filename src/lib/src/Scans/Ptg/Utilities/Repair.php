<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() {
		/** @var Ptg\ResultItem $oItem */
		$oItem = $this->getScanItem();

		if ( $this->canRepair() ) {
			if ( $oItem->context == 'plugins' ) {
				$bSuccess = $this->repairPluginFile( $oItem->path_full );
			}
			else {
				$bSuccess = $this->repairThemeFile( $oItem->path_full );
			}
		}
		else {
			$bSuccess = false;
		}

		return $bSuccess;
	}

	/**
	 * @param string $sPath
	 * @return bool
	 */
	private function repairPluginFile( $sPath ) {
		$success = false;
		$oFiles = new WpOrg\Plugin\Files();
		try {
			if ( $oFiles->isValidFileFromPlugin( $sPath ) ) {
				$success = $oFiles->replaceFileFromVcs( $sPath );
			}
			elseif ( $this->isAllowDelete() ) {
				$success = (bool)Services::WpFs()->deleteFile( $sPath );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}
		return (bool)$success;
	}

	/**
	 * @param string $sPath
	 * @return bool
	 */
	private function repairThemeFile( $sPath ) {
		$success = false;
		$oFiles = new WpOrg\Theme\Files();
		try {
			if ( $oFiles->isValidFileFromTheme( $sPath ) ) {
				$success = $oFiles->replaceFileFromVcs( $sPath );
			}
			elseif ( $this->isAllowDelete() ) {
				$success = (bool)Services::WpFs()->deleteFile( $sPath );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}
		return (bool)$success;
	}

	/**
	 * @return bool
	 */
	public function canRepair() {
		/** @var Ptg\ResultItem $oItem */
		$oItem = $this->getScanItem();
		if ( $oItem->context == 'plugins' ) {
			$oAsset = Services::WpPlugins()->getPluginAsVo( $oItem->slug );
			$bCanRepair = ( $oAsset instanceof VOs\WpPluginVo && $oAsset->isWpOrg() && $oAsset->svn_uses_tags );
		}
		else {
			$oAsset = Services::WpThemes()->getThemeAsVo( $oItem->slug );
			$bCanRepair = ( $oAsset instanceof VOs\WpThemeVo && $oAsset->isWpOrg() );
		}
		return $bCanRepair;
	}
}