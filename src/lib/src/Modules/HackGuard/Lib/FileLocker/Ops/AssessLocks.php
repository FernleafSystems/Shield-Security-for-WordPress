<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	/**
	 * @return int[]
	 */
	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var FileLocker\Update $updater */
		$updater = $mod->getDbHandler_FileLocker()->getQueryUpdater();

		$this->removeDuplicates();

		$aProblemIds = [];
		foreach ( $this->getFileLocks() as $lock ) {
			try {
				if ( ( new CompareHash() )->isEqualFileSha1( $lock->file, $lock->hash_original ) ) {
					if ( !empty( $lock->hash_current ) ) {
						$updater->updateCurrentHash( $lock, '' );
					}
				}
				else {
					$fileHash = hash_file( 'sha1', $lock->file );
					if ( empty( $lock->hash_current ) || !hash_equals( $lock->hash_current, $fileHash ) ) {
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
				$mod->getDbHandler_FileLocker()
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