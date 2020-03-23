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
	 * @throws \ErrorException
	 */
	public function run( $oLock ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Handler $oDbH */
		$oDbH = $oMod->getDbHandler_FileLocker();

		$aPublicKey = $this->getPublicKey();
		$sRawContent = ( new BuildEncryptedFilePayload() )
			->setMod( $oMod )
			->build( $oLock->file, reset( $aPublicKey ) );

		/** @var FileLocker\Update $oUpdater */
		$oUpdater = $oDbH->getQueryUpdater();
		$oUpdater->updateEntry( $oLock, [
			'hash_original' => hash_file( 'sha1', $oLock->file ),
			'content'       => base64_encode( $sRawContent ),
			'public_key_id' => key( $aPublicKey ),
			'detected_at'   => 0,
			'updated_at'    => Services::Request()->ts(),
		] );

		$this->clearFileLocksCache();
	}
}