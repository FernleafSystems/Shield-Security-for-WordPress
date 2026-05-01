<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

class PasskeyCredentialDataNormalizer {

	private const TRUST_PATH_CLASS_ALIASES = [
		'EmptyTrustPath'       => 'empty',
		'CertificateTrustPath' => 'x5c',
		'EcdaaKeyIdTrustPath'  => 'ecdaa_key_id',
	];

	public function normalize( array $credentialData ) :array {
		if ( !\is_array( $credentialData[ 'trustPath' ] ?? null ) ) {
			return $credentialData;
		}

		$type = $credentialData[ 'trustPath' ][ 'type' ] ?? null;
		$alias = \is_string( $type ) ? $this->normaliseTrustPathClassType( $type ) : null;
		if ( $alias === null ) {
			return $credentialData;
		}

		$credentialData[ 'trustPath' ][ 'type' ] = $alias;
		return $credentialData;
	}

	private function normaliseTrustPathClassType( string $type ) :?string {
		$parts = \explode( '\\', \ltrim( $type, '\\' ) );
		if ( \count( $parts ) < 3 ) {
			return null;
		}

		$className = \array_pop( $parts );
		$pathNamespace = \array_pop( $parts );
		$webauthnNamespace = \array_pop( $parts );

		return $webauthnNamespace === 'Webauthn'
			   && $pathNamespace === 'TrustPath'
			   && isset( self::TRUST_PATH_CLASS_ALIASES[ $className ] )
			? self::TRUST_PATH_CLASS_ALIASES[ $className ]
			: null;
	}
}
