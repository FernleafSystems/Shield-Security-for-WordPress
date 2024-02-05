<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops\Update;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	public function run() {
		$FLCon = $this->mod()->getFileLocker();
		/** @var Update $updater */
		$updater = self::con()->db_con->dbhFileLocker()->getQueryUpdater();

		$this->removeDuplicates();

		$locksChanged = false;
		foreach ( $FLCon->getLocks() as $lock ) {
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
			$FLCon->clearLocks();
		}
	}

	private function removeDuplicates() {
		$FLCon = $this->mod()->getFileLocker();
		$paths = [];
		foreach ( $FLCon->getLocks() as $lock ) {
			if ( \in_array( $lock->path, $paths ) ) {
				self::con()
					->db_con
					->dbhFileLocker()
					->getQueryDeleter()
					->deleteById( $lock->id );
			}
			else {
				$paths[] = $lock->path;
			}
		}
		if ( \count( $FLCon->getLocks() ) !== \count( $paths ) ) {
			$FLCon->clearLocks();
		}
	}
}