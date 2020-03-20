<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

class AssessLocks extends BaseOps {

	/**
	 * @return int[]
	 */
	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();
		/** @var FileLocker\Update $oUpd */
		$oUpd = $oDbH->getQueryUpdater();

		$aProblemIds = [];
		foreach ( $this->getFileLocks() as $oLock ) {
			try {
				if ( ( new CompareHash() )->isEqualFileSha1( $oLock->file, $oLock->hash_original ) ) {
					if ( !empty( $oLock->hash_current ) ) {
						$oUpd->updateCurrentHash( $oLock, '' );
					}
				}
				else {
					$sDiff = wp_text_diff(
						( new ReadOriginalFileContent() )
							->setMod( $this->getMod() )
							->run( $oLock ),
						Services::WpFs()->getFileContent( $oLock->file )
					);

					$sFileHash = hash_file( 'sha1', $oLock->file );
					if ( empty( $sDiff ) ) { // Only whitespace has changed so we accept it
						( new Accept() )
							->setMod( $this->getMod() )
							->run( $oLock );
					}
					elseif( !hash_equals( $oLock->hash_current, $sFileHash ) ) {
						$oUpd->updateCurrentHash( $oLock, $sFileHash );
						$aProblemIds[] = $oLock->id;
					}
				}
			}
			catch ( \InvalidArgumentException $oE ) {
				$oUpd->markProblem( $oLock );
				$aProblemIds[] = $oLock->id;
			}
		}
		$this->clearFileLocksCache();
		return $aProblemIds;
	}
}