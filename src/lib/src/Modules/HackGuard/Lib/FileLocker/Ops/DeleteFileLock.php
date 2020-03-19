<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

/**
 * Class DeleteFileLock
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class DeleteFileLock extends BaseOps {

	/**
	 * @return bool
	 */
	public function delete() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();
		$oLockRecord = $this->findLockRecordForFile();
		$bSuccess = $oLockRecord instanceof FileLocker\EntryVO
					&& $oDbH->getQueryDeleter()->deleteEntry( $oLockRecord );
		if ( $bSuccess ) {
			$this->clearFileLocksCache();
		}
		return $bSuccess;
	}
}