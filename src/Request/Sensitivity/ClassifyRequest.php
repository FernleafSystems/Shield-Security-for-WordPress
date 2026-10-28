<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as RequestTypes;

/**
 * Judges how much harm a request could do from what it touches, not from its HTTP method. Only a request
 * recognised as viewing content is Open; anything else is at least Guarded.
 *
 * Before WordPress routes a request, a pretty URL can't be told apart from a plugin endpoint, so an Open
 * result without route facts is provisional and must be judged again once the request is routed.
 */
class ClassifyRequest {

	private const VALUE_ID = '#^\d{1,20}$#';
	private const VALUE_PAGE = '#^/?\d{0,20}$#';
	private const VALUE_CATEGORY = '#^-?\d{1,20}(,-?\d{1,20})*$#';
	private const VALUE_SLUG = '#^[\p{L}\p{N}_\-+,./%]{1,200}$#u';
	private const VALUE_VERSION = '#^[A-Za-z0-9._\-]{1,40}$#';
	private const VALUE_TRACKING = '#^[\p{L}\p{N}_\-+.~|: ]{0,256}$#u';

	/**
	 * Parameters and query vars that only choose which content to show, with the values that fit that role.
	 */
	private const CONTENT_VALUES = [
		'p'             => self::VALUE_ID,
		'page_id'       => self::VALUE_ID,
		'attachment_id' => self::VALUE_ID,
		'paged'         => self::VALUE_ID,
		'cpage'         => self::VALUE_ID,
		'year'          => self::VALUE_ID,
		'monthnum'      => self::VALUE_ID,
		'day'           => self::VALUE_ID,
		'm'             => self::VALUE_ID,
		'replytocom'    => self::VALUE_ID,
		'error'         => self::VALUE_ID,
		'page'          => self::VALUE_PAGE,
		'cat'           => self::VALUE_CATEGORY,
		'name'          => self::VALUE_SLUG,
		'pagename'      => self::VALUE_SLUG,
		'category_name' => self::VALUE_SLUG,
		'tag'           => self::VALUE_SLUG,
		'author_name'   => self::VALUE_SLUG,
		'attachment'    => self::VALUE_SLUG,
		'post_type'     => self::VALUE_SLUG,
		'feed'          => self::VALUE_SLUG,
	];

	/**
	 * Query parameters that only track a visit or bust a cache. WordPress doesn't route on them.
	 */
	private const PASSIVE_VALUES = [
		'ver'     => self::VALUE_VERSION,
		'gclid'   => self::VALUE_TRACKING,
		'fbclid'  => self::VALUE_TRACKING,
		'msclkid' => self::VALUE_TRACKING,
	];

	private const ACCOUNT_FORM_ACTIONS = [
		'register',
		'lostpassword',
		'retrievepassword',
		'rp',
		'resetpass',
	];

	private const OEMBED_ROUTE = 'oembed/1.0/embed';

	public function classify( RequestFacts $facts ) :RequestSensitivity {
		$isRead = \in_array( $facts->method, [ 'GET', 'HEAD' ], true );

		switch ( true ) {
			case $facts->type === RequestTypes::TYPE_WPCLI:
				$result = [ RequestSensitivity::OPEN, 'not_a_visitor_request' ];
				break;

			case $facts->type === RequestTypes::TYPE_CRON:
				// Anyone can request wp-cron.php; only WordPress's own spawn comes from the server itself.
				$result = $facts->isServerLoopback
					? [ RequestSensitivity::OPEN, 'not_a_visitor_request' ]
					: [ RequestSensitivity::GUARDED, 'external_cron' ];
				break;

			case $facts->isShieldAction:
				$result = [ RequestSensitivity::CRITICAL, 'shield_action' ];
				break;

			case \in_array( $facts->type, [ RequestTypes::TYPE_LOGIN, RequestTypes::TYPE_2FA, RequestTypes::TYPE_XMLRPC ], true ):
			case $this->isLoginSubmission( $facts ):
				$result = [ RequestSensitivity::CRITICAL, 'authentication' ];
				break;

			case $facts->type === RequestTypes::TYPE_MCP:
				$result = [ RequestSensitivity::CRITICAL, 'mcp' ];
				break;

			case $facts->type === RequestTypes::TYPE_REST:
			case $facts->restRoute !== '':
				$result = $this->forRest( $facts, $isRead );
				break;

			case $facts->type === RequestTypes::TYPE_AJAX:
			case $this->pathEndsWith( $facts, '/wp-admin/admin-post.php' ):
				$result = $facts->hasCredentials
					? [ RequestSensitivity::CRITICAL, 'authenticated_admin' ]
					: [ RequestSensitivity::GUARDED, 'anonymous_admin_code' ];
				break;

			case $facts->isAdmin:
				$result = [ RequestSensitivity::CRITICAL, 'admin' ];
				break;

			case $this->isLoginPage( $facts ):
				$result = $this->forLoginPage( $facts, $isRead );
				break;

			case $facts->type === RequestTypes::TYPE_COMMENT:
				$result = [ RequestSensitivity::GUARDED, 'comment' ];
				break;

			case !$isRead:
				$result = [ RequestSensitivity::GUARDED, 'submission' ];
				break;

			case $this->isScript( $facts ):
				$result = [ RequestSensitivity::GUARDED, 'script' ];
				break;

			case !$this->isContentQuery( $facts->query ):
				$result = [ RequestSensitivity::GUARDED, 'query_input' ];
				break;

			case $facts->routeVars !== null && !$this->isContentRoute( $facts ):
				$result = [ RequestSensitivity::GUARDED, 'route_input' ];
				break;

			case $facts->queriedObjectId > 0 && \in_array( $facts->queriedObjectId, $facts->wooPageIds, true ):
				$result = [ RequestSensitivity::GUARDED, 'woocommerce' ];
				break;

			default:
				$result = [ RequestSensitivity::OPEN, 'content', $facts->routeVars === null ];
				break;
		}

		return new RequestSensitivity( ...$result );
	}

	private function forRest( RequestFacts $facts, bool $isRead ) :array {
		$route = \trim( $facts->restRoute, '/' );
		if ( $facts->hasCredentials ) {
			$result = [ RequestSensitivity::CRITICAL, 'authenticated_api' ];
		}
		elseif ( \preg_match( '#^shield/v\d+/action/#', $route ) ) {
			$result = [ RequestSensitivity::CRITICAL, 'shield_action' ];
		}
		elseif ( $isRead && $route === self::OEMBED_ROUTE ) {
			$result = [ RequestSensitivity::OPEN, 'oembed' ];
		}
		else {
			$result = [ RequestSensitivity::GUARDED, 'anonymous_api' ];
		}
		return $result;
	}

	private function forLoginPage( RequestFacts $facts, bool $isRead ) :array {
		if ( \in_array( $facts->action, self::ACCOUNT_FORM_ACTIONS, true ) ) {
			$result = [ RequestSensitivity::GUARDED, 'account_form' ];
		}
		elseif ( $isRead ) {
			$result = [ RequestSensitivity::GUARDED, 'login_page' ];
		}
		else {
			$result = [ RequestSensitivity::CRITICAL, 'authentication' ];
		}
		return $result;
	}

	/**
	 * A submitted username and password, such as WooCommerce's account login, wherever its nonce is.
	 */
	private function isLoginSubmission( RequestFacts $facts ) :bool {
		return $facts->method === 'POST'
			   && \count( \array_intersect( [ 'login', 'username', 'password' ], $facts->postParams ) ) === 3;
	}

	private function isLoginPage( RequestFacts $facts ) :bool {
		return $this->pathEndsWith( $facts, '/wp-login.php' )
			   || \in_array( $this->normalisePath( $facts->path ), \array_map( [ $this, 'normalisePath' ], $facts->loginPaths ), true );
	}

	/**
	 * A PHP file requested directly, such as wp-activate.php or a plugin's own script, rather than through WordPress.
	 */
	private function isScript( RequestFacts $facts ) :bool {
		$path = \strtolower( $facts->path );
		return \str_ends_with( $path, '.php' ) && !\str_ends_with( $path, '/index.php' );
	}

	private function isContentQuery( array $query ) :bool {
		foreach ( $query as $param => $value ) {
			$param = (string)$param;
			$pattern = self::CONTENT_VALUES[ $param ]
					   ?? self::PASSIVE_VALUES[ $param ]
						  ?? ( \str_starts_with( $param, 'utm_' ) ? self::VALUE_TRACKING : null );
			if ( $pattern === null || !$this->valueFits( $value, $pattern ) ) {
				return false;
			}
		}
		return true;
	}

	private function isContentRoute( RequestFacts $facts ) :bool {
		foreach ( (array)$facts->routeVars as $var => $value ) {
			$var = (string)$var;
			$pattern = self::CONTENT_VALUES[ $var ] ?? ( \in_array( $var, $facts->contentVars, true ) ? self::VALUE_SLUG : null );
			if ( $pattern === null || !$this->valueFits( $value, $pattern ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param mixed $value
	 */
	private function valueFits( $value, string $pattern ) :bool {
		return \is_scalar( $value )
			   && ( (string)$value === '' || ( \preg_match( $pattern, (string)$value ) === 1 && !\str_contains( (string)$value, '..' ) ) );
	}

	private function pathEndsWith( RequestFacts $facts, string $suffix ) :bool {
		return \str_ends_with( \strtolower( $facts->path ), $suffix );
	}

	private function normalisePath( string $path ) :string {
		return '/'.\trim( \strtolower( $path ), '/' );
	}
}
