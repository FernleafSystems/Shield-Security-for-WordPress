<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Request\{
	RequestTypeResolver,
	ThisRequest
};

/**
 * Gathers the facts about the current request that its sensitivity is judged on.
 */
class BuildRequestFacts {

	public function build( ThisRequest $req ) :RequestFacts {
		$request = $req->request;
		$action = $request->request( ActionData::FIELD_ACTION );

		return new RequestFacts( [
			'type'           => ( new RequestTypeResolver() )->resolve(),
			'method'         => $request->getMethod(),
			'path'           => (string)$req->path,
			'restRoute'      => $req->getRestRoute(),
			'action'         => \is_string( $action ) ? $action : '',
			'queryParams'    => \array_map( 'strval', \array_keys( $request->query ) ),
			'postParams'     => \array_map( 'strval', \array_keys( $request->post ) ),
			'hasCredentials' => $this->hasCredentials( $req ),
			'isAdmin'        => (bool)$req->wp_is_admin,
			'isShieldAction' => $action === ActionData::FIELD_SHIELD
								&& ActionData::isValidActionSlug( (string)$request->request( ActionData::FIELD_EXECUTE ) ),
			'wooPagePaths'   => $this->wooPagePaths(),
		] );
	}

	/**
	 * Only whether credentials are present: a request carrying them is treated more strictly, never less.
	 */
	private function hasCredentials( ThisRequest $req ) :bool {
		$request = $req->request;
		$hasLoginCookie = \defined( 'LOGGED_IN_COOKIE' ) && !empty( $request->cookie( \LOGGED_IN_COOKIE ) );
		$hasAuthHeader = !empty( $request->server( 'HTTP_AUTHORIZATION' ) )
						 || !empty( $request->server( 'REDIRECT_HTTP_AUTHORIZATION' ) )
						 || !empty( $request->server( 'PHP_AUTH_USER' ) );
		return $hasLoginCookie || $hasAuthHeader;
	}

	/**
	 * @return string[]
	 */
	private function wooPagePaths() :array {
		$paths = [];
		if ( \function_exists( 'wc_get_page_id' ) && \function_exists( 'wc_get_page_permalink' ) ) {
			foreach ( [ 'cart', 'checkout', 'myaccount' ] as $page ) {
				if ( wc_get_page_id( $page ) > 0 ) {
					$path = \wp_parse_url( (string)wc_get_page_permalink( $page ), \PHP_URL_PATH );
					if ( \is_string( $path ) && $path !== '' ) {
						$paths[] = $path;
					}
				}
			}
		}
		return $paths;
	}
}
