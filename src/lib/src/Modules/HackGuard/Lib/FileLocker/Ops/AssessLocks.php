<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	public function run() {
		// @deprecated 19.2 - required for upgrade from 19.0
		if ( \is_null( self::con()->comps ) || \is_null( self::con()->comps->file_locker ) ) {
			return;
		}

		$this->removeDuplicates();

		$locksChanged = false;
		foreach ( self::con()->comps->file_locker->getLocks() as $lock ) {
			try {
				if ( ( new CompareHash() )->isEqualFileSha1( $lock->path, $lock->hash_original ) ) {
					if ( !empty( $lock->hash_current ) ) {
						$this->getUpdater()->updateCurrentHash( $lock );
						$locksChanged = true;
					}
				}
				else {
					$fileHash = \hash_file( 'sha1', $lock->path );
					if ( !empty( $fileHash ) && !\hash_equals( $lock->hash_current, $fileHash ) ) {
						$this->getUpdater()->updateCurrentHash( $lock, $fileHash );
						$locksChanged = true;
					}
				}
			}
			catch ( \InvalidArgumentException $e ) {
				$this->getUpdater()->markProblem( $lock );
				$locksChanged = true;
			}
		}

		if ( $locksChanged ) {
			self::con()->comps->file_locker->clearLocks();
		}
	}

	/**
	 * Required for upgrades from 19.0
	 * @return FileLockerDB\Update
	 */
	private function getUpdater() {
		$dbCon = self::con()->db_con;
		return ( $dbCon->file_locker !== null ? $dbCon->file_locker : $dbCon->dbhFileLocker() )->getQueryUpdater();
	}

	private function removeDuplicates() {
		$paths = [];
		foreach ( self::con()->comps->file_locker->getLocks() as $lock ) {
			if ( \in_array( $lock->path, $paths ) ) {
				( new DeleteFileLock() )->delete( $lock );
			}
			else {
				$paths[] = $lock->path;
			}
		}
	}
}