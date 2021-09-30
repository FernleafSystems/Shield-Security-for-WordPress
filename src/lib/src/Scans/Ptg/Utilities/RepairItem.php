<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\{
	Base\Utilities\RepairItemBase,
	Ptg
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class RepairItem extends RepairItemBase {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairItem() :bool {
		/** @var Ptg\ResultItem $item */
		$item = $this->getScanItem();

		if ( $this->canRepair() ) {
			$success = ( $item->context == 'plugins' ) ?
				$this->repairPluginFile( $item->path_full )
				: $this->repairThemeFile( $item->path_full );
		}
		else {
			$success = false;
		}

		return $success;
	}

	private function repairPluginFile( string $path ) :bool {
		$success = false;
		$files = new WpOrg\Plugin\Files();
		try {
			if ( $files->isValidFileFromPlugin( $path ) ) {
				$success = $files->replaceFileFromVcs( $path );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}
		return $success;
	}

	private function repairThemeFile( string $path ) :bool {
		$success = false;
		$files = new WpOrg\Theme\Files();
		try {
			if ( $files->isValidFileFromTheme( $path ) ) {
				$success = $files->replaceFileFromVcs( $path );
			}
		}
		catch ( \InvalidArgumentException $e ) {
		}
		return $success;
	}

	public function canRepair() :bool {
		/** @var Ptg\ResultItem $item */
		$item = $this->getScanItem();

		if ( $item->is_unrecognised ) {
			$canRepair = false;
		}
		elseif ( $item->context == 'plugins' ) {
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