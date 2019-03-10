<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Realtime extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		parent::run();
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		if ( $oMod->isRtEnabledWpConfig() ) {
			$this->runWpConfig();
		}
	}

	private function runWpConfig() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oProc = new Shield\Scans\Realtime\Files\Process();

		/** @var ICWP_WPSF_FeatureHandler_Plugin $oModPlugin */
		$oModPlugin = $this->getCon()->getModule( 'plugin' );
		$oProc->priv_key = $oModPlugin->getOpenSslPrivateKey();
		$oProc->original_path = Services::WpGeneral()->getPath_WpConfig();
		$oProc->original_path_hash = $oMod->getRtHashForFile( 'wpconfig' );
		$oProc->backup_file = $oMod->getRtBackupFileNameForFile( 'wpconfig' );
		$oProc->backup_dir = $this->getCon()->getPath_PluginCache();

		// This is going to create the new backup file
		$bNeedStoreHashAndPath = empty( $oProc->backup_file );
		try {
			if ( $oProc->run() && $bNeedStoreHashAndPath ) {
				$oProc->backup_file = $oMod->setRtBackupFileNameForFile( 'wpconfig', $oProc->backup_file );
				$oProc->original_path_hash = $oMod->setRtHashForFile( 'wpconfig', $oProc->original_path_hash );
			}
		}
		catch ( \Exception $oE ) {
			$this->handleErrorCode( $oE->getCode() );
		}
	}

	private function handleErrorCode( $nCode ) {
		switch ( $nCode ) {

			case 1:
				break;

			case 2:
				break;

			default:
				break;
		}
	}
}