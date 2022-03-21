<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Services;

class BuildEncryptedFilePayload extends BaseOps {

	/**
	 * @param string $path
	 * @param string $publicKey
	 * @return string
	 * @throws \ErrorException
	 */
	public function build( $path, $publicKey ) {
		$srvEnc = Services::Encrypt();
		$payload = $srvEnc->sealData( Services::WpFs()->getFileContent( $path ), $publicKey );
		if ( !$payload->success ) {
			throw new \ErrorException( 'File contents could not be encrypted' );
		}
		return json_encode( $payload->getRawData() );
	}
}