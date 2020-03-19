<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CreateLock
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class CreateFileLock extends BaseOps {

	/**
	 */
	public function create() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();
		$oFS = Services::WpFs();
		foreach ( $this->oFile->getPossiblePaths() as $sPossPath ) {
			if ( $oFS->isFile( $sPossPath ) ) {
				$oEntry = new FileLocker\EntryVO();
				$oEntry->file = $sPossPath;
				$oEntry->hash = hash_file( 'sha1', $sPossPath );
				try {
					$oEntry->content = $this->buildEncryptedFilePayload( $sPossPath );
					$oEntry->encrypted = 1;
				}
				catch ( \Exception $oE ) {
					$oEntry->content = base64_encode( $oFS->getFileContent( $sPossPath ) );
					$oEntry->encrypted = 0;
				}
				/** @var FileLocker\Insert $oInserter */
				$oInserter = $oDbH->getQueryInserter();
				$oInserter->insert( $oEntry );
				break;
			}
		}
	}

	/**
	 * @param string $sPath
	 * @return string
	 * @throws \Exception
	 */
	private function buildEncryptedFilePayload( $sPath ) {
		$oEnc = Services::Encrypt();
		$mKey = $this->getCon()->getModule_Plugin()->getOpenSslPublicKey();
		if ( empty( $mKey ) ) {
			throw new \LogicException( 'Cannot encrypt without a key' );
		}
		$oPayload = $oEnc->sealData( Services::WpFs()->getFileContent( $sPath ), $mKey );
		if ( !$oPayload->success ) {
			throw new \ErrorException( 'File contents could not be encrypted' );
		}
		return json_encode( $oPayload->getRawDataAsArray() );
	}
}