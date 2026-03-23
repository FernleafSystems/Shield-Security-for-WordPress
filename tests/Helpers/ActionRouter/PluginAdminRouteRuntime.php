<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Services\Services;

class PluginAdminRouteRuntime {

	/**
	 * @return array<string,mixed>
	 */
	public function processActionPayloadWithAdminBypass( string $actionSlug, array $params = [] ) :array {
		$con = RuntimeTestState::controller();
		$filter = $con->prefix( 'bypass_is_plugin_admin' );
		$isSecurityAdminSnapshot = $con->this_req->is_security_admin;
		$wpIsAjaxSnapshot = $con->this_req->wp_is_ajax;
		\add_filter( $filter, '__return_true', 1000 );
		$con->this_req->is_security_admin = true;
		$con->this_req->wp_is_ajax = false;

		try {
			return ( new ActionProcessor() )
				->processAction( $actionSlug, $params )
				->payload();
		}
		finally {
			$con->this_req->is_security_admin = $isSecurityAdminSnapshot;
			$con->this_req->wp_is_ajax = $wpIsAjaxSnapshot;
			\remove_filter( $filter, '__return_true', 1000 );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public function renderPluginAdminRoutePayload( string $nav, string $subNav, array $extra = [] ) :array {
		$servicesRequest = Services::Request();
		$thisRequest = RuntimeTestState::controller()->this_req->request;

		$snapshotServicesQuery = \is_array( $servicesRequest->query ) ? $servicesRequest->query : [];
		$snapshotThisQuery = \is_array( $thisRequest->query ) ? $thisRequest->query : [];

		$routeQuery = [
			Constants::NAV_ID     => $nav,
			Constants::NAV_SUB_ID => $subNav,
		];
		$servicesRequest->query = \array_merge( $snapshotServicesQuery, $routeQuery );
		$thisRequest->query = \array_merge( $snapshotThisQuery, $routeQuery );

		try {
			return $this->processActionPayloadWithAdminBypass(
				PageAdminPlugin::SLUG,
				\array_merge( $routeQuery, $extra )
			);
		}
		finally {
			$servicesRequest->query = $snapshotServicesQuery;
			$thisRequest->query = $snapshotThisQuery;
		}
	}
}
