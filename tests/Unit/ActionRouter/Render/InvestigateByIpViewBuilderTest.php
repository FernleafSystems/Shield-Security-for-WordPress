<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateByIpViewBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class InvestigateByIpViewBuilderTest extends BaseUnitTest {

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

	public function test_valid_lookup_builds_expected_contract_and_inline_tabs_flag() :void {
		$this->installServices( static fn( string $ip ) :bool => $ip === '203.0.113.88' );

		$renderData = ( new InvestigateByIpViewBuilder() )->build( '203.0.113.88', true );

		$this->assertTrue( $renderData[ 'flags' ][ 'has_lookup' ] );
		$this->assertTrue( $renderData[ 'flags' ][ 'has_subject' ] );
		$this->assertSame( '/admin/activity/by_ip', $renderData[ 'hrefs' ][ 'by_ip' ] );
		$this->assertSame( '203.0.113.88', $renderData[ 'vars' ][ 'analyse_ip' ] );
		$this->assertSame( '203.0.113.88', $renderData[ 'vars' ][ 'subject_header' ][ 'title' ] );
		$this->assertSame(
			[
				'panel_form'            => true,
				'use_select2'           => true,
				'auto_submit_on_change' => true,
			],
			$renderData[ 'vars' ][ 'lookup_behavior' ]
		);
		$this->assertSame( 'ip', $renderData[ 'vars' ][ 'lookup_ajax' ][ 'subject' ] );
		$this->assertSame( 'rendered-ip:203.0.113.88:tabs', $renderData[ 'content' ][ 'ip_analysis' ] );
	}

	public function test_invalid_lookup_preserves_lookup_contract_without_ip_analysis_content() :void {
		$this->installServices( static fn( string $ip ) :bool => false );

		$renderData = ( new InvestigateByIpViewBuilder() )->build( 'not-an-ip' );

		$this->assertTrue( $renderData[ 'flags' ][ 'has_lookup' ] );
		$this->assertFalse( $renderData[ 'flags' ][ 'has_subject' ] );
		$this->assertSame( 'not-an-ip', $renderData[ 'vars' ][ 'analyse_ip' ] );
		$this->assertSame( '', $renderData[ 'content' ][ 'ip_analysis' ] );
		$this->assertNotEmpty( $renderData[ 'vars' ][ 'lookup_ajax' ] );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function rootAdminPageSlug() :string {
				return 'icwp-wpsf-plugin';
			}

			public function investigateByIp( string $ip = '' ) :string {
				return '/admin/activity/by_ip';
			}
		};
		$controller->action_router = new class {
			public function render( string $action, array $actionData = [] ) :string {
				return 'rendered-ip:'.(string)( $actionData[ 'ip' ] ?? '' ).':'.( !empty( $actionData[ 'render_inline_tabs' ] ) ? 'tabs' : 'plain' );
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function installServices( ?\Closure $ipValidator = null ) :void {
		ServicesState::installItems( [
			'service_request' => new class extends Request {
				public function query( $key, $default = null ) {
					return $default;
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
			'service_ip' => new class( $ipValidator ) extends IpUtils {
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
