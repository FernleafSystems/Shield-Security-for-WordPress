<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateLanding;
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
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestUsers
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

	public function test_subject_tiles_expose_drill_selection_contract() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$tiles = $this->invokeNonPublicMethod( $page, 'getSubjectTiles' );
		$subjectDefinitions = PluginNavs::investigateLandingSubjectDefinitions();

		$this->assertCount( \count( $subjectDefinitions ), $tiles );
		$this->assertSame(
			\array_keys( $subjectDefinitions ),
			\array_values( \array_map( static fn( array $tile ) :string => $tile[ 'key' ], $tiles ) )
		);

		$tilesByKey = [];
		foreach ( $tiles as $tile ) {
			$tilesByKey[ $tile[ 'key' ] ] = $tile;
			foreach ( [
				'key',
				'is_enabled',
				'is_disabled',
				'is_pro',
				'is_live',
				'is_live_attr',
				'title',
				'icon_class',
				'status',
				'stat_text',
				'lookup_key',
				'render_action',
				'render_action_json',
				'header',
				'header_json',
			] as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $tile );
			}

			$this->assertArrayNotHasKey( 'panel_target', $tile );
			$this->assertArrayNotHasKey( 'panel_body', $tile );
			$this->assertArrayNotHasKey( 'subject_title', $tile );
			$this->assertArrayNotHasKey( 'render_subnav', $tile );
			$this->assertSame( !(bool)$tile[ 'is_enabled' ], (bool)$tile[ 'is_disabled' ] );
		}

		$this->assertSame( '1', $tilesByKey[ 'live_traffic' ][ 'is_live_attr' ] ?? '0' );
		$this->assertTrue( (bool)( $tilesByKey[ 'live_traffic' ][ 'is_live' ] ?? false ) );
		$this->assertFalse( $tilesByKey[ 'premium_integrations' ][ 'is_enabled' ] );
		$this->assertTrue( $tilesByKey[ 'premium_integrations' ][ 'is_disabled' ] );
		$this->assertSame( [], $tilesByKey[ 'premium_integrations' ][ 'render_action' ] );
		$this->assertSame( '[]', $tilesByKey[ 'premium_integrations' ][ 'render_action_json' ] );
		$this->assertSame( PluginNavs::NAV_ACTIVITY, $tilesByKey[ 'ip' ][ 'render_action' ][ Constants::NAV_ID ] ?? '' );
		$this->assertSame(
			PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			$tilesByKey[ 'ip' ][ 'render_action' ][ Constants::NAV_SUB_ID ] ?? ''
		);
		$this->assertSame( 'info', $tilesByKey[ 'ip' ][ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame(
			[
				'compact_back_label' => 'Back to IP Address',
				'active_back_label'  => 'Back to Investigate',
				'breadcrumb_label'   => 'IP Address',
				'title'              => 'IP Address',
				'summary'            => 'Use the panel below to look up and explore.',
				'focus'              => $tilesByKey[ 'ip' ][ 'stat_text' ],
				'next_step'          => 'Use the panel tabs and actions to continue the investigation.',
				'icon_class'         => $tilesByKey[ 'ip' ][ 'icon_class' ],
				'badge'              => $tilesByKey[ 'ip' ][ 'stat_text' ],
				'badge_status'       => 'info',
				'color_key'          => 'investigate',
			],
			$tilesByKey[ 'ip' ][ 'header' ] ?? []
		);
	}

	public function test_landing_vars_expose_noninteractive_mode_shell_and_drill_shell() :void {
		$page = new PageInvestigateLandingUnitTestDouble();

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$idlePanel = $this->invokeNonPublicMethod( $page, 'buildPanelLayerData', [ '' ] );
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertArrayNotHasKey( 'subjects', $vars );
		$this->assertArrayNotHasKey( 'active_subject', $vars );
		$this->assertSame( 'investigate_drill_shell', $vars[ 'drill_shell' ][ 'id' ] ?? '' );
		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( [ 'subjects', 'panel' ], \array_column( $vars[ 'drill_shell' ][ 'layers' ] ?? [], 'key' ) );
		$this->assertSame( 'SUBJECTS_LAYER_HTML', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'body' ] ?? '' );
		$this->assertSame( 'PANEL_LAYER:', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' );
		$this->assertSame( 'Back to Investigate', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'compact_back_label' ] ?? '' );
		$this->assertSame( 'Investigation', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( '', $idlePanel[ 'render_action_json' ] ?? 'missing' );
		$this->assertSame( 'investigate', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? true ) );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'use_operator_chrome' ] ?? false ) );
		$this->assertSame( '/admin/home', $renderData[ 'vars' ][ 'mode_shell' ][ 'home_href' ] ?? '' );
		$this->assertSame( 'Investigate', $renderData[ 'vars' ][ 'mode_shell' ][ 'root_step' ][ 'title' ] ?? '' );
		$this->assertSame( 'investigate', $renderData[ 'vars' ][ 'mode_shell' ][ 'root_step' ][ 'color_key' ] ?? '' );
		$this->assertSame( [], $renderData[ 'vars' ][ 'mode_tiles' ] ?? [ 'unexpected' ] );
		$this->assertSame( '', $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? 'missing' );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_panel' ][ 'is_open' ] ?? true ) );
	}

	public function test_valid_deep_link_preloads_panel_layer_and_uses_lookup_action_data() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$page->action_data = [
			'subject'    => 'ip',
			'analyse_ip' => '203.0.113.99',
		];
		$ipDefinition = PluginNavs::investigateLandingSubjectDefinitions()[ 'ip' ];

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$panel = $this->invokeNonPublicMethod( $page, 'buildPanelLayerData', [ 'ip' ] );

		$this->assertSame( 1, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( 'IP Address', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( 'ip', $panel[ 'subject_key' ] ?? '' );
		$this->assertSame( '1', $panel[ 'is_loaded' ] ?? '0' );
		$this->assertSame( '0', $panel[ 'is_live' ] ?? '1' );
		$this->assertNotSame( '', \trim( (string)( $panel[ 'body' ] ?? '' ) ) );
		$this->assertCount( 1, $this->renderCapture->calls );
		$this->assertSame( $ipDefinition[ 'render_action' ], $this->renderCapture->calls[ 0 ][ 'action' ] ?? '' );
		$this->assertSame( $ipDefinition[ 'render_nav' ], $this->renderCapture->calls[ 0 ][ 'action_data' ][ Constants::NAV_ID ] ?? '' );
		$this->assertSame( $ipDefinition[ 'render_subnav' ], $this->renderCapture->calls[ 0 ][ 'action_data' ][ Constants::NAV_SUB_ID ] ?? '' );
		$this->assertSame( '203.0.113.99', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'analyse_ip' ] ?? '' );
	}

	public function test_invalid_subject_falls_back_to_lookup_subject() :void {
		$page = new PageInvestigateLandingUnitTestDouble();
		$page->action_data = [
			'subject'     => 'invalid',
			'plugin_slug' => 'hello-dolly/hello.php',
		];

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$panel = $this->invokeNonPublicMethod( $page, 'buildPanelLayerData', [ 'plugin' ] );

		$this->assertSame( 1, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( 'Plugin', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( 'plugin', $panel[ 'subject_key' ] ?? '' );
		$this->assertCount( 1, $this->renderCapture->calls );
		$this->assertSame( 'hello-dolly/hello.php', $this->renderCapture->calls[ 0 ][ 'action_data' ][ 'plugin_slug' ] ?? '' );
	}

	public function test_subject_tile_payload_is_cached_per_instance() :void {
		$page = new PageInvestigateLandingUnitTestDouble();

		$this->invokeNonPublicMethod( $page, 'getSubjectTiles' );
		$this->invokeNonPublicMethod( $page, 'getSubjectTiles' );
		$this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertCount( 0, $this->renderCapture->calls );
	}

	public function test_render_panel_layer_keeps_the_shared_panel_template_after_nested_render_work() :void {
		$fakeRender = new PageInvestigateLandingFakeRenderService();
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			new UnitTestActionRouter(),
			(object)[
				'comps' => (object)[
					'render' => $fakeRender,
				],
			]
		);

		$page = new PageInvestigateLandingRenderPanelOrderUnitTestDouble();
		$html = $this->invokeNonPublicMethod( $page, 'renderPanelLayer', [ 'ip' ] );

		$this->assertSame(
			'rendered:/wpadmin/components/investigate/layer_panel.twig:ip',
			$html
		);
	}

	private function installControllerStub() :void {
		$this->renderCapture = new RenderCapture();
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			new UnitTestActionRouter(
				$this->renderCapture,
				static function ( string $action, array $actionData ) :string {
					$lookupData = '';
					foreach ( [ 'user_lookup', 'analyse_ip', 'plugin_slug', 'theme_slug' ] as $lookupKey ) {
						if ( isset( $actionData[ $lookupKey ] ) && $actionData[ $lookupKey ] !== '' ) {
							$lookupData = ';'.$lookupKey.'='.$actionData[ $lookupKey ];
							break;
						}
					}

					return '<div class="inner-page-body-shell"><div>body-for:'.$action.$lookupData.'</div></div>';
				}
			)
		);
	}

	private function installServices( array $query = [] ) :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( $query ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers(),
		] );
	}
}

class PageInvestigateLandingUnitTestDouble extends PageInvestigateLanding {

	protected function renderSubjectsLayer() :string {
		return 'SUBJECTS_LAYER_HTML';
	}

	protected function renderPanelLayer( string $activeSubject ) :string {
		return 'PANEL_LAYER:'.$activeSubject;
	}

	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return \array_merge( [
			'render_slug' => $renderAction::SLUG,
		], $auxData );
	}
}

class PageInvestigateLandingRenderPanelOrderUnitTestDouble extends PageInvestigateLanding {

	protected function buildPanelLayerData( string $activeSubject ) :array {
		self::con()->comps->render->setTemplate( '/nested/template.twig' );

		return [
			'subject_key'        => $activeSubject,
			'is_loaded'          => '1',
			'is_live'            => '0',
			'render_action_json' => '{}',
			'body'               => '<div data-inner-page-body-shell="1"></div>',
		];
	}
}

class PageInvestigateLandingFakeRenderService {

	private string $template = '';

	private array $renderVars = [];

	public function setTemplate( string $template ) :self {
		$this->template = $template;
		return $this;
	}

	public function setData( array $vars ) :self {
		$this->renderVars = $vars;
		return $this;
	}

	public function render() :string {
		return sprintf(
			'rendered:%s:%s',
			$this->template,
			(string)( $this->renderVars[ 'panel' ][ 'subject_key' ] ?? '' )
		);
	}
}
