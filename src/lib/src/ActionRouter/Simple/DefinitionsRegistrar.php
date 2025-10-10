<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;

class DefinitionsRegistrar {

	public function registerDefaults( Registry $registry ) :void {
		if ( !$registry->has( 'mark_tour_finished' ) ) {
			$registry->register( new Definition(
				'mark_tour_finished',
				function ( array $actionData, ActionResponse $response ) {
					( new TourManager() )->setCompleted( $actionData[ 'tour_key' ] ?? '' );
					$response->action_response_data = [
						'success' => true,
						'message' => __( 'Tour Finished', 'wp-simple-firewall' ),
					];
					return $response;
				},
				[
					'required_data' => [ 'tour_key' ],
					'policies'      => [
						Definition::POLICY_REQUIRE_NONCE => true,
						Definition::POLICY_MIN_CAPABILITY => 'read',
						Definition::POLICY_REQUIRE_SECURITY_ADMIN => false,
					],
				]
			) );
		}
	}
}
