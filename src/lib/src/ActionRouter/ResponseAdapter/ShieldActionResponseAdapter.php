<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;

class ShieldActionResponseAdapter extends BaseAdapter {

	public function adapt( ActionResponse $response ) {
		$con = self::con();

		switch ( $response->action_data[ 'notification_type' ] ?? '' ) {
			case 'wp_admin_notice':
				if ( \is_string( $response->action_response_data[ 'message' ] ?? null ) ) {
					$con->getAdminNotices()
						->addFlash(
							$response->action_response_data[ 'message' ],
							null,
							!( $response->action_response_data[ 'success' ] ?? true )
						);
				}
				break;
			default:
				break;
		}
	}
}