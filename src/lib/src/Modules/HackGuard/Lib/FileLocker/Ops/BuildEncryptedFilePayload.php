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
	 * @param string $sPublicKey
	 * @return string
	 * @throws \ErrorException
	 */
	public function build( $sPath, $sPublicKey ) {
		$oEnc = Services::Encrypt();
		$oPayload = $oEnc->sealData( Services::WpFs()->getFileContent( $sPath ), $sPublicKey );
		if ( !$oPayload->success ) {
			throw new \ErrorException( 'File contents could not be encrypted' );
		}
		return json_encode( $oPayload->getRawDataAsArray() );
	}
}