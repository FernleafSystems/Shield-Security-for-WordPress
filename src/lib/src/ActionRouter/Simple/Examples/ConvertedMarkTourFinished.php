<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Examples;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Definition;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Dispatcher;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Registry;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager;

class ConvertedMarkTourFinished {

	private Registry $registry;

	private Dispatcher $dispatcher;

	public function __construct( Registry $registry, Dispatcher $dispatcher ) {
		$this->registry = $registry;
		$this->dispatcher = $dispatcher;
	}

	public function registerConverted() :void {
		$this->registry->register( new Definition(
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

	public function dispatchConverted( array $data ) :ActionResponse {
		return $this->dispatcher->dispatch(
			$this->registry->get( 'mark_tour_finished' ),
			$data
		);
	}
}
