<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

/**
 * What a request is and what it carries, as needed to judge how much harm it could do. Route facts arrive in
 * two steps: the query vars once WordPress has parsed the request (`parse_request`), and the queried post once
 * it has run its main query (`wp`). Before that, `routeVars` is null and `routingComplete` is false.
 */
class RequestFacts {

	/**
	 * A request type from RequestTypeResolver.
	 */
	public string $type = '';

	public string $method = 'GET';

	/**
	 * The request path, without the query string.
	 */
	public string $path = '/';

	/**
	 * The file name of the PHP script the server is running for this request, such as index.php.
	 */
	public string $scriptName = '';

	/**
	 * The REST route, if this is a REST request, however it was addressed.
	 */
	public string $restRoute = '';

	/**
	 * The request's `action` parameter, as used by admin-ajax.php, admin-post.php and wp-login.php.
	 */
	public string $action = '';

	/**
	 * @var array<string, mixed> the query parameters and their values
	 */
	public array $query = [];

	/**
	 * @var string[] names of the POST parameters
	 */
	public array $postParams = [];

	/**
	 * A logged-in cookie, or credentials in the Authorization header.
	 */
	public bool $hasCredentials = false;

	public bool $isAdmin = false;

	public bool $isShieldAction = false;

	/**
	 * The request came from the web server itself, as WordPress's own cron spawn does.
	 */
	public bool $isServerLoopback = false;

	/**
	 * @var string[] login page paths other than wp-login.php, such as Shield's renamed login
	 */
	public array $loginPaths = [];

	/**
	 * @var ?array<string, mixed> the query vars WordPress routed the request to, once it has
	 */
	public ?array $routeVars = null;

	/**
	 * @var string[] query vars of public post types and taxonomies, which only choose content
	 */
	public array $contentVars = [];

	/**
	 * @var string[] names of public taxonomies
	 */
	public array $contentTaxonomies = [];

	/**
	 * WordPress has run its main query, so the queried post is known.
	 */
	public bool $routingComplete = false;

	/**
	 * The ID of the single post or page the request was routed to, or 0 for anything else.
	 */
	public int $queriedPostId = 0;

	/**
	 * @var int[] IDs of WooCommerce's cart, checkout and account pages, when WooCommerce is active
	 */
	public array $wooPageIds = [];

	public function __construct( array $facts = [] ) {
		foreach ( $facts as $key => $value ) {
			if ( \property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
		$this->method = \strtoupper( $this->method );
	}
}
