<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionTypeDoesNotExistException;

class ResponseAdapterFactory {

	/**
	 * @throws ActionTypeDoesNotExistException
	 * @todo Once routing returns only RoutedResponse consumers, drop legacy adapter instantiation paths.
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
				throw new ActionTypeDoesNotExistException( $type );
		}
		return $adapter;
	}
}
