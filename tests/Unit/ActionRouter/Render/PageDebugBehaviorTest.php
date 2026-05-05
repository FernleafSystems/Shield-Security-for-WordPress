<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageDebug;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PageDebugBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
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
				$query = [];
				foreach ( $params as $key => $value ) {
					$query[] = $key.'='.$value;
				}
				return empty( $query ) ? $url : $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $query );
			}
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_contextual_debug_runtime_controls_use_action_control_contract() :void {
		$contextualHrefs = $this->invokeNonPublicMethod( new PageDebug(), 'getPageContextualHrefs' );

		$purgeAction = $this->findContextualActionByClass( $contextualHrefs, 'tool_purge_provider_ips' );
		$printAction = $this->findContextualActionByClass( $contextualHrefs, 'shield_div_print' );

		$this->assertSame( '', $purgeAction[ 'href' ] ?? 'unexpected' );
		$this->assertTrue( $purgeAction[ 'is_action' ] ?? false );
		$this->assertSame( [ 'tool_purge_provider_ips' ], $purgeAction[ 'classes' ] ?? [] );

		$this->assertSame( '', $printAction[ 'href' ] ?? 'unexpected' );
		$this->assertTrue( $printAction[ 'is_action' ] ?? false );
		$this->assertSame( [ 'shield_div_print' ], $printAction[ 'classes' ] ?? [] );
		$this->assertSame( '#PageMainBody_Inner-Apto', $printAction[ 'data' ][ 'selector' ] ?? '' );
	}

	/**
	 * @param list<array<string,mixed>> $contextualHrefs
	 * @return array<string,mixed>
	 */
	private function findContextualActionByClass( array $contextualHrefs, string $class ) :array {
		foreach ( $contextualHrefs as $contextualHref ) {
			$classes = $contextualHref[ 'classes' ] ?? [];
			if ( \is_array( $classes ) && \in_array( $class, $classes, true ) ) {
				return $contextualHref;
			}
		}

		$this->fail( 'Missing debug contextual action class: '.$class );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function debugInfo() :string {
				return '/shield-admin.php?page=icwp-wpsf-plugin&nav=tools&nav_sub=debug';
			}

			public function noncedPluginAction( string $action, string $url ) :string {
				return $url.'&action='.$action;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
