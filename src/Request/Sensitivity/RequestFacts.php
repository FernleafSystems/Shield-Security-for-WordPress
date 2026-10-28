<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity;

/**
 * What a request is and what it carries, as needed to judge how much harm it could do.
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

	public string $restRoute = '';

	/**
	 * The request's `action` parameter, as used by admin-ajax.php, admin-post.php and wp-login.php.
	 */
	public string $action = '';

	/**
	 * @var string[] names of the query parameters
	 */
	public array $queryParams = [];

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
	 * @var string[] paths of WooCommerce's cart, checkout and account pages, when WooCommerce is active
	 */
	public array $wooPagePaths = [];

	public function __construct( array $facts = [] ) {
		foreach ( $facts as $key => $value ) {
			if ( \property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
		$this->method = \strtoupper( $this->method );
	}
}
