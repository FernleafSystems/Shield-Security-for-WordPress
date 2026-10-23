<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * The keys delivered rule files are trusted under: the same list the orchestrator, which signs them, and the API,
 * which serves them, hold. Epochs are never reused; revoking a key removes its entry without renumbering the rest.
 */
final class TrustedKeys {

	public const PRODUCTION = [
		[ 'keyId' => 'cef2d85853c9c417', 'epoch' => 1, 'publicKey' => 'L9xnSubenu4mon8h7IOxh/bTZVz40Qay93vXDTUqNT8=' ],
	];

	/**
	 * @var array<string, array{epoch: int, key: string}> by key ID, with the raw public key
	 */
	private array $keys;

	/**
	 * @param array[] $list each { keyId, epoch, publicKey } with the public key in base64
	 */
	public function __construct( array $list = self::PRODUCTION ) {
		$keys = [];
		$epochs = [];
		foreach ( $list as $item ) {
			$raw = \is_string( $item[ 'publicKey' ] ?? null ) ? Base64::decode( $item[ 'publicKey' ] ) : null;
			$epoch = $item[ 'epoch' ] ?? null;
			$keyId = $item[ 'keyId' ] ?? null;
			if ( $raw === null || \strlen( $raw ) !== 32 || !\is_int( $epoch ) || $epoch < 1 || !\is_string( $keyId )
				 || self::keyId( $raw ) !== $keyId || isset( $keys[ $keyId ] ) || isset( $epochs[ $epoch ] ) ) {
				throw new \InvalidArgumentException( 'Invalid trusted key.' );
			}
			$keys[ $keyId ] = [ 'epoch' => $epoch, 'key' => $raw ];
			$epochs[ $epoch ] = true;
		}
		$this->keys = $keys;
	}

	/**
	 * A key ID is the first 8 bytes of the SHA-256 of the raw public key, in hex.
	 */
	public static function keyId( string $rawPublicKey ) :string {
		return \substr( \hash( 'sha256', $rawPublicKey ), 0, 16 );
	}

	public function isTrusted( string $keyId ) :bool {
		return isset( $this->keys[ $keyId ] );
	}

	/**
	 * @return array{epoch: int, key: string}|null
	 */
	public function find( string $keyId ) :?array {
		return $this->keys[ $keyId ] ?? null;
	}
}
