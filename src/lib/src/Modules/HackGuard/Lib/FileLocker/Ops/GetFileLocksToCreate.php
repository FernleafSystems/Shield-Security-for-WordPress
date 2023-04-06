<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

class GetFileLocksToCreate extends BaseOps {

	public function run() :array {
		$locksToCreate = [];
		foreach ( $this->opts()->getFilesToLock() as $fileType ) {
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

		return $locksToCreate;
	}
}