<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure
};
use FernleafSystems\Wordpress\Services\Services;

class BuildEncryptedFilePayload extends BaseOps {

	/**
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 */
	public function build( string $path, string $publicKey ) :string {
		$srvEnc = Services::Encrypt();

		// Ensure the contents are never empty,
		$contents = Services::WpFs()->getFileContent( $path );
		if ( empty( $contents ) ) {
			$contents = ' ';
		}

		$payload = $srvEnc->sealData( $contents, $publicKey );
		if ( !$payload->success ) {
			throw new FileContentsEncryptionFailure( 'File contents could not be encrypted with message: '.$payload->message );
		}

		$encoded = wp_json_encode( $payload->getRawData() );
		if ( empty( $encoded ) || !is_string( $encoded ) ) {
			throw new FileContentsEncodingFailure( 'File contents could not be wp_json_encode() after encryption.' );
		}

		return $encoded;
	}
}