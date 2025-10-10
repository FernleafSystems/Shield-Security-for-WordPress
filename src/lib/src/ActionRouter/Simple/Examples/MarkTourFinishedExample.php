<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Examples;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginMarkTourFinished;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Dispatcher;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Definition;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple\Registry;

class MarkTourFinishedExample {

	private Registry $registry;

	private Dispatcher $dispatcher;

	public function __construct( Registry $registry, Dispatcher $dispatcher ) {
		$this->registry = $registry;
		$this->dispatcher = $dispatcher;
	}

	public function register() :void {
		$this->registry->register( PluginMarkTourFinished::simpleDefinition() );
	}

	public function dispatch( array $data ) :ActionResponse {
		return $this->dispatcher->dispatch(
			$this->registry->get( PluginMarkTourFinished::SLUG ),
			$data
		);
	}

	public function registerClosureDemo() :void {
		$this->registry->register( new Definition(
			'mark_tour_finished_closure_demo',
			function ( array $actionData, ActionResponse $response ) {
				( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\TourManager() )->setCompleted( $actionData[ 'tour_key' ] ?? '' );
				$response->action_response_data = [
					'success' => true,
					'message' => __( 'Tour Finished (closure demo)', 'wp-simple-firewall' ),
				];
				return $response;
			},
			[
				'policies' => [
					Definition::POLICY_REQUIRE_NONCE => true,
				],
			]
		) );
	}

	public function dispatchClosureDemo( array $data ) :ActionResponse {
		return $this->dispatcher->dispatch(
			$this->registry->get( 'mark_tour_finished_closure_demo' ),
			$data
		);
	}
}
