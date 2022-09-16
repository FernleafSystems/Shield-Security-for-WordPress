<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionResponse;

class ShieldActionResponseAdapter extends BaseAdapter {

	public function adapt( ActionResponse $response ) {
		$con = $this->getCon();

		switch ( $response->action_data[ 'notification_type' ] ?? '' ) {
			case 'wp_admin_notice':
				$con->getAdminNotices()
					->addFlash(
						sprintf( '[%s] %s', $con->getHumanName(), $response->action_response_data[ 'msg' ] ),
						null,
						!$response->action_response_data[ 'success' ]
					);
				break;
			default:
				break;
		}
	}
}