<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessAndRepairFileLocks extends BaseOps {

	public function run() {
		foreach ( $this->getFileLocks() as $oFileLock ) {
			try {
				$bFileValid = ( new CompareHash() )->isEqualFileSha1( $oFileLock->file, $oFileLock->hash );
			}
			catch ( \InvalidArgumentException $oE ) {
				$bFileValid = false;
			}
			if ( !$bFileValid ) {
				( new Revert() )
					->setMod( $this->getMod() )
					->run( $oFileLock );

				$this->clearFileLocksCache();
			}
		}
	}
}