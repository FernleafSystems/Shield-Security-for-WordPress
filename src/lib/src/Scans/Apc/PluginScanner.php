<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PluginScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 */
class PluginScanner {

	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @param string $pluginFile
	 * @return ResultItem|null
	 */
	public function scan( $pluginFile ) {
		$oResultItem = null;

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$plugin = Services::WpPlugins()->getPluginAsVo( $pluginFile );
		if ( $plugin->asset_type === 'plugin' && $plugin->isWpOrg() ) {
			$nLastUpdatedAt = $this->getLastUpdateTime( $pluginFile );
			if ( $nLastUpdatedAt > 0
				 && ( Services::Request()->ts() - $nLastUpdatedAt > $oAction->abandoned_limit ) ) {

				$oResultItem = new ResultItem();
				$oResultItem->slug = $pluginFile;
				$oResultItem->context = 'plugins';
				$oResultItem->last_updated_at = $nLastUpdatedAt;
			}
		}

		return $oResultItem;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	private function getLastUpdateTime( $file ) {
		$slug = Services::WpPlugins()->getSlug( $file );
		if ( empty( $slug ) ) {
			$slug = dirname( $file );
		}

		if ( !function_exists( 'plugins_api' ) ) {
			require_once ABSPATH.'/wp-admin/includes/plugin-install.php';
		}
		$api = plugins_api( 'plugin_information', [
			'slug'   => $slug,
			'fields' => [
				'sections' => false,
			],
		] );

		return isset( $api->last_updated ) ? strtotime( $api->last_updated ) : -1;
	}
}