<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

class AltChaV2Pbkdf2 {

	public const VERSION = '2';
	public const ALGORITHM = 'PBKDF2/SHA-256';

	private const HMAC_ALGORITHM = 'sha256';
	private const KEY_LENGTH = 32;
	private const KEY_PREFIX_LENGTH = 16;
	private const NONCE_BYTES = 16;
	private const SALT_BYTES = 16;

	public function requirementsMet() :bool {
		return \function_exists( 'hash_pbkdf2' )
			   && \function_exists( 'hash_hmac' )
			   && \function_exists( 'hash_equals' )
			   && \function_exists( 'random_bytes' )
			   && \function_exists( 'random_int' );
	}

	public function keySignatureSecret( string $hmacSignatureSecret ) :string {
		return \hash_hmac( self::HMAC_ALGORITHM, 'altcha-v2-key-signature', $hmacSignatureSecret );
	}

	/**
	 * @return array{parameters:array<string,mixed>,signature:string}
	 * @throws \Exception
	 */
	public function buildChallenge(
		string $hmacSignatureSecret,
		string $hmacKeySignatureSecret,
		int $cost,
		int $counter,
		int $expiresAt,
		?string $nonce = null,
		?string $salt = null
	) :array {
		$parameters = [
			'algorithm' => self::ALGORITHM,
			'cost'      => $cost,
			'expiresAt' => $expiresAt,
			'keyLength' => self::KEY_LENGTH,
			'nonce'     => $nonce ?? \bin2hex( \random_bytes( self::NONCE_BYTES ) ),
			'salt'      => $salt ?? \bin2hex( \random_bytes( self::SALT_BYTES ) ),
		];

		$derivedKey = $this->deriveKeyBinary( $parameters, $counter );
		$parameters[ 'keyPrefix' ] = \bin2hex( \substr( $derivedKey, 0, self::KEY_PREFIX_LENGTH ) );
		$parameters[ 'keySignature' ] = \hash_hmac( self::HMAC_ALGORITHM, $derivedKey, $hmacKeySignatureSecret );
		$parameters = $this->sortKeysRecursive( $parameters );

		return [
			'parameters' => $parameters,
			'signature'  => \hash_hmac( self::HMAC_ALGORITHM, $this->canonicalJson( $parameters ), $hmacSignatureSecret ),
		];
	}

	/**
	 * @param array{parameters:array<string,mixed>,signature:string} $challenge
	 * @throws \JsonException
	 */
	public function encodeChallenge( array $challenge ) :string {
		return $this->jsonEncode( $challenge );
	}

	/**
	 * @return array<string,mixed>
	 * @throws \Exception
	 */
	public function decodeChallenge( string $challengeJson ) :array {
		$challenge = \json_decode( $challengeJson, true );
		if ( !\is_array( $challenge ) ) {
			throw new \Exception( 'ALTCHA challenge is not valid JSON.' );
		}
		$this->assertChallengeShape( $challenge );
		return $challenge;
	}

	/**
	 * @return array{counter:int,derivedKey:string}
	 * @throws \Exception
	 */
	public function decodeSolution( string $solutionJson ) :array {
		$solution = \json_decode( $solutionJson, true );
		if ( !\is_array( $solution )
			 || !\is_int( $solution[ 'counter' ] ?? null )
			 || !\is_string( $solution[ 'derivedKey' ] ?? null ) ) {
			throw new \Exception( 'ALTCHA solution is not valid.' );
		}
		if ( $solution[ 'counter' ] < 0 || $solution[ 'counter' ] > 0xFFFFFFFF ) {
			throw new \Exception( 'ALTCHA solution counter is outside the supported range.' );
		}
		$this->hexToBinary( $solution[ 'derivedKey' ], 'derivedKey' );
		return [
			'counter'    => $solution[ 'counter' ],
			'derivedKey' => \strtolower( $solution[ 'derivedKey' ] ),
		];
	}

	/**
	 * @throws \Exception
	 */
	public function verifySolution(
		string $challengeJson,
		string $solutionJson,
		string $hmacSignatureSecret,
		string $hmacKeySignatureSecret,
		int $now
	) :bool {
		$challenge = $this->decodeChallenge( $challengeJson );
		$solution = $this->decodeSolution( $solutionJson );
		$parameters = $challenge[ 'parameters' ];

		if ( (int)$parameters[ 'expiresAt' ] <= $now ) {
			throw new \Exception( 'ALTCHA challenge has expired.' );
		}

		$expectedSignature = \hash_hmac(
			self::HMAC_ALGORITHM,
			$this->canonicalJson( $parameters ),
			$hmacSignatureSecret
		);
		if ( !\hash_equals( $expectedSignature, (string)$challenge[ 'signature' ] ) ) {
			throw new \Exception( 'ALTCHA challenge signature failed.' );
		}

		if ( \strpos( $solution[ 'derivedKey' ], \strtolower( (string)$parameters[ 'keyPrefix' ] ) ) !== 0 ) {
			throw new \Exception( 'ALTCHA solution prefix failed.' );
		}

		$derivedKey = $this->hexToBinary( $solution[ 'derivedKey' ], 'derivedKey' );
		$expectedKeySignature = \hash_hmac( self::HMAC_ALGORITHM, $derivedKey, $hmacKeySignatureSecret );
		if ( !\hash_equals( $expectedKeySignature, (string)$parameters[ 'keySignature' ] ) ) {
			throw new \Exception( 'ALTCHA key signature failed.' );
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $parameters
	 * @throws \Exception
	 */
	public function deriveKeyHex( array $parameters, int $counter ) :string {
		return \bin2hex( $this->deriveKeyBinary( $parameters, $counter ) );
	}

	/**
	 * @param mixed $value
	 * @throws \JsonException
	 */
	public function canonicalJson( $value ) :string {
		return $this->jsonEncode( $this->sortKeysRecursive( $value ) );
	}

	/**
	 * @param array<string,mixed> $parameters
	 * @throws \Exception
	 */
	private function deriveKeyBinary( array $parameters, int $counter ) :string {
		if ( $counter < 0 || $counter > 0xFFFFFFFF ) {
			throw new \Exception( 'ALTCHA counter is outside the supported range.' );
		}

		$algorithm = (string)( $parameters[ 'algorithm' ] ?? '' );
		if ( $algorithm !== self::ALGORITHM ) {
			throw new \Exception( 'ALTCHA algorithm is not supported.' );
		}

		$cost = (int)( $parameters[ 'cost' ] ?? 0 );
		$keyLength = (int)( $parameters[ 'keyLength' ] ?? 0 );
		if ( $cost < 1 || $keyLength !== self::KEY_LENGTH ) {
			throw new \Exception( 'ALTCHA KDF parameters are not supported.' );
		}

		$password = $this->hexToBinary( (string)( $parameters[ 'nonce' ] ?? '' ), 'nonce' ).\pack( 'N', $counter );
		$salt = $this->hexToBinary( (string)( $parameters[ 'salt' ] ?? '' ), 'salt' );

		$derived = \hash_pbkdf2( self::HMAC_ALGORITHM, $password, $salt, $cost, $keyLength, true );
		if ( !\is_string( $derived ) || \strlen( $derived ) !== self::KEY_LENGTH ) {
			throw new \Exception( 'ALTCHA KDF failed.' );
		}
		return $derived;
	}

	/**
	 * @param array<string,mixed> $challenge
	 * @throws \Exception
	 */
	private function assertChallengeShape( array $challenge ) :void {
		if ( !\is_array( $challenge[ 'parameters' ] ?? null ) || !\is_string( $challenge[ 'signature' ] ?? null ) ) {
			throw new \Exception( 'ALTCHA challenge is not valid.' );
		}

		$parameters = $challenge[ 'parameters' ];
		foreach ( [ 'algorithm', 'keyPrefix', 'keySignature', 'nonce', 'salt' ] as $key ) {
			if ( !\is_string( $parameters[ $key ] ?? null ) || \trim( $parameters[ $key ] ) === '' ) {
				throw new \Exception( 'ALTCHA challenge is missing '.$key.'.' );
			}
		}
		foreach ( [ 'cost', 'expiresAt', 'keyLength' ] as $key ) {
			if ( !\is_int( $parameters[ $key ] ?? null ) ) {
				throw new \Exception( 'ALTCHA challenge has invalid '.$key.'.' );
			}
		}
		if ( (string)$parameters[ 'algorithm' ] !== self::ALGORITHM ) {
			throw new \Exception( 'ALTCHA challenge algorithm is not supported.' );
		}
		$this->hexToBinary( $parameters[ 'nonce' ], 'nonce' );
		$this->hexToBinary( $parameters[ 'salt' ], 'salt' );
		$this->hexToBinary( $parameters[ 'keyPrefix' ], 'keyPrefix' );
		$this->hexToBinary( $parameters[ 'keySignature' ], 'keySignature' );
		$this->hexToBinary( $challenge[ 'signature' ], 'signature' );
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function sortKeysRecursive( $value ) {
		if ( !\is_array( $value ) ) {
			return $value;
		}

		if ( \array_keys( $value ) === \range( 0, \count( $value ) - 1 ) ) {
			return \array_map( [ $this, 'sortKeysRecursive' ], $value );
		}

		\ksort( $value, \SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->sortKeysRecursive( $item );
		}
		return $value;
	}

	/**
	 * @param mixed $value
	 * @throws \JsonException
	 */
	private function jsonEncode( $value ) :string {
		return \json_encode( $value, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR );
	}

	/**
	 * @throws \Exception
	 */
	private function hexToBinary( string $hex, string $field ) :string {
		if ( $hex === '' || \strlen( $hex ) % 2 !== 0 || !\preg_match( '/\A[0-9a-f]+\z/i', $hex ) ) {
			throw new \Exception( 'ALTCHA '.$field.' is not valid hex.' );
		}

		$binary = \hex2bin( $hex );
		if ( !\is_string( $binary ) ) {
			throw new \Exception( 'ALTCHA '.$field.' is not valid hex.' );
		}
		return $binary;
	}
}
