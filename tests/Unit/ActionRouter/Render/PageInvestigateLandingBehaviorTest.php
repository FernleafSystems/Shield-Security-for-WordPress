<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Request;

class PageInvestigateLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( fn( $text ) => $text );
		Functions\when( '__' )->alias( fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installServices();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_landing_vars_include_canonical_investigate_subject_contract() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$vars = $this->invokeProtectedMethod( $page, 'getLandingVars' );
		$subjects = $vars[ 'subjects' ] ?? [];

		$this->assertSame( '', $vars[ 'active_subject' ] ?? null );
		$this->assertCount( 7, $subjects );
		$this->assertSame(
			[ 'user', 'ip', 'plugin', 'theme', 'core', 'live_traffic', 'premium_integrations' ],
			\array_values( \array_map( fn( array $subject ) :string => $subject[ 'key' ], $subjects ) )
		);

		$subjectsByKey = [];
		foreach ( $subjects as $subject ) {
			$subjectsByKey[ $subject[ 'key' ] ] = $subject;
			foreach ( [
				'key',
				'panel_target',
				'is_enabled',
				'is_disabled',
				'is_pro',
				'title',
				'icon_class',
				'status',
				'stat_text',
				'panel_title',
				'panel_status',
				'panel_body',
				'render_action',
			] as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $subject );
			}

			$this->assertSame( $subject[ 'key' ], $subject[ 'panel_target' ] );
			$this->assertSame( !(bool)$subject[ 'is_enabled' ], (bool)$subject[ 'is_disabled' ] );
		}

		foreach ( [ 'user', 'ip', 'plugin', 'theme', 'core', 'live_traffic' ] as $enabledKey ) {
			$this->assertTrue( $subjectsByKey[ $enabledKey ][ 'is_enabled' ] );
			$this->assertNotSame( [], $subjectsByKey[ $enabledKey ][ 'render_action' ] );
			$this->assertStringContainsString( 'body-for:', $subjectsByKey[ $enabledKey ][ 'panel_body' ] );
		}

		$this->assertFalse( $subjectsByKey[ 'premium_integrations' ][ 'is_enabled' ] );
		$this->assertTrue( $subjectsByKey[ 'premium_integrations' ][ 'is_disabled' ] );
		$this->assertSame( [], $subjectsByKey[ 'premium_integrations' ][ 'render_action' ] );
		$this->assertSame( '', $subjectsByKey[ 'premium_integrations' ][ 'panel_body' ] );
	}

	public function test_mode_shell_contract_is_exposed_in_render_data() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$renderData = $this->invokeProtectedMethod( $page, 'getRenderData' );

		$this->assertSame( 'investigate', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 'info', $renderData[ 'vars' ][ 'mode_shell' ][ 'accent_status' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] ?? false ) );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? false ) );

		$this->assertCount( 7, $renderData[ 'vars' ][ 'mode_tiles' ] ?? [] );
		$this->assertSame( '', $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? 'missing' );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_panel' ][ 'is_open' ] ?? true ) );
	}

	public function test_active_panel_context_is_derived_from_subject_and_lookup_action_data() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$page->action_data = [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.99',
		];

		$renderData = $this->invokeProtectedMethod( $page, 'getRenderData' );
		$this->assertSame( 'ip', $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? '' );

		$subjects = $renderData[ 'vars' ][ 'subjects' ] ?? [];
		$subjectsByKey = [];
		foreach ( $subjects as $subject ) {
			$subjectsByKey[ $subject[ 'key' ] ] = $subject;
		}

		$this->assertStringContainsString(
			'analyse_ip=203.0.113.99',
			$subjectsByKey[ 'ip' ][ 'panel_body' ] ?? ''
		);
	}

	public function test_subject_panel_payload_is_cached_per_instance() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$this->invokeProtectedMethod( $page, 'getLandingVars' );
		$this->invokeProtectedMethod( $page, 'getLandingTiles' );
		$this->invokeProtectedMethod( $page, 'getLandingVars' );

		$this->assertCount( 6, $this->renderCapture->calls );
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class( $this->renderCapture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->calls[] = [
					'action'      => $action,
					'action_data' => $actionData,
				];
				$lookupData = '';
				foreach ( [ 'user_lookup', 'analyse_ip', 'plugin_slug', 'theme_slug' ] as $lookupKey ) {
					if ( isset( $actionData[ $lookupKey ] ) && $actionData[ $lookupKey ] !== '' ) {
						$lookupData = ';'.$lookupKey.'='.$actionData[ $lookupKey ];
						break;
					}
				}

				return '<div class="inner-page-body-shell"><div>body-for:'.$action.$lookupData.'</div></div>';
			}
		};
		PluginControllerInstaller::install( $controller );
	}

	private function installServices( array $query = [] ) :void {
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
		] );
	}

	private function invokeProtectedMethod( object $subject, string $methodName ) :array {
		return $this->invokeNonPublicMethod( $subject, $methodName );
	}
}

class PageInvestigateLandingUnitTestDouble extends PageInvestigateLanding {

	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return \array_merge( [
			'render_slug' => $renderAction::SLUG,
		], $auxData );
	}
}
