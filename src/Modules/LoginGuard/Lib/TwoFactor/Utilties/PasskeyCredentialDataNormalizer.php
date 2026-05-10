<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

class PasskeyCredentialDataNormalizer {

	private const TRUST_PATH_TYPE_EMPTY = 'empty';
	private const TRUST_PATH_TYPE_CERTIFICATE = 'x5c';
	private const TRUST_PATH_TYPE_ECDAA = 'ecdaa_key_id';
	private const TRUST_PATH_ALIAS_TYPES = [
		self::TRUST_PATH_TYPE_EMPTY,
		self::TRUST_PATH_TYPE_CERTIFICATE,
		self::TRUST_PATH_TYPE_ECDAA,
	];

	public function normalize( array $credentialData ) :array {
		if ( !\is_array( $credentialData[ 'trustPath' ] ?? null ) ) {
			return $credentialData;
		}

		$alias = $this->normaliseTrustPathType( $credentialData[ 'trustPath' ] );
		if ( $alias === null ) {
			return $credentialData;
		}

		$credentialData[ 'trustPath' ][ 'type' ] = $alias;
		return $credentialData;
	}

	private function normaliseTrustPathType( array $trustPath ) :?string {
		if ( \array_key_exists( 'x5c', $trustPath ) ) {
			return self::TRUST_PATH_TYPE_CERTIFICATE;
		}
		if ( \array_key_exists( 'ecdaaKeyId', $trustPath ) ) {
			return self::TRUST_PATH_TYPE_ECDAA;
		}

		$type = $trustPath[ 'type' ] ?? null;
		if ( !\is_string( $type ) ) {
			return null;
		}

		if ( \in_array( $type, self::TRUST_PATH_ALIAS_TYPES, true ) ) {
			return $type;
		}

		return $this->isClassLikeType( $type ) ? self::TRUST_PATH_TYPE_EMPTY : null;
	}

	private function isClassLikeType( string $type ) :bool {
		$parts = \explode( '\\', \ltrim( $type, '\\' ) );
		if ( \count( $parts ) < 2 ) {
			return false;
		}

		foreach ( $parts as $part ) {
			if ( \preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $part ) !== 1 ) {
				return false;
			}
		}

		return true;
	}
}
