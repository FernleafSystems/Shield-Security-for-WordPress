<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Accept
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class Accept extends BaseOps {

	/**
	 * @param FileLocker\EntryVO $oLock
	 */
	public function run( $oLock ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();

		$oEntry = new FileLocker\EntryVO();
		$oEntry->file = $oLock->file;
		$oEntry->hash_original = hash_file( 'sha1', $oLock->file );
		$oEntry->created_at = $oLock->created_at;
		$oEntry->updated_at = Services::Request()->ts();
		try {
			$oEntry->content = ( new BuildEncryptedFilePayload() )
				->setMod( $oMod )
				->build( $oLock->file );
			$oEntry->encrypted = 1;
		}
		catch ( \Exception $oE ) {
			$oEntry->content = Services::WpFs()->getFileContent( $oLock->file );
			$oEntry->encrypted = 0;
		}

		$oDbH->getQueryDeleter()->deleteEntry( $oLock );
		/** @var FileLocker\Insert $oInserter */
		$oInserter = $oDbH->getQueryInserter();
		$oInserter->insert( $oEntry );

		$this->clearFileLocksCache();
	}
}