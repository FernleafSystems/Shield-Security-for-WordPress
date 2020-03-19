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
	private static $AllFileRecords;

	/**
	 * @var FileLocker\File
	 */
	protected $oFile;

	public function __construct( FileLocker\File $oFile ) {
		$this->oFile = $oFile;
	}

	/**
	 * @return Databases\FileLocker\EntryVO|null
	 */
	protected function findLockRecordForFile() {
		$oTheRecord = null;
		foreach ( $this->oFile->getPossiblePaths() as $sPath ) {
			foreach ( $this->getFileRecords() as $oRecord ) {
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
	protected function getFileRecords() {
		if ( is_null( self::$AllFileRecords ) ) {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			/** @var Databases\FileLocker\Handler $oDbH */
			$oDbH = $oMod->getDbHandler_FileLocker();
			self::$AllFileRecords = $oDbH->getQuerySelector()->all();
		}
		return self::$AllFileRecords;
	}

}