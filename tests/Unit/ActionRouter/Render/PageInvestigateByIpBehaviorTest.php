<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateByIp;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class PageInvestigateByIpBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $text ) => \is_string( $text ) ? \trim( $text ) : '' );
		Functions\when( 'sanitize_key' )->alias( static fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : '' );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = '' ) :string => \hash( 'sha256', $scheme.'|'.$data )
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
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
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_no_lookup_returns_empty_state_flags() :void {
		$this->installServices();
		$page = new PageInvestigateByIpUnitTestDouble();

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? true ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			],
			$renderData[ 'vars' ][ 'lookup_route' ] ?? []
		);
		$this->assertSame(
			[
				'panel_form'            => true,
				'use_select2'           => true,
				'auto_submit_on_change' => true,
			],
			$renderData[ 'vars' ][ 'lookup_behavior' ] ?? []
		);
		$this->assertSame( 'ip', (string)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'subject' ] ?? '' ) );
		$this->assertSame( 2, (int)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'minimum_input_length' ] ?? 0 ) );
		$this->assertSame( 700, (int)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'delay_ms' ] ?? 0 ) );
		$this->assertNotEmpty( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'action' ] ?? [] );
	}

	public function test_invalid_lookup_sets_has_lookup_without_subject() :void {
		$this->installServices(
			[ 'analyse_ip' => 'not-an-ip' ],
			static fn( string $ip ) :bool => false
		);
		$page = new PageInvestigateByIpUnitTestDouble();

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? false ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
	}

	public function test_valid_lookup_retains_lookup_contract_and_renders_ip_analysis_content() :void {
		$this->installServices(
			[ 'analyse_ip' => '203.0.113.88' ],
			static fn( string $ip ) :bool => $ip === '203.0.113.88'
		);
		$page = new PageInvestigateByIpUnitTestDouble();

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ] ?? [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? false ) );
		$this->assertSame( '203.0.113.88', (string)( $vars[ 'analyse_ip' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject', $vars );
		$this->assertArrayNotHasKey( 'summary', $vars );
		$this->assertSame( '203.0.113.88', (string)( $vars[ 'subject_header' ][ 'title' ] ?? '' ) );
		$this->assertSame( 'rendered-ip:203.0.113.88', (string)( $renderData[ 'content' ][ 'ip_analysis' ] ?? '' ) );
	}

	public function test_render_data_includes_lookup_helper_string() :void {
		$this->installServices();
		$page = new PageInvestigateByIpUnitTestDouble();

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertArrayHasKey( 'lookup_helper', $renderData[ 'strings' ] ?? [] );
		$this->assertNotEmpty( $renderData[ 'strings' ][ 'lookup_helper' ] ?? '' );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function rootAdminPageSlug() :string {
				return 'icwp-wpsf-plugin';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function investigateByIp( string $ip = '' ) :string {
				return empty( $ip ) ? '/admin/activity/by_ip' : '/admin/activity/by_ip?analyse_ip='.$ip;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class {
			public function render( string $action, array $actionData = [] ) :string {
				return 'rendered-ip:'.(string)( $actionData[ 'ip' ] ?? '' );
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function installServices( array $query = [], ?\Closure $ipValidator = null ) :void {
		ServicesState::installItems( [
			'service_request' => new class( $query ) extends Request {
				private array $queryValues;

				public function __construct( array $queryValues = [] ) {
					$this->queryValues = $queryValues;
				}

				public function query( $key, $default = null ) {
					return $this->queryValues[ $key ] ?? $default;
				}

				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 1;
				}
			},
			'service_ip'      => new class( $ipValidator ) extends IpUtils {
				private ?\Closure $validator;

				public function __construct( ?\Closure $validator ) {
					$this->validator = $validator;
				}

				public function isValidIp( $ip, $flags = null ) {
					return $this->validator instanceof \Closure
						? (bool)( $this->validator )( (string)$ip )
						: \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false;
				}
			},
		] );
	}

}

class PageInvestigateByIpUnitTestDouble extends PageInvestigateByIp {
}
