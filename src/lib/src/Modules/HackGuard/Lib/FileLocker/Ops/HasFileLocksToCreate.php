<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

class HasFileLocksToCreate extends BaseOps {

	public function run() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$hasLockToCreate = false;
		foreach ( $opts->getFilesToLock() as $fileKey ) {
			try {
				$file = ( new BuildFileFromFileKey() )->build( $fileKey );
				$lock = $this->setWorkingFile( $file )->findLockRecordForFile();
				if ( empty( $lock ) ) {
					$hasLockToCreate = true;
					break;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return $hasLockToCreate;
	}
}