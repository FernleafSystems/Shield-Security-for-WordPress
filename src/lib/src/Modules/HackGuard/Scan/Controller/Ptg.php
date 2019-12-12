<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class Ptg extends BaseForAssets {

	/**
	 * @return Scans\Ptg\Utilities\ItemActionHandler
	 */
	protected function getItemActionHandler() {
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
		return $oOpts->isPtgEnabled();
	}

	/**
	 * @return bool
	 */
	public function isScanningAvailable() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		return parent::isScanningAvailable() && $oMod->canPtgWriteToDisk();
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