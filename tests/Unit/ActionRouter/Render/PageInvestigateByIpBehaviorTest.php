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
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	RenderCapture,
	ServicesState,
	UnitTestActionRouter,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestIpUtils,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestUsers
};

class PageInvestigateByIpBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
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
		$this->assertSame( 3, (int)( $renderData[ 'vars' ][ 'lookup_ajax' ][ 'minimum_input_length' ] ?? 0 ) );
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
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			new UnitTestActionRouter(
				new RenderCapture(),
				static fn( string $action, array $actionData ) :string => 'rendered-ip:'.(string)( $actionData[ 'ip' ] ?? '' )
			)
		);
	}

	private function installServices( array $query = [], ?\Closure $ipValidator = null ) :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( $query ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers(),
			'service_ip'        => new UnitTestIpUtils( $ipValidator ),
		] );
	}

}

class PageInvestigateByIpUnitTestDouble extends PageInvestigateByIp {
}
