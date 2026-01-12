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

class ActionProcessor {

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
	 * SECURITY FIX: Strip action_overrides from user input
	 * Security controls should never be controllable by user input, even from "authenticated" sources.
	 * This prevents CSRF bypass attacks where attackers send action_overrides[is_nonce_verify_required]=false
	 * Integrations that legitimately need overrides (like MainWP) should set them programmatically
	 * AFTER action creation using setActionOverride() method.
	 * @throws ActionDoesNotExistException
	 */
	public function getAction( string $classOrSlug, array $data ) :Actions\BaseAction {
		$action = ActionsMap::ActionFromSlug( $classOrSlug );
		if ( empty( $action ) ) {
			throw new ActionDoesNotExistException( 'There was no action handler available for '.esc_html( $classOrSlug ) );
		}
		unset( $data[ 'action_overrides' ] );
		return new $action( $data );
	}
}