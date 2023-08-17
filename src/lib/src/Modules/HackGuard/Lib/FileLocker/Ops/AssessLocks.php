<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops\Update;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	public function run() {
		/** @var Update $updater */
		$updater = $this->mod()->getDbH_FileLocker()->getQueryUpdater();

		$this->removeDuplicates();

		$locksChanged = false;
		foreach ( $this->getFileLocks() as $lock ) {
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
			$this->clearFileLocksCache();
		}
	}

	private function removeDuplicates() {
		$paths = [];
		foreach ( $this->getFileLocks() as $lock ) {
			if ( \in_array( $lock->path, $paths ) ) {
				$this->mod()
					 ->getDbH_FileLocker()
					 ->getQueryDeleter()
					 ->deleteById( $lock->id );
			}
			else {
				$paths[] = $lock->path;
			}
		}
		if ( \count( $this->getFileLocks() ) !== \count( $paths ) ) {
			$this->clearFileLocksCache();
		}
	}
}