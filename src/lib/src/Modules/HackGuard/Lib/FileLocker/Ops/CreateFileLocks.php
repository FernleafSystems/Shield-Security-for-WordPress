<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CreateFileLocks
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class CreateFileLocks extends BaseOps {

	public function create() {

		foreach ( $this->oFile->getExistingPossiblePaths() as $sPath ) {
			$oTheFileLock = null;
			foreach ( $this->getFileLocks() as $oMaybeFileLock ) {
				if ( $oMaybeFileLock->file === $sPath ) {
					$oTheFileLock = $oMaybeFileLock;
					break;
				}
			}
			if ( !$oTheFileLock instanceof FileLocker\EntryVO ) {
				$this->processPath( $sPath );
			}
		}
	}

	/**
	 * @param string $sPath
	 */
	private function processPath( $sPath ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();
		$oFS = Services::WpFs();

		if ( $oFS->isFile( $sPath ) ) {
			$oEntry = new FileLocker\EntryVO();
			$oEntry->file = $sPath;
			$oEntry->hash_original = hash_file( 'sha1', $sPath );
			try {
				$oEntry->content = ( new BuildEncryptedFilePayload() )
					->setMod( $oMod )
					->build( $sPath );
				$oEntry->encrypted = 1;
			}
			catch ( \Exception $oE ) {
				$oEntry->content = $oFS->getFileContent( $sPath );
				$oEntry->encrypted = 0;
			}
			/** @var FileLocker\Insert $oInserter */
			$oInserter = $oDbH->getQueryInserter();
			$oInserter->insert( $oEntry );

			$this->clearFileLocksCache();
		}
	}
}