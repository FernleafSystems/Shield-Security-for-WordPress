<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	ActionDoesNotExistException,
	ActionException,
	InvalidActionNonceException,
	IpBlockedException,
	SecurityAdminRequiredException,
	UserAuthRequiredException,
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ActionsMap;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActionProcessor {

	use PluginControllerConsumer;

	/**
	 * @throws ActionDoesNotExistException
	 * @throws ActionException
	 * @throws InvalidActionNonceException
	 * @throws IpBlockedException
	 * @throws SecurityAdminRequiredException
	 * @throws UserAuthRequiredException
	 */
	public function processAction( string $classOrSlug, array $data = [] ) :ActionResponse {
		$registry = self::con()->action_router->simpleRegistry();
		if ( $registry->has( $classOrSlug ) ) {
			return self::con()->action_router->simpleDispatcher()->dispatch(
				$registry->get( $classOrSlug ),
				$data
			);
		}

		$action = $this->getAction( $classOrSlug, $data );
		$action->process();
		return $action->response();
	}

	/**
	 * @throws ActionDoesNotExistException
	 */
	public function getAction( string $classOrSlug, array $data ) :Actions\BaseAction {
		$action = ActionsMap::ActionFromSlug( $classOrSlug );
		if ( empty( $action ) ) {
			throw new ActionDoesNotExistException( 'There was no action handler available for '.$classOrSlug );
		}
		return new $action( $data );
	}
}