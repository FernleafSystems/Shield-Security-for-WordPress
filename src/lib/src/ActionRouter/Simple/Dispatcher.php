<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Simple;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionNonce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\InvalidActionNonceException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\IpBlockedException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\SecurityAdminRequiredException;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\UserAuthRequiredException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Dispatcher {

	use PluginControllerConsumer;

	/**
	 * @throws ActionException
	 * @throws InvalidActionNonceException
	 * @throws IpBlockedException
	 * @throws SecurityAdminRequiredException
	 * @throws UserAuthRequiredException
	 */
	public function dispatch( Definition $definition, array $data = [], ?ActionResponse $response = null ) :ActionResponse {
		$actionData = \array_merge( $definition->defaults(), $data );
		$this->assertRequiredData( $definition, $actionData );
		$this->checkAccessPolicies( $definition, $actionData );

		$response = $response instanceof ActionResponse ? $response : new ActionResponse();
		$maybeResponse = \call_user_func( $definition->handler(), $actionData, $response );
		if ( $maybeResponse instanceof ActionResponse ) {
			$response = $maybeResponse;
		}
		elseif ( \is_array( $maybeResponse ) ) {
			$response->action_response_data = $maybeResponse;
		}

		$response->action_slug = $definition->slug();
		$response->action_data = $actionData;
		return $response;
	}

	/**
	 * @throws ActionException
	 */
	private function assertRequiredData( Definition $definition, array $actionData ) :void {
		$missing = \array_diff( \array_unique( $definition->requiredDataKeys() ), \array_keys( $actionData ) );
		if ( !empty( $missing ) ) {
			throw new ActionException( \sprintf( 'Missing action (%s) data for the following keys: %s',
				$definition->slug(), \implode( ', ', $missing )
			) );
		}
	}

	/**
	 * @throws InvalidActionNonceException
	 * @throws IpBlockedException
	 * @throws SecurityAdminRequiredException
	 * @throws UserAuthRequiredException
	 */
	private function checkAccessPolicies( Definition $definition, array $actionData ) :void {
		$policies = $definition->policies();
		$con = self::con();
		$thisReq = $con->this_req;

		$bypassIpBlock = (bool)( $policies[ Definition::POLICY_BYPASS_IP_BLOCK ] ?? false );
		if ( !$thisReq->request_bypasses_all_restrictions && $thisReq->is_ip_blocked && !$bypassIpBlock ) {
			throw new IpBlockedException( \sprintf( __( 'IP Address blocked so cannot process action: %s', 'wp-simple-firewall' ), $definition->slug() ) );
		}

		$minCapability = isset( $policies[ Definition::POLICY_MIN_CAPABILITY ] )
			? (string)$policies[ Definition::POLICY_MIN_CAPABILITY ]
			: ( $con->cfg->properties[ 'base_permissions' ] ?? 'manage_options' );

		$WPU = Services::WpUsers();
		if ( !empty( $minCapability ) && ( !$WPU->isUserLoggedIn()
			|| !user_can( $WPU->getCurrentWpUser(), $minCapability ) ) ) {
			throw new UserAuthRequiredException( \sprintf( __( 'Must be logged-in to execute this action: %s', 'wp-simple-firewall' ), $definition->slug() ) );
		}

		$requiresSecurityAdmin = (bool)( $policies[ Definition::POLICY_REQUIRE_SECURITY_ADMIN ] ?? ( $minCapability === 'manage_options' ) );
		if ( $requiresSecurityAdmin && !$thisReq->is_security_admin ) {
			throw new SecurityAdminRequiredException( \sprintf( __( 'Security admin required for action: %s', 'wp-simple-firewall' ), $definition->slug() ) );
		}

		$requireNonce = (bool)( $policies[ Definition::POLICY_REQUIRE_NONCE ] ?? $con->this_req->wp_is_ajax );
		if ( $requireNonce && !ActionNonce::VerifyFromRequest() ) {
			throw new InvalidActionNonceException( __( 'Invalid Action Nonce Exception.', 'wp-simple-firewall' ) );
		}
	}
}
