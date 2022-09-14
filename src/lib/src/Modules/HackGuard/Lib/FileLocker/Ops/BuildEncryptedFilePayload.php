<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Services\Services;

class BuildEncryptedFilePayload extends BaseOps {

	/**
	 * @param string $path
	 * @param string $publicKey
	 * @throws \Exception
	 */
	public function build( $path, $publicKey ) :string {
		$srvEnc = Services::Encrypt();
		$payload = $srvEnc->sealData( Services::WpFs()->getFileContent( $path ), $publicKey );
		if ( !$payload->success ) {
			throw new \Exception( 'File contents could not be encrypted with message: '.$payload->message );
		}
		$encoded = wp_json_encode( $payload->getRawData() );
		if ( empty( $encoded ) || !is_string( $encoded ) ) {
			throw new \Exception( 'File contents could not be wp_json_encode after encryption.' );
		}
		return $encoded;
	}
}