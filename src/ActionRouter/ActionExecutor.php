<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\ResponseAdapterFactory;

/**
 * Runs an action and adapts its response for the transport the caller names. Access failures propagate as
 * exceptions; the caller that owns the transport decides how to present them.
 */
class ActionExecutor {

	private ResponseAdapterFactory $factory;

	public function __construct( ?ResponseAdapterFactory $factory = null ) {
		$this->factory = $factory instanceof ResponseAdapterFactory ? $factory : new ResponseAdapterFactory();
	}

	/**
	 * @throws Exceptions\ActionDoesNotExistException
	 * @throws Exceptions\ActionException
	 * @throws Exceptions\ActionTypeDoesNotExistException
	 * @throws Exceptions\IpBlockedException
	 * @throws Exceptions\SecurityAdminRequiredException
	 * @throws Exceptions\UserAuthRequiredException
	 */
	public function execute( string $classOrSlug, array $data = [], int $type = ActionRoutingController::ACTION_SHIELD ) :RoutedResponse {
		return $this->factory
			->forActionType( $type )
			->adapt( ( new ActionProcessor() )->processAction( $classOrSlug, $data ) );
	}
}
