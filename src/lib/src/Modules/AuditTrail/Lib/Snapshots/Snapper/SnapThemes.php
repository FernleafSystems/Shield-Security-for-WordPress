<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Services\Services;

class SnapThemes extends SnapBasePluginsThemes {

	/**
	 * @return array[] - key is stylesheet, values are arrays with keys: file, version, is_active, has_updates
	 */
	public function snap() :array {
		$WPT = Services::WpThemes();
		$items = [];
		foreach ( $WPT->getInstalledStylesheets() as $stylesheet ) {
			$theme = $WPT->getThemeAsVo( $stylesheet, true );
			$items[ $theme->stylesheet ] = [
				'uniq'      => $theme->stylesheet,
				'name'      => $theme->Name,
				'version'   => $theme->Version,
				'is_active' => $theme->active,
				'is_child'  => $theme->is_child,
				'is_parent' => $theme->is_parent,
			];
		}
		return $items;
	}
}
