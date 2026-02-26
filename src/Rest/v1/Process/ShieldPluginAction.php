<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions;

class ShieldPluginAction extends Base {

	protected function process() :array {
		$req = $this->getWpRestRequest();
		$params = $req->get_params();

		try {
			$routed = self::con()
				->action_router
				->action( $params[ 'ex' ], $params[ 'payload' ], ActionRoutingController::ACTION_REST );
			$data = $routed->payload();
		}
//		catch ( ActionDoesNotExistException $e ) {
//		}
//		catch ( ActionTypeDoesNotExistException $e ) {
//		}
//		catch ( InvalidActionNonceException $e ) {
//		}
//		catch ( SecurityAdminRequiredException $e ) {
//		}
		catch ( Exceptions\ActionException $e ) {
//			error_log( $e->getMessage() );
			$data = [
				'success' => false,
			];
		}

		/** See AJAX normalised data */
		return [
			'success' => (bool)( $data[ 'success' ] ?? false ),
			'data'    => \array_merge( [
				'page_reload' => false,
				'message'     => '',
				'html'        => '',
			], $data )
		];
	}
}
