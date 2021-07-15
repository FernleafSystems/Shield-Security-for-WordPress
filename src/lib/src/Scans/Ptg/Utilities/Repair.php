<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class Repair extends Scans\Base\Utilities\BaseRepair {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() :bool {
		/** @var Ptg\ResultItem $item */
		$item = $this->getScanItem();

		if ( $this->canRepair() ) {
			if ( $item->context == 'plugins' ) {
				$success = $this->repairPluginFile( $item->path_full );
			}
			else {
				$success = $this->repairThemeFile( $item->path_full );
			}
		}
		elseif ( $this->isAllowDelete() ) {
			$success = (bool)Services::WpFs()->deleteFile( $item->path_full );
		}
		else {
			$success = false;
		}

		return $success;
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	private function repairPluginFile( $path ) :bool {
		$success = false;
		$files = new WpOrg\Plugin\Files();
		try {
			if ( $files->isValidFileFromPlugin( $path ) ) {
				$success = $files->replaceFileFromVcs( $path );
			}
			elseif ( $this->isAllowDelete() ) {
				$success = (bool)Services::WpFs()->deleteFile( $path );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}
		return $success;
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	private function repairThemeFile( $path ) :bool {
		$success = false;
		$files = new WpOrg\Theme\Files();
		try {
			if ( $files->isValidFileFromTheme( $path ) ) {
				$success = $files->replaceFileFromVcs( $path );
			}
			elseif ( $this->isAllowDelete() ) {
				$success = (bool)Services::WpFs()->deleteFile( $path );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}
		return $success;
	}

	public function canRepair() :bool {
		/** @var Ptg\ResultItem $item */
		$item = $this->getScanItem();
		if ( $item->context == 'plugins' ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $item->slug );
			$canRepair = $asset->asset_type === 'plugin'
						 && $asset->isWpOrg() && $asset->svn_uses_tags;
		}
		else {
			$asset = Services::WpThemes()->getThemeAsVo( $item->slug );
			$canRepair = $asset->asset_type === 'theme' && $asset->isWpOrg();
		}
		return $canRepair;
	}
}