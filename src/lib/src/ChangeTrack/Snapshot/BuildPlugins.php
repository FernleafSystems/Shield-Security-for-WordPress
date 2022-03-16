<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

use FernleafSystems\Wordpress\Services\Services;

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
		$aItems = [];
		foreach ( Services::WpPlugins()->getPluginsAsVo() as $sFile => $oPlugin ) {
			$aItems[ $sFile ] = [
				'uniq'       => $sFile,
				'name'       => $oPlugin->Name,
				'version'    => $oPlugin->Version,
				'is_active'  => $oPlugin->active,
				'has_update' => $oPlugin->hasUpdate(),
			];
		}
		return $aItems;
	}
}
