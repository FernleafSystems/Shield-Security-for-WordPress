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
	protected $oFile;

	/**
	 * @return Databases\FileLocker\EntryVO|null
	 */
	protected function findLockRecordForFile() {
		$oTheRecord = null;
		foreach ( $this->oFile->getPossiblePaths() as $sPath ) {
			foreach ( $this->getFileLocks() as $oRecord ) {
				if ( $oRecord->file === $sPath ) {
					$oTheRecord = $oRecord;
					break;
				}
			}
		}
		return $oTheRecord;
	}

	/**
	 * @return Databases\FileLocker\EntryVO[]|null
	 */
	protected function getFileLocks() {
		return ( new LoadFileLocks() )
			->setMod( $this->getMod() )
			->loadLocks();
	}

	/**
	 * @return array
	 * @throws \ErrorException
	 */
	protected function getPublicKey() {
		$aPublicKey = ( new GetPublicKey() )
			->setMod( $this->getMod() )
			->retrieve();
		if ( empty( $aPublicKey ) ) {
			throw new \ErrorException( 'Cannot encrypt without a public key' );
		}
		return $aPublicKey;
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
	 * @param FileLocker\File $oFile
	 * @return $this
	 */
	public function setWorkingFile( FileLocker\File $oFile ) {
		$this->oFile = $oFile;
		return $this;
	}
}