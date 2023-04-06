<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;

class DeleteFileLock extends BaseOps {

	public function delete( ?FileLockerDB\Record $lock = null ) :bool {
		if ( empty( $lock ) ) {
			$lock = $this->findLockRecordForFile();
		}
		$success = $lock instanceof FileLockerDB\Record
				   && $this->mod()
						   ->getDbH_FileLocker()
						   ->getQueryDeleter()
						   ->deleteRecord( $lock );
		if ( $success ) {
			$this->clearFileLocksCache();
		}
		return $success;
	}
}