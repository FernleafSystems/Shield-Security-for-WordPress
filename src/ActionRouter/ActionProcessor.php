<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	ActionDoesNotExistException,
	ActionException,
	IpBlockedException,
	SecurityAdminRequiredException,
	UserAuthRequiredException,
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ActionsMap;

class ActionProcessor {

	/**
	 * @throws ActionDoesNotExistException
	 * @throws ActionException
	 * @throws IpBlockedException
	 * @throws SecurityAdminRequiredException
	 * @throws UserAuthRequiredException
	 */
	public function processAction( string $classOrSlug, array $data = [] ) :ActionResponse {
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
			throw new ActionDoesNotExistException( 'There was no action handler available for '.esc_html( $classOrSlug ) );
		}
		return new $action( $data );
	}
}