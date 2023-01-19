<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	/**
	 * @return int[]
	 */
	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var FileLockerDB\Update $updater */
		$updater = $mod->getDbH_FileLocker()->getQueryUpdater();

		$this->removeDuplicates();

		$aProblemIds = [];
		foreach ( $this->getFileLocks() as $lock ) {
			try {
				if ( ( new CompareHash() )->isEqualFileSha1( $lock->file, $lock->hash_original ) ) {
					if ( !empty( $lock->hash_current ) ) {
						$updater->updateCurrentHash( $lock );
					}
				}
				else {
					$fileHash = hash_file( 'sha1', $lock->file );
					if ( !empty( $fileHash ) && !hash_equals( $lock->hash_current, $fileHash ) ) {
						$updater->updateCurrentHash( $lock, $fileHash );
						$aProblemIds[] = $lock->id;
					}
				}
			}
			catch ( \InvalidArgumentException $e ) {
				$updater->markProblem( $lock );
				$aProblemIds[] = $lock->id;
			}
		}
		$this->clearFileLocksCache();
		return $aProblemIds;
	}

	private function removeDuplicates() {
		$paths = [];
		foreach ( $this->getFileLocks() as $lock ) {
			if ( in_array( $lock->file, $paths ) ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getDbH_FileLocker()
					->getQueryDeleter()
					->deleteById( $lock->id );
			}
			else {
				$paths[] = $lock->file;
			}
		}
		if ( count( $this->getFileLocks() ) != count( $paths ) ) {
			$this->clearFileLocksCache();
		}
	}
}