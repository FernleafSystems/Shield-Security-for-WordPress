<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;

/**
 * The sensitivity of the current request. Plugins and site owners can classify their own routes and
 * AJAX actions through the `shield/request_sensitivity` filter, which receives the level Shield chose
 * and the request facts, and must return one of RequestSensitivity::LEVELS.
 */
class ResolveRequestSensitivity {

	public function resolve( ThisRequest $req ) :RequestSensitivity {
		$facts = ( new BuildRequestFacts() )->build( $req );
		$sensitivity = ( new ClassifyRequest() )->classify( $facts );

		$filtered = apply_filters( 'shield/request_sensitivity', $sensitivity->level, $facts );
		if ( \is_string( $filtered ) && $filtered !== $sensitivity->level && \in_array( $filtered, RequestSensitivity::LEVELS, true ) ) {
			$sensitivity = new RequestSensitivity( $filtered, 'filtered' );
		}

		return $sensitivity;
	}
}
