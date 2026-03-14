<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route\PostureOverview;

class QuerySurfaceAccessPolicy {

	private ?PostureOverview $referenceRoute = null;

	public function isSiteExposureReady() :bool {
		return $this->getReferenceRoute()->isRouteAvailable();
	}

	/**
	 * @return true|\WP_Error
	 */
	public function verifyCurrentRequest( ?\WP_REST_Request $request = null ) {
		if ( !$this->isSiteExposureReady() ) {
			return new \WP_Error(
				'shield_query_surface_unavailable',
				__( 'Shield query access is unavailable for this site.', 'wp-simple-firewall' ),
				[ 'status' => \function_exists( '\rest_authorization_required_code' ) ? \rest_authorization_required_code() : 403 ]
			);
		}

		$permissionCallback = $this->getReferenceRoute()->buildRouteDefs()[ 'permission_callback' ] ?? null;
		if ( !\is_callable( $permissionCallback ) ) {
			return new \WP_Error(
				'shield_query_surface_unavailable',
				__( 'Shield query access is unavailable for this site.', 'wp-simple-firewall' ),
				[ 'status' => \function_exists( '\rest_authorization_required_code' ) ? \rest_authorization_required_code() : 403 ]
			);
		}

		$verified = $permissionCallback( $request instanceof \WP_REST_Request ? $request : $this->buildPermissionRequest() );
		return $verified === false
			? new \WP_Error(
				'shield_query_surface_permission_denied',
				__( 'Sorry, you are not allowed to access the Shield query surface.', 'wp-simple-firewall' ),
				[ 'status' => \function_exists( '\rest_authorization_required_code' ) ? \rest_authorization_required_code() : 403 ]
			)
			: $verified;
	}

	protected function buildPermissionRequest() :\WP_REST_Request {
		return new \WP_REST_Request(
			'GET',
			\sprintf( '/shield/v%s%s', $this->getReferenceRoute()->getVersion(), $this->getReferenceRoute()->buildRoutePath() )
		);
	}

	protected function getReferenceRoute() :PostureOverview {
		return $this->referenceRoute ??= new PostureOverview();
	}
}
