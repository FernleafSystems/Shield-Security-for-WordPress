<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Exceptions\{
	FileContentsEncodingFailure,
	FileContentsEncryptionFailure,
	NoCipherAvailableException
};
use FernleafSystems\Wordpress\Services\Services;

class BuildEncryptedFilePayload {

	/**
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 * @throws NoCipherAvailableException
	 */
	public function fromPath( string $path, string $publicKey, string $cipher ) :string {
		// Ensure the contents are never empty,
		$contents = Services::WpFs()->getFileContent( $path );
		return $this->fromContent( empty( $contents ) ? ' ' : $contents, $publicKey, $cipher );
	}

	/**
	 * @throws FileContentsEncodingFailure
	 * @throws FileContentsEncryptionFailure
	 * @throws NoCipherAvailableException
	 */
	public function fromContent( string $contents, string $publicKey, string $cipher ) :string {
		$srvEnc = Services::Encrypt();

		if ( empty( $cipher ) ) {
			throw new NoCipherAvailableException();
		}

		$payload = $srvEnc->sealData( $contents, $publicKey, $cipher );
		if ( !$payload->success ) {
			throw new FileContentsEncryptionFailure( __( 'File contents could not be encrypted. Message: ', 'wp-simple-firewall' ).$payload->message );
		}

		$encoded = wp_json_encode( $payload->getRawData() );
		if ( empty( $encoded ) || !\is_string( $encoded ) ) {
			throw new FileContentsEncodingFailure( __( 'File contents could not be wp_json_encode() after encryption.', 'wp-simple-firewall' ) );
		}

		return $encoded;
	}
}
