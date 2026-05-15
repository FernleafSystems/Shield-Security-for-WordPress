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

	protected function getRestRoute() :string {
		if ( \method_exists( $this->req, 'getRestRoute' ) ) {
			return $this->req->getRestRoute();
		}

		$req = $this->req;
		$currentURL = sprintf( 'http%s://%s/%s', is_ssl() ? 's' : '', $req->request->server[ 'HTTP_HOST' ] ?? '', \trim( $req->path, '/' ) );

		if ( $req->wp_is_permalinks_enabled && \strpos( $currentURL, $req->rest_api_root ) === 0 ) {
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
