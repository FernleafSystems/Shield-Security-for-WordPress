<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\{
	ActionDoesNotExistException,
	ActionException,
	InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ActionProcessor {

	use ModConsumer;

	private static $actions;

	/**
	 * @throws ActionDoesNotExistException
	 * @throws ActionException
	 * @throws InvalidActionNonceException
	 */
	public function processAction( string $slug, array $data = [] ) :ActionResponse {
		$action = $this->getAction( $slug, $data );
		if ( $action->isUserAuthRequired() && !Services::WpUsers()->isUserLoggedIn() ) {
			throw new ActionException( sprintf( 'Must be logged-in to execute this action: %s', $slug ) );
		}
		elseif ( $action->isSecurityAdminRestricted() && !$this->getCon()->isPluginAdmin() ) {
			$action = $this->getAction( Actions\Render\PluginAdminPages\PageSecurityAdminRestricted::SLUG, $data );
		}
		elseif ( $action->isNonceVerifyRequired() && !$this->verifyNonce() ) {
			throw new InvalidActionNonceException();
		}

		return $action->process()->response();
	}

	public function verifyNonce() :bool {
		$req = Services::Request();
		return wp_verify_nonce(
				   $req->request( ActionData::FIELD_NONCE ),
				   ActionData::FIELD_SHIELD.'-'.$req->request( ActionData::FIELD_EXECUTE )
			   ) === 1;
	}

	/**
	 * @throws ActionDoesNotExistException
	 */
	public function getAction( string $slug, array $data ) :Actions\BaseAction {
		$action = $this->findActionFromSlug( $slug );
		if ( empty( $action ) ) {
			throw new ActionDoesNotExistException( 'There was no action handler available for '.$slug );
		}
		return ( new $action( $data ) )->setMod( $this->getMod() );
	}

	public function findActionFromSlug( string $slug ) :string {
		$theAction = '';
		foreach ( Constants::ACTIONS as $action ) {
			if ( preg_match( $action::Pattern(), $slug ) ) {
				$theAction = $action;
				break;
			}
		}
		return $theAction;
	}
}