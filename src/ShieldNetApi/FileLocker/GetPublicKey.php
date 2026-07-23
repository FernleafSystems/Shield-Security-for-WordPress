<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

class GetPublicKey extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {
	public const API_ACTION = 'filelocker/public_key';

	public function retrieve(): ?array {
		$raw = $this->sendReq();
		if ( !\is_array( $raw )
			 || !\array_key_exists( 'key_id', $raw )
			 || !\array_key_exists( 'pub_key', $raw )
		) {
			return null;
		}

		$keyID = $this->normaliseKeyId( $raw[ 'key_id' ] );
		$publicKey = $raw[ 'pub_key' ];
		return $keyID !== null && \is_string( $publicKey ) && \trim( $publicKey ) !== '' ?
			[ $keyID => $publicKey ] : null;
	}

	private function normaliseKeyId( $value ) :?int {
		if ( \is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}
		if ( !\is_string( $value ) || \preg_match( '#^\d+$#D', $value ) !== 1 ) {
			return null;
		}
		$digits = \ltrim( $value, '0' );
		if ( $digits === '' ) {
			return null;
		}
		$max = (string)\PHP_INT_MAX;
		if ( \strlen( $digits ) > \strlen( $max )
			 || ( \strlen( $digits ) === \strlen( $max ) && \strcmp( $digits, $max ) > 0 ) ) {
			return null;
		}
		return (int)$digits;
	}
}
