<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class Ptg extends BaseForAssets {

	/**
	 * @return Scans\Ptg\ResultsSet
	 */
	protected function getItemsToAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		/** @var Scans\Ptg\ResultsSet $oRes */
		$oRes = parent::getItemsToAutoRepair();

		if ( !$oOpts->isRepairFilePlugin() || !$oOpts->isRepairFileTheme() ) {
			if ( $oOpts->isRepairFileTheme() ) {
				$oRes = $oRes->getResultsForThemesContext();
			}
			elseif ( $oOpts->isRepairFilePlugin() ) {
				$oRes = $oRes->getResultsForPluginsContext();
			}
		}

		return $oRes;
	}

	/**
	 * @return bool
	 */
	public function isCronAutoRepair() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isRepairFilePlugin() || $oOpts->isRepairFileTheme();
	}

	/**
	 * @param Scans\Mal\ResultItem $oItem
	 * @return bool
	 */
	protected function isResultItemStale( $oItem ) {
		$bStale = false;
		$oAsset = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $oItem->path_full );
		if ( empty( $oAsset ) ) {
			$oAsset = ( new WpOrg\Theme\Files() )->findThemeFromFile( $oItem->path_full );
			$bStale = empty( $oAsset );
		}
		return $bStale;
	}

	/**
	 * @return Scans\Ptg\Utilities\ItemActionHandler
	 */
	protected function newItemActionHandler() {
		return new Scans\Ptg\Utilities\ItemActionHandler();
	}

	/**
	 * @param string $sBaseFile
	 * @return bool
	 */
	public function actionPluginReinstall( $sBaseFile ) {
		$bSuccess = false;
		$oWpPs = Services::WpPlugins();
		$oPl = $oWpPs->getPluginAsVo( $sBaseFile );
		if ( $oPl->isWpOrg() && $oWpPs->reinstall( $oPl->file ) ) {
			try {
				( new HackGuard\Lib\Snapshots\StoreAction\Build() )
					->setMod( $this->getMod() )
					->setAsset( $oPl )
					->run();
				$bSuccess = true;
			}
			catch ( \Exception $oE ) {
			}
		}
		return $bSuccess;
	}

	/**
	 * @return bool
	 */
	public function isEnabled() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->isOpt( 'ptg_enable', 'Y' ) && $oOpts->isOptReqsMet( 'ptg_enable' );
	}

	/**
	 * @return bool
	 */
	public function isScanningAvailable() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		return parent::isScanningAvailable()
			   && $this->getOptions()->isOptReqsMet( 'ptg_enable' )
			   && $oMod->canCacheDirWrite();
	}

	/**
	 * Since we can't track site assets while the plugin is inactive, our snapshots and results
	 * are unreliable once the plugin has been deactivated.
	 */
	public function purge() {
		parent::purge();
		( new HackGuard\Lib\Snapshots\StoreAction\DeleteAll() )
			->setMod( $this->getMod() )
			->run();
	}
}