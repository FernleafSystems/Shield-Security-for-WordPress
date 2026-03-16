<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

trait MaintenanceAssetFixtures {

	/**
	 * @return array<string,object>
	 */
	protected function buildMaintenanceAssetServiceItems( array $pluginFixture = [], array $themeFixture = [] ) :array {
		$pluginFixture = \array_merge( [
			'updates'       => [],
			'plugins'       => [],
			'active'        => [],
			'plugin_vos'    => [],
			'activate_urls' => [],
			'upgrade_urls'  => [],
		], $pluginFixture );
		$themeFixture = \array_merge( [
			'updates'        => [],
			'themes'         => [],
			'theme_vos'      => [],
			'current'        => '',
			'current_parent' => '',
		], $themeFixture );

		return [
			'service_data' => new MaintenanceDataService(),
			'service_wpfs' => new MaintenanceFsService(),
			'service_wpgeneral' => new MaintenanceGeneralService(),
			'service_wpplugins' => new MaintenancePluginsService( $pluginFixture ),
			'service_wpthemes' => new MaintenanceThemesService( $themeFixture ),
		];
	}

	protected function buildMaintenancePluginVo( string $file, string $title, string $version ) :WpPluginVo {
		return new MaintenancePluginVo( $file, $title, $version );
	}

	protected function buildMaintenanceThemeVo( string $stylesheet, string $name, string $version ) :WpThemeVo {
		return new MaintenanceThemeVo( $stylesheet, $name, $version );
	}
}
