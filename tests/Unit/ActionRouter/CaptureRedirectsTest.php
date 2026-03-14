<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\CaptureRedirects;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Request,
	Response
};
use FernleafSystems\Wordpress\Services\Core\General;

class CaptureRedirectsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];
	private Request $request;
	private RedirectCaptureResponse $responseCapture;

	protected function setUp() :void {
		parent::setUp();

		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ) :string => \strtolower( \trim( $key ) ) );
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$query = [];
				foreach ( $params as $key => $value ) {
					$query[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $query );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->responseCapture = new RedirectCaptureResponse();
		$this->request = new class extends Request {
			public function __construct() {
				parent::__construct();
				$this->query = [];
			}
		};
		ServicesState::installItems( [
			'service_request'  => $this->request,
			'service_response' => $this->responseCapture,
			'service_wpgeneral'=> new class extends General {
				public function getUrl_AdminPage( string $page = '', bool $networkAdmin = false ) :string {
					return '/shield-admin.php?page='.$page;
				}

				public function isAjax() :bool {
					return false;
				}
			},
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'properties' => [
				'wpms_network_admin_only' => false,
				'slug_parent'             => 'icwp',
				'slug_plugin'             => 'wpsf',
			],
		];
		$controller->plugin_urls = new PluginURLs();

		PluginControllerInstaller::install( $controller );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	/**
	 * @dataProvider providerLegacyScanRoutes
	 */
	public function test_legacy_scan_routes_redirect_to_actions_queue( string $subNav ) :void {
		$this->request->query = [
			'page'                => 'icwp-wpsf-plugin',
			Constants::NAV_ID     => 'scans',
			Constants::NAV_SUB_ID => $subNav,
		];

		( new CaptureRedirects() )->run();

		$this->assertSame(
			'/shield-admin.php?page=icwp-wpsf-plugin&nav=scans&nav_sub=overview&zone=scans',
			$this->responseCapture->redirectTo
		);
	}

	public function providerLegacyScanRoutes() :array {
		return [
			'results' => [ 'results' ],
			'history' => [ 'history' ],
			'state'   => [ 'state' ],
		];
	}
}

class RedirectCaptureResponse extends Response {

	public string $redirectTo = '';

	public function redirect( $url, $queryParams = [], $safe = true, $bProtectAgainstInfiniteLoops = true ) {
		$this->redirectTo = \is_string( $url ) ? $url : '';
	}
}
