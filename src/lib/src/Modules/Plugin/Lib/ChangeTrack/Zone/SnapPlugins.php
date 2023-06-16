<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\ZoneReportPlugins;
use FernleafSystems\Wordpress\Services\Services;

class SnapPlugins extends SnapBasePluginsThemes {

	public const SLUG = 'plugins';

	public function getZoneReporterClass() :string {
		return ZoneReportPlugins::class;
	}

	/**
	 * @return array[] - key is plugin file, values are arrays with keys: file, version, is_active, has_updates
	 */
	public function snap() :array {
		$items = [];
		foreach ( Services::WpPlugins()->getPluginsAsVo() as $file => $plugin ) {
			$items[ $file ] = [
				'uniq'       => $file,
				'name'       => $plugin->Name,
				'version'    => $plugin->Version,
				'is_active'  => $plugin->active,
				'has_update' => $plugin->hasUpdate(),
			];
		}
		return $items;
	}
}