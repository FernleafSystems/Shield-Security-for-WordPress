<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildThemes
 * @package FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot
 */
class BuildThemes {

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
		$oWp = Services::WpThemes();
		$aItems = [];
		foreach ( $oWp->getThemes() as $oItem ) {
			$sFile = $oItem->get_stylesheet();
			$bActive = $oWp->isActive( $sFile );
			$aItems[ $sFile ] = [
				'uniq'       => $sFile,
				'name'       => $oItem->get( 'Name' ),
				'version'    => $oItem->get( 'Version' ),
				'has_update' => $oWp->isUpdateAvailable( $sFile ),
				'is_active'  => $bActive,
				'is_child'   => $bActive && $oWp->isActiveThemeAChild(),
				'is_parent'  => !$bActive && $oWp->isActiveParent( $sFile ),
			];
		}
		return $aItems;
	}
}
