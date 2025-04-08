<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Exceptions\InconsistentDataException;
use FernleafSystems\Wordpress\Services\Services;

class SnapThemes extends SnapBasePluginsThemes {

	/**
	 * @return array[] - key is stylesheet, values are arrays with keys: file, version, is_active, has_updates
	 * @throws InconsistentDataException
	 */
	public function snap() :array {
		$WPT = Services::WpThemes();
		$items = [];
		foreach ( $WPT->getInstalledStylesheets() as $stylesheet ) {
			$theme = $WPT->getThemeAsVo( $stylesheet, true );
			/**
			 * Customer reported Fatal Error for theme "Edubin" where during snapshots when Theme editor was loading
			 * the WP Theme wasn't being property loaded and many properties, including Name, Version, were bool(false)
			 *
			 * This completely screws up the snapshotting and have no idea why WP wouldn't be initialising the
			 * underlying WP Theme object correctly. So we throw an "Inconsistent Data" exception and prevent
			 * the snapshot from being stored/used.
			 */
			if ( $theme->wp_theme->get( 'Version' ) === false || $theme->wp_theme->get( 'Name' ) === false ) {
				throw new InconsistentDataException( sprintf( 'Name/Version for theme "%s" is set to false.', $stylesheet ) );
			}

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