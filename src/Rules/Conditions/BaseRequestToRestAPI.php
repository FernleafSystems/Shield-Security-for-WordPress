<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

abstract class BaseRequestToRestAPI extends Base {

	use Traits\TypeWordpress;

	public static function MinimumHook() :int {
		return WPHooksOrder::REST_API_INIT;
	}

	protected function getRestNamespace() :string {
		return explode( '/', $this->getRestRoute() )[ 0 ];
	}

	/**
	 * @see \WP_REST_Request::from_url()
	 */
	protected function getRestRoute() :string {
		$req = $this->req;
		$currentURL = sprintf( 'http%s://%s/%s', is_ssl() ? 's' : '', $req->request->server[ 'HTTP_HOST' ] ?? '', \trim( $req->path, '/' ) );

		if ( $req->wp_is_permalinks_enabled && \str_starts_with( $currentURL, $req->rest_api_root ) ) {
			$route = \str_replace( $req->rest_api_root, '', $currentURL );
		}
		elseif ( isset( $this->req->request->query[ 'rest_route' ] ) ) {
			$route = $this->req->request->query[ 'rest_route' ];
		}
		else {
			$route = '';
		}
		return \trim( $route, '/' );
	}
}