<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

class PairingTokenIssuer {

	public const TTL_SECONDS = 900;

	/**
	 * @return array{pairing_token:string,ttl_seconds:int,expires_at:string}
	 * @throws \Exception
	 */
	public function issue( int $ttlSeconds = self::TTL_SECONDS ) :array {
		$token = \bin2hex( \random_bytes( 32 ) );
		( new PairingTokenStore() )->storeToken( $token, $ttlSeconds );

		return [
			'pairing_token' => $token,
			'ttl_seconds'   => $ttlSeconds,
			'expires_at'    => \gmdate( 'c', \time() + $ttlSeconds ),
		];
	}
}
