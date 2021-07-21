<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() :bool {
		if ( !$this->canRepair() ) {
			throw new \Exception( 'An update is not currently available.' );
		}

		/** @var Wpv\ResultItem $item */
		$item = $this->getScanItem();

		$data = ( $item->context == 'plugins' ) ?
			Services::WpPlugins()->update( $item->slug )
			: Services::WpThemes()->update( $item->slug );

		return (bool)$data[ 'successful' ];
	}

	public function canRepair() :bool {
		/** @var Wpv\ResultItem $item */
		$item = $this->getScanItem();

		if ( $item->context == 'plugins' ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->slug );
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->slug );
		}
		return ( $asset instanceof Assets\WpPluginVo && $asset->hasUpdate() );
	}
}