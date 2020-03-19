<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

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
		if ( is_null( self::$aFileLockRecords ) ) {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			/** @var Databases\FileLocker\Handler $oDbH */
			$oDbH = $oMod->getDbHandler_FileLocker();
			self::$aFileLockRecords = $oDbH->getQuerySelector()->all();
		}
		return self::$aFileLockRecords;
	}

	/**
	 * @return $this
	 */
	protected function clearFileLocksCache() {
		self::$aFileLockRecords = null;
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