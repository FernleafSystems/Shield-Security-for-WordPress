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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ActionProcessor {

	use PluginControllerConsumer;

	private static $actions;

	/**
	 * @throws ActionDoesNotExistException
	 * @throws ActionException
	 * @throws InvalidActionNonceException
	 * @throws IpBlockedException
	 * @throws SecurityAdminRequiredException
	 * @throws UserAuthRequiredException
	 */
	public function processAction( string $slug, array $data = [] ) :ActionResponse {
		$action = $this->getAction( $slug, $data );
		$action->process();
		return $action->response();
	}

	/**
	 * @throws ActionDoesNotExistException
	 */
	public function getAction( string $slug, array $data ) :Actions\BaseAction {
		$action = $this->findActionFromSlug( $slug );
		if ( empty( $action ) ) {
			throw new ActionDoesNotExistException( 'There was no action handler available for '.$slug );
		}
		return ( new $action( $data ) )->setMod( $this->con()->getModule_Plugin() );
	}

	public function findActionFromSlug( string $slug ) :string {
		$theAction = '';
		foreach ( Constants::ACTIONS as $action ) {
			if ( $action::SLUG === $slug ) {
				$theAction = $action;
				break;
			}
		}
		return $theAction;
	}
}