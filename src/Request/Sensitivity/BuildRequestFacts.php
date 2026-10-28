<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\{
	RequestTypeResolver,
	ThisRequest
};

/**
 * Gathers the facts about the current request that its sensitivity is judged on. Route facts are included
 * once WordPress has routed the request. Nothing here queries the database: WooCommerce page IDs and
 * Shield's options come from autoloaded options, and post types and taxonomies are registered in memory.
 */
class BuildRequestFacts {

	use PluginControllerConsumer;

	public function build( ThisRequest $req ) :RequestFacts {
		$request = $req->request;
		$action = $request->request( ActionData::FIELD_ACTION );
		$routeVars = $this->routeVars();

		return new RequestFacts( [
			'type'             => ( new RequestTypeResolver() )->resolve(),
			'method'           => $request->getMethod(),
			'path'             => (string)$req->path,
			'restRoute'        => $req->getRestRoute() ?: (string)( $routeVars[ 'rest_route' ] ?? '' ),
			'action'           => \is_string( $action ) ? $action : '',
			'query'            => \is_array( $request->query ) ? $request->query : [],
			'postParams'       => \array_map( 'strval', \array_keys( \is_array( $request->post ) ? $request->post : [] ) ),
			'hasCredentials'   => $this->hasCredentials( $req ),
			'isAdmin'          => (bool)$req->wp_is_admin,
			'isShieldAction'   => $action === ActionData::FIELD_SHIELD
								  && ActionData::isValidActionSlug( (string)$request->request( ActionData::FIELD_EXECUTE ) ),
			'isServerLoopback' => (bool)$req->is_server_loopback,
			'loginPaths'       => $this->loginPaths(),
			'routeVars'        => $routeVars,
			'contentVars'      => $routeVars === null ? [] : $this->contentVars(),
			'queriedObjectId'  => \did_action( 'wp' ) ? (int)\get_queried_object_id() : 0,
			'wooPageIds'       => $this->wooPageIds(),
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
	 * @return ?array<string, mixed>
	 */
	private function routeVars() :?array {
		global $wp;
		return \did_action( 'parse_request' ) && $wp instanceof \WP && \is_array( $wp->query_vars ) ? $wp->query_vars : null;
	}

	/**
	 * @return string[]
	 */
	private function contentVars() :array {
		$vars = [];
		foreach ( \array_merge( \get_post_types( [ 'public' => true ], 'objects' ), \get_taxonomies( [ 'public' => true ], 'objects' ) ) as $type ) {
			if ( \is_string( $type->query_var ?? null ) && $type->query_var !== '' ) {
				$vars[] = $type->query_var;
			}
		}
		return $vars;
	}

	/**
	 * @return string[]
	 */
	private function loginPaths() :array {
		$renamed = \trim( (string)self::con()->opts->optGet( 'rename_wplogin_path' ), '/' );
		$path = $renamed === '' ? null : \wp_parse_url( \home_url( '/'.$renamed ), \PHP_URL_PATH );
		return \is_string( $path ) && $path !== '' ? [ $path ] : [];
	}

	/**
	 * @return int[]
	 */
	private function wooPageIds() :array {
		$ids = [];
		if ( \function_exists( 'wc_get_page_id' ) ) {
			foreach ( [ 'cart', 'checkout', 'myaccount' ] as $page ) {
				$id = (int)wc_get_page_id( $page );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}
		return $ids;
	}
}
