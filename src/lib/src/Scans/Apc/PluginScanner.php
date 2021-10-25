<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

class PluginScanner {

	use Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @return ResultItem|null
	 */
	public function scan( string $pluginFile ) {
		$item = null;

		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$plugin = Services::WpPlugins()->getPluginAsVo( $pluginFile );
		if ( $plugin->asset_type === 'plugin' && $plugin->isWpOrg() ) {
			$lastUpdatedAt = $this->getLastUpdateTime( $plugin );
			if ( $lastUpdatedAt > 0
				 && ( Services::Request()->ts() - $lastUpdatedAt > $action->abandoned_limit ) ) {

				/** @var ResultItem $item */
				$item = $this->getScanController()->getNewResultItem();
				$item->slug = $pluginFile;
				$item->last_updated_at = $lastUpdatedAt;
			}
		}

		return $item;
	}

	private function getLastUpdateTime( WpPluginVo $plugin )  :int {
		$lastUpdate = -1;

		$slug = $plugin->slug;
		if ( !empty( $slug ) ) {
			if ( !function_exists( 'plugins_api' ) ) {
				require_once ABSPATH.'/wp-admin/includes/plugin-install.php';
			}
			$api = plugins_api( 'plugin_information', [
				'slug'   => $slug,
				'fields' => [
					'sections' => false,
				],
			] );
			if ( isset( $api->last_updated ) ) {
				$lastUpdate = strtotime( $api->last_updated );
			}
		}

		return (int)$lastUpdate;
	}
}