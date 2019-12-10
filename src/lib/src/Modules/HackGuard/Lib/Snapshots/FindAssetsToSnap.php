<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

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

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		foreach ( Services::WpPlugins()->getPluginsAsVo() as $oAsset ) {
			if ( $oAsset->active ) {
				$aMeta = ( new Store( $oAsset ) )
					->setWorkingDir( $oMod->getPtgSnapsBaseDir() )
					->getSnapMeta();
				if ( empty( $aMeta ) || $oAsset->Version !== $aMeta[ 'version' ] ) {
					$aAssets[] = $oAsset;
				}
			}
		}

		$oWPT = Services::WpThemes();
		$oAsset = $oWPT->getThemeAsVo( $oWPT->getCurrent()->get_stylesheet() );
		$aMeta = ( new Store( $oAsset ) )
			->setWorkingDir( $oMod->getPtgSnapsBaseDir() )
			->getSnapMeta();
		if ( empty( $aMeta ) || $oAsset->version !== $aMeta[ 'version' ] ) {
			$aAssets[] = $oAsset;
		}

		if ( $oWPT->isActiveThemeAChild() ) {
			$oAsset = $oWPT->getThemeAsVo( $oAsset->wp_theme->get_template() );
			$aMeta = ( new Store( $oAsset ) )
				->setWorkingDir( $oMod->getPtgSnapsBaseDir() )
				->getSnapMeta();
			if ( empty( $aMeta ) || $oAsset->version !== $aMeta[ 'version' ] ) {
				$aAssets[] = $oAsset;
			}
		}

		return $aAssets;
	}
}