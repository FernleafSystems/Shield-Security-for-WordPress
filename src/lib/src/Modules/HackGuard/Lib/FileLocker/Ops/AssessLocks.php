<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	public function run() {
		/** @var FileLockerDB\Update $updater */
		$updater = self::con()->db_con->file_locker->getQueryUpdater();

		$this->removeDuplicates();

		$locksChanged = false;
		foreach ( self::con()->comps->file_locker->getLocks() as $lock ) {
			try {
				if ( ( new CompareHash() )->isEqualFileSha1( $lock->path, $lock->hash_original ) ) {
					if ( !empty( $lock->hash_current ) ) {
						$updater->updateCurrentHash( $lock );
						$locksChanged = true;
					}
				}
				else {
					$fileHash = \hash_file( 'sha1', $lock->path );
					if ( !empty( $fileHash ) && !\hash_equals( $lock->hash_current, $fileHash ) ) {
						$updater->updateCurrentHash( $lock, $fileHash );
						$locksChanged = true;
					}
				}
			}
			catch ( \InvalidArgumentException $e ) {
				$updater->markProblem( $lock );
				$locksChanged = true;
			}
		}

		if ( $locksChanged ) {
			self::con()->comps->file_locker->clearLocks();
		}
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