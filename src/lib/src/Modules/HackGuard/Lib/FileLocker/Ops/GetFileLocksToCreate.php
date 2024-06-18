<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

class GetFileLocksToCreate extends BaseOps {

	public function run() :array {
		$locksToCreate = [];
		// Required for upgrades from 19.0
		if ( isset( self::con()->comps->file_locker ) ) {
			foreach ( self::con()->comps->file_locker->getFilesToLock() as $fileType ) {
				try {
					$file = ( new BuildFileFromFileKey() )->build( $fileType );
					$lock = $this->setWorkingFile( $file )->findLockRecordForFile();
					if ( empty( $lock ) ) {
						$locksToCreate[] = $fileType;
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
		return $locksToCreate;
	}
}