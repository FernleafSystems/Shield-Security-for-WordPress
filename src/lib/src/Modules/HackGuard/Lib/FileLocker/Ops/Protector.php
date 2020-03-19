<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

class Protector extends BaseOps {

	public function analyse() {
		$oRecord = $this->findLockRecordForFile();
		if ( empty( $oRecord ) ) { // create new lock
			( new CreateFileLock( $this->oFile ) )
				->setMod( $this->getMod() )
				->create();
		}
		elseif ( !( new Ops\Verify() )->run( $oRecord ) ) { // repair locked file.
			( new Revert( $this->oFile ) )
				->setMod( $this->getMod() )
				->run( $oRecord );
		}
	}


//	public function run() {
//		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
//		$oMod = $this->getMod();
//		$oProc = new Process();
//
//		$oModPlugin = $this->getCon()->getModule_Plugin();
//		$oProc->priv_key = $oModPlugin->getOpenSslPrivateKey();
//		$oProc->original_path = Services::WpGeneral()->getPath_WpConfig();
//		$oProc->original_path_hash = $oMod->getRtFileHash( $oProc->original_path );
//		$oProc->backup_file = $oMod->getRtFileBackupName( $oProc->original_path );
//		$oProc->backup_dir = $this->getCon()->getPath_PluginCache();
//
//		// This is going to create the new backup file
//		$bNeedStoreHashAndPath = empty( $oProc->backup_file );
//		try {
//			if ( $oProc->run() && $bNeedStoreHashAndPath ) {
//				$oProc->backup_file = $oMod->setRtFileBackupName( $oProc->original_path, $oProc->backup_file );
//				$oProc->original_path_hash = $oMod->setRtFileHash( $oProc->original_path, $oProc->original_path_hash );
//			}
//		}
//		catch ( \Exception $oE ) {
//			$this->handleErrorCode( $oE->getCode() );
//		}
//	}

}