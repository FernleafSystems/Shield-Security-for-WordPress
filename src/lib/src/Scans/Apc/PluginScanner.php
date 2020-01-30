<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PluginScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 */
class PluginScanner {

	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @param string $sPluginFile
	 * @return ResultItem|null
	 */
	public function scan( $sPluginFile ) {
		$oResultItem = null;

		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oPlgn = Services::WpPlugins()->getPluginAsVo( $sPluginFile );
		if ( $oPlgn instanceof WpPluginVo && $oPlgn->isWpOrg() ) {
			$nLastUpdatedAt = $this->getLastUpdateTime( $sPluginFile );
			if ( $nLastUpdatedAt > 0
				 && ( Services::Request()->ts() - $nLastUpdatedAt > $oAction->abandoned_limit ) ) {

				$oResultItem = new ResultItem();
				$oResultItem->slug = $sPluginFile;
				$oResultItem->context = 'plugins';
				$oResultItem->last_updated_at = $nLastUpdatedAt;
			}
		}

		return $oResultItem;
	}

	/**
	 * @param string $sFile
	 * @return bool
	 */
	private function getLastUpdateTime( $sFile ) {
		$sSlug = Services::WpPlugins()->getSlug( $sFile );
		if ( empty( $sSlug ) ) {
			$sSlug = dirname( $sFile );
		}

		if ( !function_exists( 'plugins_api' ) ) {
			require_once ABSPATH.'/wp-admin/includes/plugin-install.php';
		}
		$oApi = plugins_api( 'plugin_information', [
			'slug'   => $sSlug,
			'fields' => [
				'sections' => false,
			],
		] );

		return isset( $oApi->last_updated ) ? strtotime( $oApi->last_updated ) : -1;
	}
}