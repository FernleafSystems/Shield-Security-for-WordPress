<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions;

class ShieldPluginAction extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process {

	protected function process() :array {
		$req = $this->getWpRestRequest();
		$params = $req->get_params();

		try {
			$response = self::con()
				->action_router
				->action( $params[ 'ex' ], $params[ 'payload' ], ActionRoutingController::ACTION_REST );
			$data = $response->action_response_data;
			if ( !isset( $data[ 'success' ] ) ) {
				$data[ 'success' ] = $response->success;
			}
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
			'success' => $data[ 'success' ],
			'data'    => \array_merge( [
				'page_reload' => false,
				'message'     => '',
				'html'        => '',
			], $data )
		];
	}
}