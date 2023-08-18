<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure,
	LockDbInsertFailure,
	NoFileLockPathsExistException,
	PublicKeyRetrievalFailure
};

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
				$this->createLockForPath( $path );
			}
		}
	}

	/**
	 * @throws LockDbInsertFailure
	 * @throws PublicKeyRetrievalFailure
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 */
	private function createLockForPath( string $path ) {
		/** @var FileLockerDB\Record $record */
		$record = $this->mod()->getDbH_FileLocker()->getRecord();
		$record->type = $this->file->type;
		$record->path = $path;
		$record->hash_original = \hash_file( 'sha1', $path );

		$publicKey = $this->getPublicKey();
		$record->public_key_id = \key( $publicKey );
		$record->cipher = $this->mod()->getFileLocker()->getState()[ 'cipher' ];
		$record->content = ( new BuildEncryptedFilePayload() )->build( $path, \reset( $publicKey ), $record->cipher );

		/** @var FileLockerDB\Insert $inserter */
		$inserter = $this->mod()->getDbH_FileLocker()->getQueryInserter();
		if ( !$inserter->insert( $record ) ) {
			throw new LockDbInsertFailure( sprintf( 'Failed to insert file locker record for path: "%s"', $path ) );
		}

		$this->mod()->getFileLocker()->clearLocks();
	}
}