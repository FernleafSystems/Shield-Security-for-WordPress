<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
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
	 * @param string $path
	 * @throws \Exception
	 */
	private function processPath( $path ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( Services::WpFs()->isFile( $path ) ) {
			$oEntry = new FileLocker\EntryVO();
			$oEntry->file = $path;
			$oEntry->hash_original = hash_file( 'sha1', $path );

			$aPublicKey = $this->getPublicKey();
			$oEntry->public_key_id = key( $aPublicKey );
			$oEntry->content = ( new BuildEncryptedFilePayload() )
				->setMod( $mod )
				->build( $path, reset( $aPublicKey ) );

			/** @var FileLocker\Insert $oInserter */
			$oInserter = $mod->getDbHandler_FileLocker()->getQueryInserter();
			$oInserter->insert( $oEntry );

			$this->clearFileLocksCache();
		}
	}
}