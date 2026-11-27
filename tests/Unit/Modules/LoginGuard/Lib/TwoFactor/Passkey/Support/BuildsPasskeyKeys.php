<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Passkey\Support;

use Base64Url\Base64Url;
use CBOR\{
	ByteStringObject,
	CBORObject,
	MapObject,
	NegativeIntegerObject,
	UnsignedIntegerObject
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\PasskeyFixtureLoader;
use Webauthn\PublicKeyCredentialSource;

/**
 * COSE public keys for passkey tests, and the fixture passkey carrying one of them.
 */
trait BuildsPasskeyKeys {

	/**
	 * @param array<int,int|string> $items COSE labels to integer or byte-string values
	 */
	private function coseKey( array $items ) :string {
		$map = MapObject::create();
		foreach ( $items as $label => $value ) {
			$map->add( $this->cborValue( $label ), $this->cborValue( $value ) );
		}
		return (string)$map;
	}

	private function rsaCoseKey( int $alg, string $n, string $e ) :string {
		return $this->coseKey( [
			1  => 3,
			3  => $alg,
			-1 => $n,
			-2 => $e,
		] );
	}

	/**
	 * An odd modulus with its top bit set, so it has exactly the given number of significant bytes.
	 */
	private function modulus( int $bytes ) :string {
		return "\xc1".\str_repeat( "\x5a", $bytes - 2 )."\x0b";
	}

	/**
	 * The fixture's stored passkey with its public key replaced.
	 */
	private function fixtureSourceWithKey( string $coseKey ) :PublicKeyCredentialSource {
		$record = PasskeyFixtureLoader::legacyRecord();
		$record[ 'credentialPublicKey' ] = Base64Url::encode( $coseKey );
		return PublicKeyCredentialSource::createFromArray( $record );
	}

	/**
	 * @param int|string $value
	 */
	private function cborValue( $value ) :CBORObject {
		if ( \is_string( $value ) ) {
			return ByteStringObject::create( $value );
		}
		return $value < 0 ? NegativeIntegerObject::create( $value ) : UnsignedIntegerObject::create( $value );
	}
}
