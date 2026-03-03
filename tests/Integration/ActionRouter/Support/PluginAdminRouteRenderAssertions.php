<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\PageAdminPlugin,
	Constants
};
use FernleafSystems\Wordpress\Services\Services;

trait PluginAdminRouteRenderAssertions {

	private function processActionPayloadWithAdminBypass( string $actionSlug, array $params = [] ) :array {
		$con = self::con();
		$filter = $con->prefix( 'bypass_is_plugin_admin' );
		$isSecurityAdminSnapshot = $con->this_req->is_security_admin;
		add_filter( $filter, '__return_true', 1000 );
		$con->this_req->is_security_admin = true;

		try {
			return ( new ActionProcessor() )
						->processAction( $actionSlug, $params )
						->payload();
		}
		finally {
			$con->this_req->is_security_admin = $isSecurityAdminSnapshot;
			remove_filter( $filter, '__return_true', 1000 );
		}
	}

	private function renderPluginAdminRoutePayload( string $nav, string $subNav, array $extra = [] ) :array {
		$servicesRequest = Services::Request();
		$thisRequest = self::con()->this_req->request;

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
