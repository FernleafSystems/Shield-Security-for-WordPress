<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse;

class ShieldActionResponseAdapter extends BaseAdapter {

	public function adapt( ActionResponse $response ) :RoutedResponse {
		$payload = $response->payload();
		if ( !isset( $payload[ 'next_step' ] ) && !empty( $response->next_step ) ) {
			$payload[ 'next_step' ] = $response->next_step;
		}

		switch ( $response->action_data[ 'notification_type' ] ?? '' ) {
			case 'wp_admin_notice':
				if ( \is_string( $payload[ 'message' ] ?? null ) ) {
					self::con()->admin_notices->addFlash(
						$payload[ 'message' ],
						null,
						!( $payload[ 'success' ] ?? true )
					);
				}
				break;
			default:
				break;
		}

		$response->setPayload( $payload );
		return new RoutedResponse( $response, $payload );
	}
}
