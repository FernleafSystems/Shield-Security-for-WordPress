<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\{
	HookAddFilter,
	SetRequestToBeLogged
};

class ResponseParamsNormalizer {

	public function normalize( ?string $responseClass, array $params ) :array {
		if ( !empty( $responseClass ) && \is_a( $responseClass, HookAddFilter::class, true ) ) {
			if ( \array_key_exists( 'args', $params ) && !\array_key_exists( 'accepted_args', $params ) ) {
				$params[ 'accepted_args' ] = $params[ 'args' ];
			}
			unset( $params[ 'args' ] );
		}

		if ( !empty( $responseClass ) && \is_a( $responseClass, SetRequestToBeLogged::class, true ) ) {
			if ( \array_key_exists( 'hook_priority', $params ) && !\array_key_exists( 'priority', $params ) ) {
				$params[ 'priority' ] = $params[ 'hook_priority' ];
			}
			unset( $params[ 'hook_priority' ] );
		}

		return $params;
	}
}
