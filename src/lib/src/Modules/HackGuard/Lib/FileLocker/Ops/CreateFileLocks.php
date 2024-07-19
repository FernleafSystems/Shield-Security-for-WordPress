<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure,
	LockDbInsertFailure,
	NoCipherAvailableException,
	NoFileLockPathsExistException,
	PublicKeyRetrievalFailure
};

class CreateFileLocks extends BaseOps {

	/**
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 * @throws LockDbInsertFailure
	 * @throws PublicKeyRetrievalFailure
	 * @throws NoCipherAvailableException
	 * @throws NoFileLockPathsExistException
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
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 * @throws LockDbInsertFailure
	 * @throws PublicKeyRetrievalFailure
	 * @throws NoCipherAvailableException
	 */
	private function createLockForPath( string $path ) {
		$dbh = self::con()->db_con->file_locker;
		/** @var FileLockerDB\Record $record */
		$record = $dbh->getRecord();
		$record->type = $this->file->type;
		$record->path = $path;
		$record->hash_original = \hash_file( 'sha1', $path );

		$record->cipher = self::con()->comps->file_locker->getState()[ 'cipher' ] ?? '';
		if ( empty( $record->cipher ) ) {
			throw new NoCipherAvailableException();
		}

		$publicKey = $this->getPublicKey();
		$record->public_key_id = \key( $publicKey );
		$record->content = ( new BuildEncryptedFilePayload() )->fromPath( $path, \reset( $publicKey ), $record->cipher );

		/** @var FileLockerDB\Insert $inserter */
		$inserter = $dbh->getQueryInserter();
		if ( !$inserter->insert( $record ) ) {
			throw new LockDbInsertFailure( sprintf( 'Failed to insert file locker record for path: "%s"', $path ) );
		}

		self::con()->comps->file_locker->clearLocks();
	}
}