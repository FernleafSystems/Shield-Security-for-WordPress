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

	private const WOO_PAGES = [ '/cart/', '/checkout/', '/my-account/' ];

	#[DataProvider( 'provideRequests' )]
	public function test_request_is_classified_by_what_it_could_do( array $facts, string $level, string $reason ) :void {
		$sensitivity = ( new ClassifyRequest() )->classify( new RequestFacts( $facts ) );

		$this->assertSame( [ $level, $reason ], [ $sensitivity->level, $sensitivity->reason ] );
	}

	public static function provideRequests() :array {
		$http = [ 'type' => RequestTypes::TYPE_HTTP ];
		$rest = [ 'type' => RequestTypes::TYPE_REST, 'path' => '/wp-json/wp/v2/posts', 'restRoute' => 'wp/v2/posts' ];
		$ajax = [ 'type' => RequestTypes::TYPE_AJAX, 'path' => '/wp-admin/admin-ajax.php', 'isAdmin' => true ];
		$login = [ 'type' => RequestTypes::TYPE_HTTP, 'path' => '/wp-login.php' ];
		$woo = [ 'type' => RequestTypes::TYPE_HTTP, 'wooPagePaths' => self::WOO_PAGES ];

		return [
			'front page'                     => [ $http + [ 'path' => '/' ], RequestSensitivity::OPEN, 'content' ],
			'head request'                   => [ $http + [ 'method' => 'HEAD', 'path' => '/about/' ], RequestSensitivity::OPEN, 'content' ],
			'pagination and tracking'        => [ $http + [ 'path' => '/blog/', 'queryParams' => [ 'paged', 'utm_source', 'gclid' ] ], RequestSensitivity::OPEN, 'content' ],
			'feed'                           => [ $http + [ 'path' => '/feed/' ], RequestSensitivity::OPEN, 'content' ],
			'versioned asset'                => [ $http + [ 'path' => '/style.php', 'queryParams' => [ 'ver' ] ], RequestSensitivity::OPEN, 'content' ],
			'search'                         => [ $http + [ 'path' => '/', 'queryParams' => [ 's' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'author enumeration'             => [ $http + [ 'path' => '/', 'queryParams' => [ 'author' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'unrecognised parameter'         => [ $http + [ 'path' => '/', 'queryParams' => [ 'page', 'file' ] ], RequestSensitivity::GUARDED, 'query_input' ],
			'front-end form post'            => [ $http + [ 'method' => 'POST', 'path' => '/contact/', 'postParams' => [ 'email' ] ], RequestSensitivity::GUARDED, 'submission' ],
			'comment'                        => [ [ 'type' => RequestTypes::TYPE_COMMENT, 'method' => 'POST', 'path' => '/wp-comments-post.php' ], RequestSensitivity::GUARDED, 'comment' ],
			'login page'                     => [ $login, RequestSensitivity::GUARDED, 'login_page' ],
			'lost password page'             => [ $login + [ 'action' => 'lostpassword' ], RequestSensitivity::GUARDED, 'account_form' ],
			'registration'                   => [ $login + [ 'method' => 'POST', 'action' => 'register' ], RequestSensitivity::GUARDED, 'account_form' ],
			'other login page post'          => [ $login + [ 'method' => 'POST', 'action' => 'postpass' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'login attempt'                  => [ [ 'type' => RequestTypes::TYPE_LOGIN, 'method' => 'POST', 'path' => '/wp-login.php' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'2fa'                            => [ [ 'type' => RequestTypes::TYPE_2FA, 'method' => 'POST', 'path' => '/wp-login.php' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'xml-rpc'                        => [ [ 'type' => RequestTypes::TYPE_XMLRPC, 'method' => 'POST', 'path' => '/xmlrpc.php' ], RequestSensitivity::CRITICAL, 'authentication' ],
			'anonymous rest read'            => [ $rest, RequestSensitivity::GUARDED, 'anonymous_api' ],
			'anonymous rest write'           => [ $rest + [ 'method' => 'POST' ], RequestSensitivity::GUARDED, 'anonymous_api' ],
			'rest with credentials'          => [ $rest + [ 'hasCredentials' => true ], RequestSensitivity::CRITICAL, 'authenticated_api' ],
			'oembed'                         => [ [ 'type' => RequestTypes::TYPE_REST, 'restRoute' => '/oembed/1.0/embed' ], RequestSensitivity::OPEN, 'oembed' ],
			'shield mcp'                     => [ [ 'type' => RequestTypes::TYPE_MCP, 'method' => 'POST' ], RequestSensitivity::CRITICAL, 'mcp' ],
			'anonymous ajax read'            => [ $ajax + [ 'action' => 'load_more' ], RequestSensitivity::GUARDED, 'anonymous_admin_code' ],
			'logged-in ajax'                 => [ $ajax + [ 'method' => 'POST', 'hasCredentials' => true ], RequestSensitivity::CRITICAL, 'authenticated_admin' ],
			'anonymous admin-post'           => [ $http + [ 'path' => '/wp-admin/admin-post.php', 'isAdmin' => true ], RequestSensitivity::GUARDED, 'anonymous_admin_code' ],
			'admin page'                     => [ $http + [ 'path' => '/wp-admin/options.php', 'isAdmin' => true ], RequestSensitivity::CRITICAL, 'admin' ],
			'shield action'                  => [ $http + [ 'method' => 'POST', 'isShieldAction' => true ], RequestSensitivity::CRITICAL, 'shield_action' ],
			'woocommerce cart'               => [ $woo + [ 'path' => '/cart' ], RequestSensitivity::GUARDED, 'woocommerce' ],
			'woocommerce ajax'               => [ $woo + [ 'path' => '/', 'queryParams' => [ 'wc-ajax' ] ], RequestSensitivity::GUARDED, 'woocommerce' ],
			'woocommerce add to cart'        => [ $woo + [ 'path' => '/shop/', 'queryParams' => [ 'add-to-cart' ] ], RequestSensitivity::GUARDED, 'woocommerce' ],
			'woocommerce account login'      => [ $woo + [ 'method' => 'POST', 'path' => '/my-account/', 'postParams' => [ 'username', 'password', 'woocommerce-login-nonce' ] ], RequestSensitivity::CRITICAL, 'authentication' ],
			'cart path without woocommerce'  => [ $http + [ 'path' => '/cart/' ], RequestSensitivity::OPEN, 'content' ],
			'cron'                           => [ [ 'type' => RequestTypes::TYPE_CRON, 'path' => '/wp-cron.php' ], RequestSensitivity::OPEN, 'not_a_visitor_request' ],
			'wp-cli'                         => [ [ 'type' => RequestTypes::TYPE_WPCLI ], RequestSensitivity::OPEN, 'not_a_visitor_request' ],
		];
	}
}
