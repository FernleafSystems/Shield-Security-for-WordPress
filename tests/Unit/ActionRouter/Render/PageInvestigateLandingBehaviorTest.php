<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateLanding;
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

class PageInvestigateLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( 'sanitize_text_field' )->alias( fn( $text ) => $text );
		Functions\when( '__' )->alias( fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
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
		$subjectDefinitions = PluginNavs::investigateLandingSubjectDefinitions();
		$enabledSubjectDefinitions = \array_filter(
			$subjectDefinitions,
			static fn( array $subject ) :bool => (bool)( $subject[ 'is_enabled' ] ?? false )
		);

		$this->assertSame( '', $vars[ 'active_subject' ] ?? null );
		$this->assertCount( \count( $subjectDefinitions ), $subjects );
		$this->assertSame(
			\array_keys( $subjectDefinitions ),
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
				'is_loaded',
				'is_live',
				'title',
				'icon_class',
				'status',
				'stat_text',
				'lookup_key',
				'subject_title',
				'subject_icon',
				'subject_meta',
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

		foreach ( \array_keys( $enabledSubjectDefinitions ) as $enabledKey ) {
			$subjectDefinition = $subjectDefinitions[ $enabledKey ];
			$this->assertTrue( $subjectsByKey[ $enabledKey ][ 'is_enabled' ] );
			$this->assertFalse( $subjectsByKey[ $enabledKey ][ 'is_loaded' ] );
			$this->assertNotSame( [], $subjectsByKey[ $enabledKey ][ 'render_action' ] );
			$this->assertStringContainsString( 'data-investigate-panel-placeholder="1"', $subjectsByKey[ $enabledKey ][ 'panel_body' ] ?? '' );
			$this->assertSame( '', $subjectsByKey[ $enabledKey ][ 'subject_title' ] );
			$this->assertSame(
				(string)( $subjectDefinition[ 'lookup_key' ] ?? '' ),
				$subjectsByKey[ $enabledKey ][ 'lookup_key' ] ?? ''
			);
			$this->assertSame(
				$subjectDefinition[ 'render_action' ]::SLUG,
				$subjectsByKey[ $enabledKey ][ 'render_action' ][ 'render_slug' ] ?? ''
			);
			$this->assertSame(
				$subjectDefinition[ 'render_nav' ],
				$subjectsByKey[ $enabledKey ][ 'render_action' ][ Constants::NAV_ID ] ?? ''
			);
			$this->assertSame(
				$subjectDefinition[ 'render_subnav' ],
				$subjectsByKey[ $enabledKey ][ 'render_action' ][ Constants::NAV_SUB_ID ] ?? ''
			);
		}

		foreach ( $subjectDefinitions as $subjectKey => $subjectDefinition ) {
			$this->assertSame(
				$subjectKey === 'live_traffic',
				(bool)( $subjectsByKey[ $subjectKey ][ 'is_live' ] ?? false )
			);
			$this->assertSame(
				(string)( $subjectDefinition[ 'icon_class' ] ?? '' ),
				$subjectsByKey[ $subjectKey ][ 'subject_icon' ] ?? ''
			);
			$this->assertSame(
				(string)( $subjectDefinition[ 'stat_text' ] ?? '' ),
				$subjectsByKey[ $subjectKey ][ 'subject_meta' ] ?? ''
			);
		}

		$this->assertFalse( $subjectsByKey[ 'premium_integrations' ][ 'is_enabled' ] );
		$this->assertTrue( $subjectsByKey[ 'premium_integrations' ][ 'is_disabled' ] );
		$this->assertFalse( $subjectsByKey[ 'premium_integrations' ][ 'is_loaded' ] );
		$this->assertSame( [], $subjectsByKey[ 'premium_integrations' ][ 'render_action' ] );
		$this->assertSame( '', $subjectsByKey[ 'premium_integrations' ][ 'panel_body' ] );
	}

	public function test_mode_shell_contract_is_exposed_in_render_data() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$renderData = $this->invokeProtectedMethod( $page, 'getRenderData' );
		$subjectDefinitions = PluginNavs::investigateLandingSubjectDefinitions();

		$this->assertSame( 'investigate', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 'info', $renderData[ 'vars' ][ 'mode_shell' ][ 'accent_status' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] ?? false ) );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? false ) );

		$this->assertCount( \count( $subjectDefinitions ), $renderData[ 'vars' ][ 'mode_tiles' ] ?? [] );
		$this->assertSame( '', $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? 'missing' );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_panel' ][ 'is_open' ] ?? true ) );
		$batchRenderAction = (array)( $renderData[ 'vars' ][ 'batch_render_action' ] ?? [] );
		$this->assertSame( ActionData::FIELD_SHIELD, $batchRenderAction[ ActionData::FIELD_ACTION ] ?? '' );
		$this->assertSame( AjaxBatchRequests::SLUG, $batchRenderAction[ ActionData::FIELD_EXECUTE ] ?? '' );
		$this->assertArrayHasKey( ActionData::FIELD_NONCE, $batchRenderAction );
		$this->assertNotSame( '', (string)( $renderData[ 'strings' ][ 'landing_hint' ] ?? '' ) );
	}

	public function test_active_panel_context_is_derived_from_subject_and_lookup_action_data() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$page->action_data = [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.99',
		];
		$ipDefinition = PluginNavs::investigateLandingSubjectDefinitions()[ 'ip' ];

		$renderData = $this->invokeProtectedMethod( $page, 'getRenderData' );
		$this->assertSame( 'ip', $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? '' );

		$subjects = $renderData[ 'vars' ][ 'subjects' ] ?? [];
		$subjectsByKey = [];
		foreach ( $subjects as $subject ) {
			$subjectsByKey[ $subject[ 'key' ] ] = $subject;
		}

		$this->assertTrue( $subjectsByKey[ 'ip' ][ 'is_loaded' ] ?? false );
		$this->assertSame( '203.0.113.99', $subjectsByKey[ 'ip' ][ 'subject_title' ] ?? '' );
		$this->assertSame( (string)$ipDefinition[ 'icon_class' ], $subjectsByKey[ 'ip' ][ 'subject_icon' ] ?? '' );
		$this->assertSame( (string)$ipDefinition[ 'stat_text' ], $subjectsByKey[ 'ip' ][ 'subject_meta' ] ?? '' );
		$this->assertNotSame( '', \trim( (string)( $subjectsByKey[ 'ip' ][ 'panel_body' ] ?? '' ) ) );
		foreach ( [ 'user', 'plugin', 'theme', 'core', 'live_traffic' ] as $key ) {
			$this->assertFalse( $subjectsByKey[ $key ][ 'is_loaded' ] ?? true );
		}
		$this->assertCount( 1, $this->renderCapture->calls );
		$this->assertSame( $ipDefinition[ 'render_action' ], $this->renderCapture->calls[ 0 ][ 'action' ] ?? '' );
		$this->assertSame( $ipDefinition[ 'render_nav' ], $this->renderCapture->calls[ 0 ][ 'action_data' ][ Constants::NAV_ID ] ?? '' );
		$this->assertSame( $ipDefinition[ 'render_subnav' ], $this->renderCapture->calls[ 0 ][ 'action_data' ][ Constants::NAV_SUB_ID ] ?? '' );
		$this->assertSame( '203.0.113.99', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'analyse_ip' ] ?? '' );
	}

	public function test_active_subject_falls_back_to_lookup_when_subject_key_is_invalid() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$page->action_data = [
			'subject'      => 'invalid',
			'plugin_slug'  => 'hello-dolly/hello.php',
		];
		$pluginDefinition = PluginNavs::investigateLandingSubjectDefinitions()[ 'plugin' ];

		$renderData = $this->invokeProtectedMethod( $page, 'getRenderData' );
		$this->assertSame( 'plugin', $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? '' );

		$subjects = [];
		foreach ( (array)( $renderData[ 'vars' ][ 'subjects' ] ?? [] ) as $subject ) {
			if ( \is_array( $subject ) && isset( $subject[ 'key' ] ) ) {
				$subjects[ $subject[ 'key' ] ] = $subject;
			}
		}
		$this->assertTrue( (bool)( $subjects[ 'plugin' ][ 'is_loaded' ] ?? false ) );
		$this->assertSame( 'hello-dolly/hello.php', $subjects[ 'plugin' ][ 'subject_title' ] ?? '' );
		$this->assertCount( 1, $this->renderCapture->calls );
		$this->assertSame( $pluginDefinition[ 'render_action' ], $this->renderCapture->calls[ 0 ][ 'action' ] ?? '' );
		$this->assertSame( $pluginDefinition[ 'render_nav' ], $this->renderCapture->calls[ 0 ][ 'action_data' ][ Constants::NAV_ID ] ?? '' );
		$this->assertSame( $pluginDefinition[ 'render_subnav' ], $this->renderCapture->calls[ 0 ][ 'action_data' ][ Constants::NAV_SUB_ID ] ?? '' );
		$this->assertSame( 'hello-dolly/hello.php', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'plugin_slug' ] ?? '' );
	}

	public function test_subject_panel_payload_is_cached_per_instance() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$this->invokeProtectedMethod( $page, 'getLandingVars' );
		$this->invokeProtectedMethod( $page, 'getLandingTiles' );
		$this->invokeProtectedMethod( $page, 'getLandingVars' );

		$this->assertCount( 0, $this->renderCapture->calls );
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
