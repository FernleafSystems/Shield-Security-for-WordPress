<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

/**
 * Class DeleteFileLock
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class DeleteFileLock extends BaseOps {

	/**
	 * @param FileLocker\EntryVO $oLock
	 * @return bool
	 */
	public function delete( $oLock = null ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( empty( $oLock ) ) {
			$oLock = $this->findLockRecordForFile();
		}
		$bSuccess = $oLock instanceof FileLocker\EntryVO
					&& $mod->getDbHandler_FileLocker()
							->getQueryDeleter()
							->deleteEntry( $oLock );
		if ( $bSuccess ) {
			$this->clearFileLocksCache();
		}
		return $bSuccess;
	}
}