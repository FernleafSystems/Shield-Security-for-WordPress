<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
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
	 * @return Databases\FileLocker\EntryVO[]
	 */
	protected function getFileLocks() :array {
		return ( new LoadFileLocks() )
			->setMod( $this->getMod() )
			->loadLocks();
	}

	/**
	 * @throws \Exception
	 */
	protected function getPublicKey() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$getter = ( new GetPublicKey() )->setMod( $this->getMod() );
		$getter->last_error = $mod->getFileLocker()->getState()[ 'last_error' ] ?? '';
		$key = $getter->retrieve();
		if ( empty( $key ) || !is_array( $key ) ) {
			throw new \Exception( 'Failed to obtain public key from API.' );
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