<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\ZoneReportThemes;
use FernleafSystems\Wordpress\Services\Services;

class SnapThemes extends SnapBasePluginsThemes {

	public const SLUG = 'themes';

	public function getZoneReporterClass() :string {
		return ZoneReportThemes::class;
	}

	/**
	 * @return array[] - key is stylesheet, values are arrays with keys: file, version, is_active, has_updates
	 */
	public function snap() :array {
		$items = [];
		foreach ( Services::WpThemes()->getThemesAsVo() as $theme ) {
			$items[ $theme->stylesheet ] = [
				'uniq'       => $theme->stylesheet,
				'name'       => $theme->Name,
				'version'    => $theme->Version,
				'has_update' => $theme->hasUpdate(),
				'is_active'  => $theme->active,
				'is_child'   => $theme->is_child,
				'is_parent'  => $theme->is_parent,
			];
		}
		return $items;
	}
}
