<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	LockDbInsertFailure,
	NoFileLockPathsExistException,
	PublicKeyRetrievalFailure,
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;

class CreateFileLocks extends BaseOps {

	/**
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 * @throws LockDbInsertFailure
	 * @throws NoFileLockPathsExistException
	 * @throws PublicKeyRetrievalFailure
	 */
	public function create() {
		$existingPaths = $this->file->getExistingPossiblePaths();
		if ( empty( $existingPaths ) ) {
			throw new NoFileLockPathsExistException();
		}

		foreach ( $existingPaths as $path ) {
			if ( empty( $this->findLockRecordForFile() ) ) {
				$this->processPath( $path );
			}
		}
	}

	/**
	 * @throws LockDbInsertFailure
	 * @throws PublicKeyRetrievalFailure
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 */
	private function processPath( string $path ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$entry = new FileLocker\EntryVO();
		$entry->file = $path;
		$entry->hash_original = hash_file( 'sha1', $path );

		$publicKey = $this->getPublicKey();
		$entry->public_key_id = key( $publicKey );
		$entry->content = ( new BuildEncryptedFilePayload() )
			->setMod( $mod )
			->build( $path, reset( $publicKey ) );

		/** @var FileLocker\Insert $inserter */
		$inserter = $mod->getDbHandler_FileLocker()->getQueryInserter();
		if ( !$inserter->insert( $entry ) ) {
			throw new LockDbInsertFailure( sprintf( 'Failed to insert file locker record for path: "%s"', $path ) );
		}

		$this->clearFileLocksCache();
	}
}