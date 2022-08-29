<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class GetFileLocksToCreate extends BaseOps {

	public function run() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$locksToCreate = [];
		foreach ( $opts->getFilesToLock() as $fileKey ) {
			try {
				$file = ( new BuildFileFromFileKey() )->build( $fileKey );
				$lock = $this->setWorkingFile( $file )->findLockRecordForFile();
				if ( empty( $lock ) ) {
					$locksToCreate[] = $fileKey;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return $locksToCreate;
	}
}