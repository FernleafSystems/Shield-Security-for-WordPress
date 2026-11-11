<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\SecurityAdminRequiredException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\ResponseAdapterFactory;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @internal Coordinates action processing and transport adaptation.
 * @todo Transition consumers from ActionRoutingController::action() to this executor.
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
	 * @throws SecurityAdminRequiredException
	 */
	public function execute( string $classOrSlug, array $data = [], int $type = ActionRoutingController::ACTION_SHIELD ) :RoutedResponse {
		$actionResponse = null;
		$routedResponse = null;

		try {
			$actionResponse = ( new ActionProcessor() )->processAction( $classOrSlug, $data );
		}
		catch ( SecurityAdminRequiredException $sare ) {
			if ( Services::WpGeneral()->isAjax() ) {
				throw $sare;
			}
			$routedResponse = $this->execute( PageSecurityAdminRestricted::class, $data );
		}

		if ( !$routedResponse instanceof RoutedResponse ) {
			$routedResponse = $this->factory->forActionType( $type )->adapt( $actionResponse );
		}

		return $routedResponse;
	}
}
