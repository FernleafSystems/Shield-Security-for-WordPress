<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Request;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request
};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class ThisRequestRestRouteTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'rest_url' )->alias( static fn( string $path = '' ) :string => 'https://example.test/wp-json/'.\ltrim( $path, '/' ) );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url, int $component = -1 ) => \parse_url( $url, $component ) );

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installServices();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$_SERVER = [];
		$_GET = [];
		$_POST = [];

		parent::tearDown();
	}

	public function test_permalink_rest_path_is_normalized() :void {
		$request = $this->buildRequest(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-json/wp/v2/users/me?context=edit',
			]
		);

		$thisReq = new ThisRequest( [
			'request'                  => $request,
			'wp_is_permalinks_enabled' => true,
			'rest_api_root'            => 'https://example.test/wp-json/',
		] );

		$this->assertSame( 'wp/v2/users/me', $thisReq->getRestRoute() );
	}

	public function test_query_rest_route_is_normalized_when_permalinks_disabled() :void {
		$request = $this->buildRequest(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/?rest_route=/wp/v2/users/me',
			],
			[
				'rest_route' => '/wp/v2/users/me',
				'context'    => 'edit',
			]
		);

		$thisReq = new ThisRequest( [
			'request'                  => $request,
			'wp_is_permalinks_enabled' => false,
			'rest_api_root'            => 'https://example.test/wp-json/',
		] );

		$this->assertSame( 'wp/v2/users/me', $thisReq->getRestRoute() );
	}

	public function test_scheme_mismatch_does_not_break_permalink_rest_route() :void {
		$request = $this->buildRequest(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-json/wp/v2/users/me',
				'HTTPS'          => 'off',
			]
		);

		$thisReq = new ThisRequest( [
			'request'                  => $request,
			'wp_is_permalinks_enabled' => true,
			'rest_api_root'            => 'https://example.test/wp-json/',
		] );

		$this->assertSame( 'wp/v2/users/me', $thisReq->getRestRoute() );
	}

	public function test_injected_rest_api_root_takes_precedence_over_global_rest_url() :void {
		Functions\when( 'rest_url' )->alias( static fn( string $path = '' ) :string => 'https://example.test/?rest_route='.rawurlencode( $path ) );

		$request = $this->buildRequest(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-json/wp/v2/users/me',
			]
		);

		$thisReq = new ThisRequest( [
			'request'                  => $request,
			'wp_is_permalinks_enabled' => true,
			'rest_api_root'            => 'https://example.test/wp-json/',
		] );

		$this->assertSame( 'wp/v2/users/me', $thisReq->getRestRoute() );
	}

	public function test_non_rest_request_returns_empty_string() :void {
		$request = $this->buildRequest(
			[
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/wp-admin/index.php',
			]
		);

		$thisReq = new ThisRequest( [
			'request'                  => $request,
			'wp_is_permalinks_enabled' => true,
			'rest_api_root'            => 'https://example.test/wp-json/',
		] );

		$this->assertSame( '', $thisReq->getRestRoute() );
	}

	private function installServices() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new class extends General {
				public function getLocale( $separator = '_' ) {
					return 'en_GB';
				}

				public function getUserLocaleCountry() :string {
					return 'en';
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

				public function getWpUrl( string $path = '' ) :string {
					return 'https://example.test/'.\ltrim( $path, '/' );
				}

				public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
					return 'https://example.test/'.\ltrim( $path, '/' );
				}

				public function getOption( $sKey, $mDefault = false, $bIgnoreWPMS = false ) {
					return $mDefault;
				}
			},
			'service_ip'        => new class extends IpUtils {
				public function isValidIp_PublicRemote( $ip ) :bool {
					return false;
				}
			},
		] );
	}

	private function buildRequest( array $server, array $query = [] ) :Request {
		$_SERVER = \array_merge( [
			'HTTP_HOST'       => 'example.test',
			'HTTP_USER_AGENT' => 'phpunit',
			'REMOTE_ADDR'     => '127.0.0.1',
			'REQUEST_METHOD'  => 'GET',
			'REQUEST_URI'     => '/',
		], $server );
		$_GET = $query;
		$_POST = [];

		$request = new class extends Request {
			public function ip() :string {
				return '127.0.0.1';
			}
		};

		ServicesState::mergeItems( [
			'service_request' => $request,
		] );

		return $request;
	}
}
