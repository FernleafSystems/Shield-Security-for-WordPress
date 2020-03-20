<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildEncryptedFilePayload
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class BuildEncryptedFilePayload extends BaseOps {

	/**
	 * @param string $sPath
	 * @return string
	 * @throws \Exception
	 */
	public function build( $sPath ) {
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