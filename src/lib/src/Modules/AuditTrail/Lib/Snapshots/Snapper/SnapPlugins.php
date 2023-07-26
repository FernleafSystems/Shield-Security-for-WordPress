<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Services\Services;

class SnapPlugins extends SnapBasePluginsThemes {

	/**
	 * @return array[] - key is plugin file, values are arrays with keys: file, version, is_active, has_updates
	 */
	public function snap() :array {
		$WPP = Services::WpPlugins();
		$items = [];
		foreach ( $WPP->getInstalledPluginFiles() as $file ) {
			$plugin = $WPP->getPluginAsVo( $file, true );
			$items[ $file ] = [
				'uniq'      => $file,
				'name'      => $plugin->Name,
				'version'   => $plugin->Version,
				'is_active' => $plugin->active,
			];
		}
		return $items;
	}
}