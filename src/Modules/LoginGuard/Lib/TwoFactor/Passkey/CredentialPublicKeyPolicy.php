<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Passkey;

use CBOR\{
	Decoder,
	MapObject,
	OtherObject\OtherObjectManager,
	StringStream,
	Tag\TagObjectManager
};
use Cose\Key\{
	Key,
	RsaKey
};
use Webauthn\{
	PublicKeyCredentialSource,
	U2FPublicKey
};

/**
 * TEMPORARY HARDENING for web-auth/cose-lib 3.x. Shield stays on that line (through web-auth/webauthn-lib 3.3)
 * while it supports PHP 7.4, and the fixed releases need PHP 8.1+ (cose-lib 4.7.2) and PHP 8.2+ (webauthn-lib
 * 5.3.13). Remove this class, WebauthnLibAdapter::assertKeyAccepted() and its two call sites once Shield is on
 * those releases, and remove the matching audit exceptions from composer.json at the same time.
 *
 * It refuses passkey RSA keys that the library verifies signatures with but shouldn't:
 * - GHSA-rh56-4rc8-hj58: RSA signature verification accepts forged signatures under a public exponent of 1.
 * - GHSA-9v8c-2mgr-qvx3: an oversized RSA key makes every signature check cost seconds of CPU.
 * Both are only reachable through a key the attacker registered on their own account. EC2 and OKP keys
 * (ES256, Ed25519 and so on) aren't affected and pass unchecked.
 *
 * Genuine RSA passkeys are well within these bounds: authenticators create them with 2048-bit keys
 * (Windows Hello) and the exponent 65537.
 */
class CredentialPublicKeyPolicy {

	/**
	 * 4096 bits. Twice the size of the largest RSA passkey keys authenticators create.
	 */
	private const RSA_MAX_MODULUS_BYTES = 512;

	/**
	 * A key that can't be read is accepted here and left for the library to reject, so this check never refuses
	 * a passkey that the library itself would accept, other than an out-of-bounds RSA key.
	 */
	public function accepts( PublicKeyCredentialSource $source ) :bool {
		try {
			$accepted = !$this->isUnsafeRsaKey( $this->loadKey( $source ) );
		}
		catch ( \Throwable $e ) {
			$accepted = true;
		}
		return $accepted;
	}

	/**
	 * Unsafe when the modulus is empty or over the bound (GHSA-9v8c), or when the exponent isn't odd and at
	 * least 3 (GHSA-rh56) or is longer than the modulus (an unbounded exponent costs CPU as well).
	 */
	private function isUnsafeRsaKey( Key $key ) :bool {
		if ( !$key instanceof RsaKey ) {
			return false;
		}

		$n = \ltrim( $key->n(), "\0" );
		$e = \ltrim( $key->e(), "\0" );
		return $n === ''
			   || \strlen( $n ) > self::RSA_MAX_MODULUS_BYTES
			   || $e === ''
			   || \strlen( $e ) > \strlen( $n )
			   || ( \ord( \substr( $e, -1 ) ) & 1 ) === 0
			   || ( \strlen( $e ) === 1 && \ord( $e ) < 3 );
	}

	/**
	 * Reads the key the same way the library's assertion validator does, including legacy U2F keys.
	 * @throws \Exception
	 */
	private function loadKey( PublicKeyCredentialSource $source ) :Key {
		$data = $source->getCredentialPublicKey();
		if ( U2FPublicKey::isU2FKey( $data ) ) {
			$data = U2FPublicKey::createCOSEKey( $data );
		}

		$decoded = ( new Decoder( new TagObjectManager(), new OtherObjectManager() ) )->decode( new StringStream( $data ) );
		if ( !$decoded instanceof MapObject ) {
			throw new \InvalidArgumentException( 'Invalid public key.' );
		}
		return Key::createFromData( $decoded->getNormalizedData() );
	}
}
