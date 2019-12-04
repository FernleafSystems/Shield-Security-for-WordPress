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
		$oFiles = new WpOrg\Plugin\Files();
		try {
			if ( $oFiles->isValidFileFromPlugin( $oItem->path_full ) ) {
				$bSuccess = $oFiles->replaceFileFromVcs( $oItem->path_full );
			}
			elseif ( $this->isAllowDelete() ) {
				$bSuccess = Services::WpFs()->deleteFile( $oItem->path_full );
			}
			else {
				$bSuccess = false;
			}
		}
		catch ( \InvalidArgumentException $oE ) {
			$bSuccess = false;
		}
		return (bool)$bSuccess;
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