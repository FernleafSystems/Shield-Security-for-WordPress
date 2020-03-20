<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();
		/** @var FileLocker\Update $oUpd */
		$oUpd = $oDbH->getQueryUpdater();

		foreach ( $this->getFileLocks() as $oLock ) {
			try {
				if ( ( new CompareHash() )->isEqualFileSha1( $oLock->file, $oLock->hash_original ) ) {
					if ( !empty( $oLock->hash_current ) ) {
						$oUpd->updateCurrentHash( $oLock, '' );
					}
				}
				else {
					// Now we do a WP text diff. If the diff is empty, the only change is whitespace
					$sDiff = wp_text_diff(
						( new ReadOriginalFileContent() )
							->setMod( $this->getMod() )
							->run( $oLock ),
						Services::WpFs()->getFileContent( $oLock->file )
					);

					$sFileHash = hash_file( 'sha1', $oLock->file );
					if ( empty( $sDiff ) ) { // Only whitespace has changed
						$oUpd->updateOriginalHash( $oLock, $sFileHash );
					}
					else {
						$oUpd->updateCurrentHash( $oLock, $sFileHash );
					}
				}
			}
			catch ( \InvalidArgumentException $oE ) {
				$oUpd->markProblem( $oLock );
			}
		}
		$this->clearFileLocksCache();
	}
}