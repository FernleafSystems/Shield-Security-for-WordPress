<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Request\Sensitivity;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as RequestTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Request\Sensitivity\{
	ClassifyRequest,
	RequestFacts,
	RequestSensitivity
};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClassifyRequestTest extends TestCase {

	#[DataProvider( 'provideRequests' )]
	public function test_request_is_classified_by_what_it_could_do( array $facts, string $level, string $reason ) :void {
		$sensitivity = ( new ClassifyRequest() )->classify( new RequestFacts( $facts ) );

		$this->assertSame( [ $level, $reason ], [ $sensitivity->level, $sensitivity->reason ] );
	}

	public static function provideRequests() :array {
		$http = [ 'type' => RequestTypes::TYPE_HTTP ];
		$routed = $http + [ 'routeVars' => [] ];
		$rest = [ 'type' => RequestTypes::TYPE_REST, 'path' => '/wp-json/wp/v2/posts', 'restRoute' => 'wp/v2/posts' ];
		$ajax = [ 'type' => RequestTypes::TYPE_AJAX, 'path' => '/wp-admin/admin-ajax.php', 'isAdmin' => true ];
		$login = [ 'type' => RequestTypes::TYPE_HTTP, 'path' => '/wp-login.php' ];

		return [
			// Content
			'front page'                       => [ $http + [ 'path' => '/' ], RequestSensitivity::OPEN, 'content' ],
			'head request'                     => [ $http + [ 'method' => 'HEAD', 'path' => '/about/' ], RequestSensitivity::OPEN, 'content' ],
			'pagination and tracking'          => [ $http + [ 'path' => '/blog/', 'query' => [ 'paged' => '2', 'utm_source' => 'spring newsletter', 'gclid' => 'Cj0KCQ' ] ], RequestSensitivity::OPEN, 'content' ],
			'plain permalink post'             => [ $http + [ 'path' => '/index.php', 'query' => [ 'p' => '42' ] ], RequestSensitivity::OPEN, 'content' ],
			'versioned asset'                  => [ $http + [ 'path' => '/fonts/brand.css', 'query' => [ 'ver' => '6.4.1' ] ], RequestSensitivity::OPEN, 'content' ],
			'feed'                             => [ $http + [ 'path' => '/feed/' ], RequestSensitivity::OPEN, 'content' ],

			// Query input
			'search'                           => [ $http + [ 'query' => [ 's' => 'widgets' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'author enumeration'               => [ $http + [ 'query' => [ 'author' => '1' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'unrecognised parameter'           => [ $http + [ 'query' => [ 'page' => '2', 'file' => 'x' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'traversal in a content parameter' => [ $http + [ 'query' => [ 'page' => '../../wp-config.php' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'injection in a content parameter' => [ $http + [ 'query' => [ 'name' => "' OR 1=1--" ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'non-numeric post id'              => [ $http + [ 'query' => [ 'p' => '42abc' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'array content parameter'          => [ $http + [ 'query' => [ 'p' => [ '1' ] ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'markup in a tracking parameter'   => [ $http + [ 'query' => [ 'utm_source' => '<script>' ] ], RequestSensitivity::GUARDED, 'query_input' ],

			// Scripts and submissions
			'multisite activation'             => [ $http + [ 'path' => '/wp-activate.php' ], RequestSensitivity::GUARDED, 'script' ],
			'multisite signup'                 => [ $http + [ 'path' => '/wp-signup.php' ], RequestSensitivity::GUARDED, 'script' ],
			'trackback'                        => [ $http + [ 'path' => '/wp-trackback.php' ], RequestSensitivity::GUARDED, 'script' ],
			'plugin script'                    => [ $http + [ 'path' => '/wp-content/plugins/gallery/upload.php' ], RequestSensitivity::GUARDED, 'script' ],
			'front-end form post'              => [ $http + [ 'method' => 'POST', 'path' => '/contact/', 'postParams' => [ 'email' ] ], RequestSensitivity::GUARDED, 'submission' ],
			'comment'                          => [ [ 'type' => RequestTypes::TYPE_COMMENT, 'method' => 'POST', 'path' => '/wp-comments-post.php' ], RequestSensitivity::GUARDED, 'comment' ],

			// Login
			'login page'                       => [ $login, RequestSensitivity::GUARDED, 'login_page' ],
			'renamed login page'               => [ $http + [ 'path' => '/my-login', 'loginPaths' => [ '/my-login/' ] ], RequestSensitivity::GUARDED, 'login_page' ],
			'lost password page'               => [ $login + [ 'action' => 'lostpassword' ], RequestSensitivity::GUARDED, 'account_form' ],
			'registration'                     => [ $login + [ 'method' => 'POST', 'action' => 'register' ], RequestSensitivity::GUARDED, 'account_form' ],
			'other login page post'            => [ $login + [ 'method' => 'POST', 'action' => 'postpass' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'login attempt'                    => [ [ 'type' => RequestTypes::TYPE_LOGIN, 'method' => 'POST', 'path' => '/wp-login.php' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'2fa'                              => [ [ 'type' => RequestTypes::TYPE_2FA, 'method' => 'POST', 'path' => '/wp-login.php' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'xml-rpc'                          => [ [ 'type' => RequestTypes::TYPE_XMLRPC, 'method' => 'POST', 'path' => '/xmlrpc.php' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'woocommerce login, either nonce'  => [ $http + [ 'method' => 'POST', 'path' => '/my-account/', 'postParams' => [ 'login', 'username', 'password', '_wpnonce' ] ], RequestSensitivity::CRITICAL, 'authentication' ],

			// REST
			'anonymous rest read'              => [ $rest, RequestSensitivity::GUARDED, 'anonymous_api' ],
			'anonymous rest write'             => [ $rest + [ 'method' => 'POST' ], RequestSensitivity::GUARDED, 'anonymous_api' ],
			'rest with credentials'            => [ $rest + [ 'hasCredentials' => true ], RequestSensitivity::CRITICAL, 'authenticated_api' ],
			'rest not typed as rest'           => [ $http + [ 'path' => '/%77p-json/wp/v2/users/me', 'restRoute' => 'wp/v2/users/me', 'hasCredentials' => true ], RequestSensitivity::CRITICAL, 'authenticated_api' ],
			'oembed'                           => [ [ 'type' => RequestTypes::TYPE_REST, 'restRoute' => '/oembed/1.0/embed' ], RequestSensitivity::OPEN, 'oembed' ],
			'oembed with credentials'          => [ [ 'type' => RequestTypes::TYPE_REST, 'restRoute' => 'oembed/1.0/embed', 'hasCredentials' => true ], RequestSensitivity::CRITICAL, 'authenticated_api' ],
			'oembed proxy'                     => [ [ 'type' => RequestTypes::TYPE_REST, 'restRoute' => 'oembed/1.0/proxy' ], RequestSensitivity::GUARDED, 'anonymous_api' ],
			'shield rest action'               => [ [ 'type' => RequestTypes::TYPE_REST, 'restRoute' => 'shield/v1/action/capture_not_bot' ], RequestSensitivity::CRITICAL, 'shield_action' ],
			'shield mcp'                       => [ [ 'type' => RequestTypes::TYPE_MCP, 'method' => 'POST' ], RequestSensitivity::CRITICAL, 'mcp' ],

			// Admin and Shield
			'anonymous ajax read'              => [ $ajax + [ 'action' => 'load_more' ], RequestSensitivity::GUARDED, 'anonymous_admin_code' ],
			'logged-in ajax'                   => [ $ajax + [ 'method' => 'POST', 'hasCredentials' => true ], RequestSensitivity::CRITICAL, 'authenticated_admin' ],
			'anonymous admin-post'             => [ $http + [ 'path' => '/wp-admin/admin-post.php', 'isAdmin' => true ], RequestSensitivity::GUARDED, 'anonymous_admin_code' ],
			'admin page'                       => [ $http + [ 'path' => '/wp-admin/options.php', 'isAdmin' => true ], RequestSensitivity::CRITICAL, 'admin' ],
			'shield action'                    => [ $http + [ 'method' => 'POST', 'isShieldAction' => true ], RequestSensitivity::CRITICAL, 'shield_action' ],

			// Once routed
			'routed front page'                => [ $routed + [ 'path' => '/' ], RequestSensitivity::OPEN, 'content' ],
			'routed public post type'          => [ $http + [ 'path' => '/product/shirt/', 'routeVars' => [ 'product' => 'shirt', 'post_type' => 'product', 'name' => 'shirt' ], 'contentVars' => [ 'product' ] ], RequestSensitivity::OPEN, 'content' ],
			'routed plugin endpoint'           => [ $http + [ 'path' => '/my-account/view-order/123/', 'routeVars' => [ 'pagename' => 'my-account', 'view-order' => '123' ] ], RequestSensitivity::GUARDED, 'route_input' ],
			'routed api endpoint'              => [ $http + [ 'path' => '/wc-api/paypal/', 'routeVars' => [ 'wc-api' => 'paypal' ] ], RequestSensitivity::GUARDED, 'route_input' ],
			'routed woocommerce page'          => [ $http + [ 'path' => '/checkout/', 'routeVars' => [ 'pagename' => 'checkout' ], 'queriedObjectId' => 21, 'wooPageIds' => [ 20, 21, 22 ] ], RequestSensitivity::GUARDED, 'woocommerce' ],
			'routed plain permalink page'      => [ $http + [ 'path' => '/', 'query' => [ 'page_id' => '5' ], 'routeVars' => [ 'page_id' => '5' ], 'queriedObjectId' => 5, 'wooPageIds' => [ 20, 21, 22 ] ], RequestSensitivity::OPEN, 'content' ],

			// Not visitors
			'cron spawned by wordpress'        => [ [ 'type' => RequestTypes::TYPE_CRON, 'path' => '/wp-cron.php', 'isServerLoopback' => true ], RequestSensitivity::OPEN, 'not_a_visitor_request' ],
			'cron requested from outside'      => [ [ 'type' => RequestTypes::TYPE_CRON, 'path' => '/wp-cron.php' ], RequestSensitivity::GUARDED, 'external_cron' ],
			'wp-cli'                           => [ [ 'type' => RequestTypes::TYPE_WPCLI ], RequestSensitivity::OPEN, 'not_a_visitor_request' ],
		];
	}

	public function test_open_before_routing_is_provisional_and_open_after_routing_is_not() :void {
		$classify = new ClassifyRequest();

		$this->assertTrue( $classify->classify( new RequestFacts( [ 'type' => RequestTypes::TYPE_HTTP, 'path' => '/about/' ] ) )->isProvisional );
		$this->assertFalse( $classify->classify( new RequestFacts( [ 'type' => RequestTypes::TYPE_HTTP, 'path' => '/about/', 'routeVars' => [ 'pagename' => 'about' ] ] ) )->isProvisional );
		$this->assertFalse( $classify->classify( new RequestFacts( [ 'type' => RequestTypes::TYPE_HTTP, 'query' => [ 's' => 'x' ] ] ) )->isProvisional, 'Only Open can be provisional' );
	}
}
