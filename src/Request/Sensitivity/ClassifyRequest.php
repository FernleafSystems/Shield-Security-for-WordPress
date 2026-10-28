<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as RequestTypes;

/**
 * Judges how much harm a request could do from what it touches, not from its HTTP method. A request that
 * isn't recognised as harmless is Guarded.
 */
class ClassifyRequest {

	/**
	 * Query parameters that only choose which content to show, or track a visit.
	 */
	private const HARMLESS_QUERY_PARAMS = [
		'p',
		'page_id',
		'name',
		'pagename',
		'cat',
		'category_name',
		'tag',
		'year',
		'monthnum',
		'day',
		'm',
		'paged',
		'page',
		'cpage',
		'feed',
		'attachment_id',
		'replytocom',
		'ver',
		'gclid',
		'fbclid',
		'msclkid',
	];

	private const ACCOUNT_FORM_ACTIONS = [
		'register',
		'lostpassword',
		'retrievepassword',
		'rp',
		'resetpass',
	];

	public function classify( RequestFacts $facts ) :RequestSensitivity {
		$isRead = \in_array( $facts->method, [ 'GET', 'HEAD' ], true );

		switch ( true ) {
			case \in_array( $facts->type, [ RequestTypes::TYPE_WPCLI, RequestTypes::TYPE_CRON ], true ):
				$result = [ RequestSensitivity::OPEN, 'not_a_visitor_request' ];
				break;

			case $facts->isShieldAction:
				$result = [ RequestSensitivity::CRITICAL, 'shield_action' ];
				break;

			case \in_array( $facts->type, [ RequestTypes::TYPE_LOGIN, RequestTypes::TYPE_2FA, RequestTypes::TYPE_XMLRPC ], true ):
				$result = [ RequestSensitivity::CRITICAL, 'authentication' ];
				break;

			case $facts->type === RequestTypes::TYPE_MCP:
				$result = [ RequestSensitivity::CRITICAL, 'mcp' ];
				break;

			case $facts->type === RequestTypes::TYPE_REST:
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

			case $this->pathEndsWith( $facts, '/wp-login.php' ):
				$result = $this->forLoginPage( $facts, $isRead );
				break;

			case $this->isWooCommerceLogin( $facts ):
				$result = [ RequestSensitivity::CRITICAL, 'authentication' ];
				break;

			case $this->isWooCommerce( $facts ):
				$result = [ RequestSensitivity::GUARDED, 'woocommerce' ];
				break;

			case $facts->type === RequestTypes::TYPE_COMMENT:
				$result = [ RequestSensitivity::GUARDED, 'comment' ];
				break;

			case !$isRead:
				$result = [ RequestSensitivity::GUARDED, 'submission' ];
				break;

			case !empty( $this->unrecognisedQueryParams( $facts ) ):
				$result = [ RequestSensitivity::GUARDED, 'query_input' ];
				break;

			default:
				$result = [ RequestSensitivity::OPEN, 'content' ];
				break;
		}

		return new RequestSensitivity( ...$result );
	}

	private function forRest( RequestFacts $facts, bool $isRead ) :array {
		if ( $isRead && \str_starts_with( \trim( $facts->restRoute, '/' ), 'oembed/' ) ) {
			$result = [ RequestSensitivity::OPEN, 'oembed' ];
		}
		elseif ( $facts->hasCredentials ) {
			$result = [ RequestSensitivity::CRITICAL, 'authenticated_api' ];
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

	private function isWooCommerceLogin( RequestFacts $facts ) :bool {
		return !empty( $facts->wooPagePaths )
			   && \in_array( 'woocommerce-login-nonce', $facts->postParams, true );
	}

	private function isWooCommerce( RequestFacts $facts ) :bool {
		return !empty( $facts->wooPagePaths )
			   && (
				   \in_array( $this->normalisePath( $facts->path ), \array_map( [ $this, 'normalisePath' ], $facts->wooPagePaths ), true )
				   || \in_array( 'wc-ajax', $facts->queryParams, true )
				   || \in_array( 'add-to-cart', $facts->queryParams, true )
			   );
	}

	/**
	 * @return string[]
	 */
	private function unrecognisedQueryParams( RequestFacts $facts ) :array {
		return \array_filter(
			$facts->queryParams,
			fn( string $param ) => !\in_array( $param, self::HARMLESS_QUERY_PARAMS, true ) && !\str_starts_with( $param, 'utm_' )
		);
	}

	private function pathEndsWith( RequestFacts $facts, string $suffix ) :bool {
		return \str_ends_with( \strtolower( $facts->path ), $suffix );
	}

	private function normalisePath( string $path ) :string {
		return '/'.\trim( \strtolower( $path ), '/' );
	}
}
