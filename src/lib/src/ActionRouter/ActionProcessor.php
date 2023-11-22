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
	 * @var array
	 */
	private static $actions = [];

	/**
	 * @throws ActionDoesNotExistException
	 * @throws ActionException
	 * @throws InvalidActionNonceException
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
		if ( \class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ActionsMap' ) ) {
			$action = ActionsMap::ActionFromSlug( $classOrSlug );
		}
		else {
			$action = \class_exists( $classOrSlug ) ? $classOrSlug : ( self::$actions[ $classOrSlug ] ?? $this->findActionFromSlug( $classOrSlug ) );
		}
		if ( empty( $action ) ) {
			throw new ActionDoesNotExistException( 'There was no action handler available for '.$classOrSlug );
		}
		return new $action( $data );
	}

	/**
	 * @deprecated 18.5.6
	 */
	private function findActionFromSlug( string $slug ) :string {
		foreach ( Constants::ACTIONS as $action ) {
			if ( \class_exists( $action ) && $action::SLUG === $slug ) {
				return self::$actions[ $slug ] = $action;
			}
		}
		return '';
	}
}