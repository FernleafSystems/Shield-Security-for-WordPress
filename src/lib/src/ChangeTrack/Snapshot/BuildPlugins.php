<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildPlugins
 * @package FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot
 */
class BuildPlugins {

	/**
	 * @return array[] - key is plugin file, values are arrays with keys: file, version, is_active, has_updates
	 */
	public function run() {
		return $this->getItems();
	}

	/**
	 * @return array
	 */
	private function getItems() {
		$oWp = Services::WpPlugins();
		$aItems = [];
		foreach ( $oWp->getPlugins() as $sFile => $aData ) {
			$aItems[ $sFile ] = [
				'uniq'       => $sFile,
				'version'    => $aData[ 'Version' ],
				'is_active'  => $oWp->isActive( $sFile ),
				'has_update' => $oWp->isUpdateAvailable( $sFile ),
			];
		}
		return $aItems;
	}
}
