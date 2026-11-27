<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\HttpRedirect;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestGeneral,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

/**
 * Only an address typed into the rule may send a visitor off-site. A destination filled in from the request goes only
 * to a host WordPress allows, and is skipped otherwise.
 */
class HttpRedirectTest extends BaseUnitTest {

	private const ALLOWED_HOST = 'example.com';

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_ip'      => new IpUtils(),
			'service_request'   => new class() extends UnitTestRequest {
				public function __construct() {
					parent::__construct();
					$this->server = [];
				}
			},
			'service_wpgeneral' => new class() extends UnitTestGeneral {
				public function getLocale( $separator = '_' ) {
					unset( $separator );
					return 'en_US';
				}

				public function isAjax() :bool {
					return false;
				}

				public function isCron() :bool {
					return false;
				}

				public function isDebug() :bool {
					return false;
				}

				public function isWpCli() :bool {
					return false;
				}

				public function isXmlrpc() :bool {
					return false;
				}

				public function isPermalinksEnabled() :bool {
					return true;
				}
			},
		] );
		Functions\when( '__' )->returnArg();
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => \parse_url( $url, $component )
		);
		Functions\when( 'wp_validate_redirect' )->alias(
			static fn( string $location, string $fallback = '' ) :string => \parse_url( $location, \PHP_URL_HOST ) === self::ALLOWED_HOST ? $location : $fallback
		);
		foreach ( [ 'wp_redirect', 'wp_safe_redirect' ] as $function ) {
			Functions\when( $function )->alias(
				static function ( string $location, int $status = 302 ) use ( $function ) :void {
					throw new RedirectIntercepted( $function, $location, $status );
				}
			);
		}
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_typed_off_site_address_redirects_off_site() :void {
		$redirect = $this->redirectFor( 'https://elsewhere.example/page', '' );

		$this->assertSame( [ 'wp_redirect', 'https://elsewhere.example/page', 301 ], $redirect );
	}

	public function test_local_path_uses_safe_redirect() :void {
		$redirect = $this->redirectFor( '/wp-login.php', '' );

		$this->assertSame( [ 'wp_safe_redirect', '/wp-login.php', 301 ], $redirect );
	}

	public function test_destination_filled_from_the_request_may_go_to_an_allowed_host() :void {
		$redirect = $this->redirectFor( 'https://elsewhere.example/{{request.useragent}}', 'https://example.com/landing' );

		$this->assertSame( [ 'wp_safe_redirect', 'https://example.com/landing', 301 ], $redirect );
	}

	/**
	 * A redirect would be returned rather than thrown, so the exception is the skip.
	 */
	public function test_destination_filled_from_the_request_is_skipped_for_a_host_not_allowed() :void {
		$this->expectException( \Exception::class );

		$this->redirectFor( 'https://elsewhere.example/{{request.useragent}}', 'https://evil.example/' );
	}

	/**
	 * The request value matching the typed text exactly doesn't make it a typed address.
	 */
	public function test_request_value_identical_to_the_typed_text_is_still_treated_as_filled_from_the_request() :void {
		$typed = 'https://elsewhere.example/{{request.useragent}}';

		$this->expectException( \Exception::class );
		$this->redirectFor( $typed, $typed );
	}

	/**
	 * @return array{0:string,1:string,2:int}
	 */
	private function redirectFor( string $redirectUrl, string $userAgent ) :array {
		$req = new ThisRequest();
		$req->host = 'example.com';
		$req->path = '/current-page';
		$req->wp_is_ajax = false;
		$req->useragent = $userAgent;

		try {
			( new HttpRedirect() )
				->setThisRequest( $req )
				->setParams( [
					'redirect_url' => $redirectUrl,
					'status_code'  => 301,
				] )
				->execResponse();
		}
		catch ( RedirectIntercepted $redirect ) {
			return [ $redirect->function, $redirect->location, $redirect->status ];
		}
		$this->fail( 'Expected a redirect or a skip.' );
	}
}

final class RedirectIntercepted extends \RuntimeException {

	public string $function;

	public string $location;

	public int $status;

	public function __construct( string $function, string $location, int $status ) {
		parent::__construct( $function );
		$this->function = $function;
		$this->location = $location;
		$this->status = $status;
	}
}
