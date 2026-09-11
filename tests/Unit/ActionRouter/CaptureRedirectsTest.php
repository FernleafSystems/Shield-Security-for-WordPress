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
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\{
	PluginNavs,
	PluginURLs
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;
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
	private bool $isAjax = false;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();

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
			'service_wpgeneral'=> new class( fn() :bool => $this->isAjax ) extends General {
				private \Closure $isAjaxRequest;

				public function __construct( \Closure $isAjaxRequest ) {
					$this->isAjaxRequest = $isAjaxRequest;
				}

				public function getUrl_AdminPage( string $page = '', bool $networkAdmin = false ) :string {
					return '/shield-admin.php?page='.$page;
				}

				public function isAjax() :bool {
					return ( $this->isAjaxRequest )();
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
		$controller->comps = (object)[ 'zones' => new SecurityZonesCon() ];

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

	public static function providerLegacyScanRoutes() :array {
		return [
			'results' => [ 'results' ],
			'history' => [ 'history' ],
			'state'   => [ 'state' ],
		];
	}

	/**
	 * @dataProvider providerLegacyReportsRoutes
	 */
	public function test_legacy_reports_routes_redirect_to_canonical_workspace( string $subNav, string $workspace ) :void {
		$this->request->query = [
			'page'                => 'icwp-wpsf-plugin',
			Constants::NAV_ID     => 'reports',
			Constants::NAV_SUB_ID => $subNav,
		];

		( new CaptureRedirects() )->run();

		$this->assertSame(
			'/shield-admin.php?page=icwp-wpsf-plugin&nav=reports&nav_sub=overview&workspace='.$workspace,
			$this->responseCapture->redirectTo
		);
	}

	public static function providerLegacyReportsRoutes() :array {
		return [
			'alerts'    => [ 'alerts', PluginNavs::SUBNAV_REPORTS_SETTINGS ],
			'reporting' => [ 'reporting', PluginNavs::SUBNAV_REPORTS_SETTINGS ],
			'list'      => [ PluginNavs::SUBNAV_REPORTS_LIST, PluginNavs::SUBNAV_REPORTS_LIST ],
			'settings'  => [ PluginNavs::SUBNAV_REPORTS_SETTINGS, PluginNavs::SUBNAV_REPORTS_SETTINGS ],
			'charts'    => [ PluginNavs::SUBNAV_REPORTS_CHARTS, PluginNavs::SUBNAV_REPORTS_CHARTS ],
		];
	}

	public function test_navigation_redirect_does_not_intercept_a_plugin_action() :void {
		$this->request->query = [
			'page' => 'icwp-wpsf-plugin', 'nav' => 'reports', 'nav_sub' => 'list',
			'action' => 'shield_action', 'ex' => 'report_create',
		];
		( new CaptureRedirects() )->run();
		$this->assertSame( '', $this->responseCapture->redirectTo );
	}

	/** @dataProvider providerOldPageSlugs */
	public function test_old_page_slugs_preserve_the_final_destination( string $nav, ?string $subNav, string $destination ) :void {
		$query = [
			'page' => 'icwp-wpsf-'.$nav,
			'unrecognized' => 'must-not-forward',
		];
		if ( $subNav !== null ) {
			$query[ Constants::NAV_SUB_ID ] = $subNav;
		}
		$this->request->query = $query;

		( new CaptureRedirects() )->run();

		$this->assertSame( '/shield-admin.php?page=icwp-wpsf-plugin&'.$destination, $this->responseCapture->redirectTo );
	}

	public static function providerOldPageSlugs() :array {
		return [
			'reports charts' => [ 'reports', 'charts', 'nav=reports&nav_sub=overview&workspace=charts' ],
			'live traffic' => [ 'traffic', 'live', 'nav=activity&nav_sub=overview&subject=live_traffic' ],
			'sessions alias' => [ 'tools', 'sessions', 'nav=activity&nav_sub=sessions' ],
			'retired component' => [ 'zone_components', 'whitelabel', 'nav=zones&nav_sub=overview&component=whitelabel' ],
			'scan history' => [ 'scans', 'history', 'nav=scans&nav_sub=overview&zone=scans' ],
			'retained leaf' => [ 'rules', 'build', 'nav=rules&nav_sub=build' ],
			'missing subnav' => [ 'reports', null, 'nav=reports&nav_sub=overview' ],
			'empty subnav' => [ 'reports', '', 'nav=reports&nav_sub=overview' ],
			'invalid subnav' => [ 'reports', 'unknown', 'nav=reports&nav_sub=overview' ],
		];
	}

	public function test_old_page_slug_action_is_left_to_action_capture() :void {
		$this->request->query = [ 'page' => 'icwp-wpsf-reports', 'nav_sub' => 'charts' ];
		$this->request->post = [ 'action' => 'shield_action', 'ex' => 'report_create' ];
		( new CaptureRedirects() )->run();
		$this->assertSame( '', $this->responseCapture->redirectTo );
	}

	public function test_old_page_slug_ajax_request_is_not_redirected() :void {
		$this->isAjax = true;
		$this->request->query = [ 'page' => 'icwp-wpsf-reports', 'nav_sub' => 'charts' ];
		( new CaptureRedirects() )->run();
		$this->assertSame( '', $this->responseCapture->redirectTo );
	}
}

class RedirectCaptureResponse extends Response {

	public string $redirectTo = '';

	public function redirect( $url, $queryParams = [], $safe = true, $bProtectAgainstInfiniteLoops = true ) {
		$this->redirectTo = \is_string( $url ) ? $url : '';
	}
}
