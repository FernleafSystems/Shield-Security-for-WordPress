<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionTypeDoesNotExistException;

class ResponseAdapterFactory {

	/**
	 * The single place an adapter is chosen for a transport.
	 * @throws ActionTypeDoesNotExistException
	 */
	public function forActionType( int $type ) :ResponseAdapterInterface {
		switch ( $type ) {
			case ActionRoutingController::ACTION_AJAX:
				$adapter = new AjaxResponseAdapter();
				break;
			case ActionRoutingController::ACTION_SHIELD:
				$adapter = new ShieldActionResponseAdapter();
				break;
			case ActionRoutingController::ACTION_REST:
				$adapter = new RestApiActionResponseAdapter();
				break;
			default:
				throw new ActionTypeDoesNotExistException( sprintf( 'Action type does not exist: %s', $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Programming-error guard: every caller passes one of the router's fixed action-type constants, so it isn't reached, and only an int is added.
		}
		return $adapter;
	}
}
