<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\FileLocker\GetPublicKey;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CreateFileLocks
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class CreateFileLocks extends BaseOps {

	/**
	 * @throws \Exception
	 */
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
	 * @throws \Exception
	 */
	private function processPath( $sPath ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		if ( Services::WpFs()->isFile( $sPath ) ) {
			$oEntry = new FileLocker\EntryVO();
			$oEntry->file = $sPath;
			$oEntry->hash_original = hash_file( 'sha1', $sPath );

			$aPublicKey = $this->getPublicKey();
			$oEntry->public_key_id = key( $aPublicKey );
			$oEntry->content = ( new BuildEncryptedFilePayload() )
				->setMod( $oMod )
				->build( $sPath, reset( $aPublicKey ) );

			/** @var FileLocker\Insert $oInserter */
			$oInserter = $oMod->getDbHandler_FileLocker()->getQueryInserter();
			$oInserter->insert( $oEntry );

			$this->clearFileLocksCache();
		}
	}
}