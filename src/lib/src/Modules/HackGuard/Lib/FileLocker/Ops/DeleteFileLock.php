<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;

class DeleteFileLock extends BaseOps {

	public function delete( FileLockerDB\Record $lock ) :void {
		self::con()
			->db_con
			->dbhFileLocker()
			->getQueryDeleter()
			->deleteRecord( $lock );
		$this->mod()->getFileLocker()->clearLocks();
	}
}