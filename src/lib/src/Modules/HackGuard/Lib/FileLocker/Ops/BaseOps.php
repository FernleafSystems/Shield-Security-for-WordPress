<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\GetPublicKey;

class BaseOps {

	use ModConsumer;

	/**
	 * @var Databases\FileLocker\EntryVO[]
	 */
	private static $aFileLockRecords;

	/**
	 * @var FileLocker\File
	 */
	protected $file;

	/**
	 * @return Databases\FileLocker\EntryVO|null
	 */
	protected function findLockRecordForFile() {
		$theLock = null;
		foreach ( $this->file->getPossiblePaths() as $sPath ) {
			foreach ( $this->getFileLocks() as $lock ) {
				if ( $lock->file === $sPath ) {
					$theLock = $lock;
					break;
				}
			}
		}
		return $theLock;
	}

	/**
	 * @return Databases\FileLocker\EntryVO[]
	 */
	protected function getFileLocks() :array {
		return ( new LoadFileLocks() )
			->setMod( $this->getMod() )
			->loadLocks();
	}

	/**
	 * @return array
	 * @throws \ErrorException
	 */
	protected function getPublicKey() :array {
		$key = ( new GetPublicKey() )
			->setMod( $this->getMod() )
			->retrieve();
		if ( empty( $key ) || !is_array( $key ) ) {
			throw new \ErrorException( 'Cannot encrypt without a public key' );
		}
		return $key;
	}

	/**
	 * @return $this
	 */
	protected function clearFileLocksCache() {
		( new LoadFileLocks() )
			->setMod( $this->getMod() )
			->clearLocksCache();
		return $this;
	}

	/**
	 * @param FileLocker\File $file
	 * @return $this
	 */
	public function setWorkingFile( FileLocker\File $file ) {
		$this->file = $file;
		return $this;
	}
}