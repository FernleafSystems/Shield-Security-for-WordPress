<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\FileLocker\Ops as FileLockerDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\PublicKeyRetrievalFailure;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;

class BaseOps {

	use ModConsumer;

	/**
	 * @var FileLocker\File
	 */
	protected $file;

	protected function findLockRecordForFile() :?FileLockerDB\Record {
		$theLock = null;
		foreach ( $this->file->getPossiblePaths() as $path ) {
			foreach ( $this->getFileLocks() as $maybeLock ) {
				if ( $maybeLock->file === $path ) {
					$theLock = $maybeLock;
					break;
				}
			}
		}
		return $theLock;
	}

	/**
	 * @return FileLockerDB\Record[]
	 */
	protected function getFileLocks() :array {
		return ( new LoadFileLocks() )
			->setMod( $this->getMod() )
			->loadLocks();
	}

	/**
	 * @throws PublicKeyRetrievalFailure
	 */
	protected function getPublicKey() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$getter = ( new GetPublicKey() )->setMod( $this->getMod() );
		$getter->last_error = $mod->getFileLocker()->getState()[ 'last_error' ] ?? '';

		$key = $getter->retrieve();
		if ( empty( $key ) || !is_array( $key ) ) {
			throw new PublicKeyRetrievalFailure( 'Failed to obtain public key from API.' );
		}

		$thePublicKey = reset( $key );
		if ( empty( $thePublicKey ) || !is_string( $thePublicKey ) ) {
			throw new PublicKeyRetrievalFailure( 'Public key was empty' );
		}

		return $key;
	}

	protected function clearFileLocksCache() {
		( new LoadFileLocks() )
			->setMod( $this->getMod() )
			->clearLocksCache();
	}

	/**
	 * @return $this
	 */
	public function setWorkingFile( FileLocker\File $file ) {
		$this->file = $file;
		return $this;
	}
}