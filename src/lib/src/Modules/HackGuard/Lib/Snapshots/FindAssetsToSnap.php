<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;

class FindAssetsToSnap {

	use ModConsumer;

	/**
	 * @return WpPluginVo[]|WpThemeVo[]
	 */
	public function run() {
		$aAssets = [];

		$oLoader = ( new StoreAction\Load() )->setMod( $this->getMod() );
		foreach ( Services::WpPlugins()->getPluginsAsVo() as $oAsset ) {
			if ( $oAsset->active ) {
				try {
					$aMeta = $oLoader->setAsset( $oAsset )
									 ->run()
									 ->getSnapMeta();
				}
				catch ( \Exception $oE ) {
					$aMeta = null;
				}
				if ( empty( $aMeta ) || $oAsset->Version !== $aMeta[ 'version' ] ) {
					$aAssets[] = $oAsset;
				}
			}
		}

		$oWPT = Services::WpThemes();
		$oAsset = $oWPT->getThemeAsVo( $oWPT->getCurrent()->get_stylesheet() );
		try {
			$aMeta = $oLoader->setAsset( $oAsset )
							 ->run()
							 ->getSnapMeta();
		}
		catch ( \Exception $oE ) {
			$aMeta = null;
		}
		if ( empty( $aMeta ) || $oAsset->version !== $aMeta[ 'version' ] ) {
			$aAssets[] = $oAsset;
		}

		if ( $oWPT->isActiveThemeAChild() ) {
			$oAsset = $oWPT->getThemeAsVo( $oAsset->wp_theme->get_template() );
			try {
				$aMeta = $oLoader->setAsset( $oAsset )
								 ->run()
								 ->getSnapMeta();
			}
			catch ( \Exception $oE ) {
				$aMeta = null;
			}
			if ( empty( $aMeta ) || $oAsset->version !== $aMeta[ 'version' ] ) {
				$aAssets[] = $oAsset;
			}
		}

		return $aAssets;
	}
}