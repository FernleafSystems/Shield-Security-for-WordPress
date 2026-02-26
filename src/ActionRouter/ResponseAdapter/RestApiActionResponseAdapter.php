<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse;

class RestApiActionResponseAdapter extends BaseAdapter {

	public function adapt( ActionResponse $response ) :RoutedResponse {
		$payload = $response->payload();
		if ( !\array_key_exists( 'success', $payload ) ) {
			$payload[ 'success' ] = (bool)$response->success;
		}
		$response->setPayload( $payload );

		return new RoutedResponse( $response, $payload );
	}
}
