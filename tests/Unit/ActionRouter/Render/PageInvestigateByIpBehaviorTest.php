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
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class PageInvestigateByIpBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $text ) => \is_string( $text ) ? \trim( $text ) : '' );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );

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
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			],
			$renderData[ 'vars' ][ 'lookup_route' ] ?? []
		);
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
		$this->assertArrayNotHasKey( 'subject', $vars );
		$this->assertArrayNotHasKey( 'summary', $vars );
		$this->assertSame( 'rendered-ip:203.0.113.88', (string)( $renderData[ 'content' ][ 'ip_analysis' ] ?? '' ) );
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
