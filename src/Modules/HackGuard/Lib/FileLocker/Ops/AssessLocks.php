<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks {

	use PluginControllerConsumer;

	public function run() {
		$this->removeDuplicates();

		$locksChanged = false;
		foreach ( self::con()->comps->file_locker->getLocks() as $lock ) {
			try {
				if ( !Services::WpFs()->isFile( $lock->path ) ) {
					if ( $lock->hash_current !== '-' ) {
						$this->getUpdater()->updateCurrentHash( $lock, '-' );
						$this->getUpdater()->markProblem( $lock );
						$locksChanged = true;
					}
				}
				elseif ( ( new CompareHash() )->isEqualFileSha1( $lock->path, $lock->hash_original ) ) {
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

	private function getUpdater() :FileLockerDB\Update {
		return self::con()->db_con->file_locker->getQueryUpdater();
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