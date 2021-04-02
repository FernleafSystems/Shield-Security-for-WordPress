<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

class DeleteFileLock extends BaseOps {

	/**
	 * @param FileLocker\EntryVO|null $lock
	 * @return bool
	 */
	public function delete( $lock = null ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( empty( $lock ) ) {
			$lock = $this->findLockRecordForFile();
		}
		$success = $lock instanceof FileLocker\EntryVO
				   && $mod->getDbHandler_FileLocker()
						  ->getQueryDeleter()
						  ->deleteEntry( $lock );
		if ( $success ) {
			$this->clearFileLocksCache();
		}
		return $success;
	}
}