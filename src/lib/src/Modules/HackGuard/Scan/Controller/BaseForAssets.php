<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseForAssets extends Base {

	/**
	 * @param Scans\Ptg\ResultItem|Scans\Wpv\ResultItem|Scans\Apc\ResultItem $item
	 * @return bool
	 */
	protected function isResultItemStale( $item ) :bool {
		if ( $item->context == 'plugins' ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->slug );
			$stale = empty( $asset ) || !$asset->active;
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->slug );
			$stale = empty( $asset ) || ( !$asset->active && !$asset->is_parent );
		}
		return $stale;
	}
}