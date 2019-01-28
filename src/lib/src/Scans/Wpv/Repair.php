<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class Repair extends Scans\Base\BaseRepair {

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem( $oItem ) {

		$oWpPlugins = Services::WpPlugins();
		$oWpThemes = Services::WpThemes();
		if ( $oItem->context == 'plugins' && $oWpPlugins->isUpdateAvailable( $oItem->slug ) ) {
			$oWpPlugins->update( $oItem->slug );
		}
		else if ( $oItem->context == 'themes' && $oWpThemes->isUpdateAvailable( $oItem->slug ) ) {
			$oWpThemes->update( $oItem->slug );
		}
		else {
			throw new \Exception( 'Update not available.' );
		}

		return true;
	}
}