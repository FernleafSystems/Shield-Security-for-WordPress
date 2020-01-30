<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() {
		if ( !$this->canRepair() ) {
			throw new \Exception( 'An update is not currently available.' );
		}

		/** @var Wpv\ResultItem $oItem */
		$oItem = $this->getScanItem();

		$aData = ( $oItem->context == 'plugins' ) ?
			Services::WpPlugins()->update( $oItem->slug )
			: Services::WpThemes()->update( $oItem->slug );

		return $aData[ 'successful' ];
	}

	/**
	 * @return bool
	 */
	public function canRepair() {
		/** @var Wpv\ResultItem $oItem */
		$oItem = $this->getScanItem();

		if ( $oItem->context == 'plugins' ) {
			$oAsset = Services::WpPlugins()->getPluginAsVo( $oItem->slug );
		}
		else {
			$oAsset = Services::WpThemes()->getThemeAsVo( $oItem->slug );
		}
		return ( $oAsset instanceof VOs\WpPluginVo && $oAsset->hasUpdate() );
	}
}