<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;

class DeleteFileLock extends BaseOps {

	public function delete( ?FileLockerDB\Record $lock = null ) :bool {
		if ( empty( $lock ) ) {
			$lock = $this->findLockRecordForFile();
		}
		$success = $lock instanceof FileLockerDB\Record
				   && self::con()
					   ->db_con
					   ->dbhFileLocker()
					   ->getQueryDeleter()
					   ->deleteRecord( $lock );
		if ( $success ) {
			$this->mod()->getFileLocker()->clearLocks();
		}
		return $success;
	}
}