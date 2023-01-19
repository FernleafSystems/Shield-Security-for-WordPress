<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

class DeleteFileLock extends BaseOps {

	public function delete( ?FileLockerDB\Record $lock = null ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( empty( $lock ) ) {
			$lock = $this->findLockRecordForFile();
		}
		$success = $lock instanceof FileLockerDB\Record
				   && $mod->getDbH_FileLocker()
						  ->getQueryDeleter()
						  ->deleteRecord( $lock );
		if ( $success ) {
			$this->clearFileLocksCache();
		}
		return $success;
	}
}