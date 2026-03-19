<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

if ( !\function_exists( __NAMESPACE__.'\\wp_hash' ) ) {
	function wp_hash( $data, $scheme = 'auth', $algo = 'md5' ) {
		return \md5( (string)$data.'|'.$scheme.'|'.$algo );
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ConfigureDrillDownDiagnosis,
	ConfigureDrillDownEditor,
	PageConfigureLanding
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestUsers
};

class PageConfigureLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \trim( $text ) : ''
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
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [] ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		] );
		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_landing_vars_expose_drill_shell_and_ajax_contracts() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertArrayNotHasKey( 'zone_tiles', $vars );
		$this->assertArrayNotHasKey( 'rail', $vars );
		$this->assertArrayNotHasKey( 'configure_render_action', $vars );
		$this->assertSame( 'configure_drill_shell', $vars[ 'drill_shell' ][ 'id' ] ?? '' );
		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( [ 'zones', 'diagnosis', 'editor' ], \array_column( $vars[ 'drill_shell' ][ 'layers' ] ?? [], 'key' ) );
		$this->assertSame( 'ZONES_HTML', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'body' ] ?? '' );
		$this->assertSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? 'missing' );
		$this->assertSame( 'Back to Configure', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'compact_back_label' ] ?? '' );
		$this->assertSame( 'Review findings', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( 'configure', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? true ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'mode_tiles' ] ?? [ 'unexpected' ] );
		$this->assertSame( 78, $vars[ 'configure_posture_strip' ][ 'meter' ][ 'percentage' ] ?? 0 );
		$this->assertStringContainsString( '78', (string)( $vars[ 'configure_posture_strip' ][ 'summary' ] ?? '' ) );
		$this->assertSame(
			ConfigureDrillDownDiagnosis::SLUG,
			$vars[ 'configure_ajax' ][ 'diagnosis_render_action' ][ 'render_slug' ] ?? ''
		);
		$this->assertSame(
			ConfigureDrillDownEditor::SLUG,
			$vars[ 'configure_ajax' ][ 'editor_render_action' ][ 'render_slug' ] ?? ''
		);
		$this->assertSame(
			PluginNavs::NAV_ZONES,
			$vars[ 'configure_ajax' ][ 'diagnosis_render_action' ][ Constants::NAV_ID ] ?? ''
		);
		$this->assertSame(
			PluginNavs::SUBNAV_ZONES_OVERVIEW,
			$vars[ 'configure_ajax' ][ 'editor_render_action' ][ Constants::NAV_SUB_ID ] ?? ''
		);
	}

	public function test_valid_zone_deep_link_preloads_diagnosis_layer() :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [ 'zone' => 'login' ] ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		] );
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( 1, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( 'DIAGNOSIS:login', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' );
		$this->assertSame( 'Login', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( 'Back to Configure', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'active_back_label' ] ?? '' );
	}

	public function test_invalid_zone_deep_link_falls_back_to_zone_layer() :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [ 'zone' => 'login_protection' ] ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		] );
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( 'Back to Configure', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'compact_back_label' ] ?? '' );
		$this->assertSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? 'missing' );
	}

	public function test_zone_sections_group_attention_first_and_general_last() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$sections = $this->invokeNonPublicMethod( $page, 'getConfigureZoneSections' );

		$this->assertSame(
			[ 'secadmin', 'login' ],
			\array_column( $sections[ 0 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertFalse( (bool)( $sections[ 0 ][ 'collapsible' ] ?? true ) );
		$this->assertSame(
			[ 'firewall', 'general' ],
			\array_column( $sections[ 1 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertTrue( (bool)( $sections[ 1 ][ 'collapsible' ] ?? false ) );
	}

	private function zoneTileFixtures() :array {
		return [
			$this->buildZoneTileFixture(
				'secadmin',
				'Security Admin',
				'critical',
				'Critical',
				'1 critical component',
				[
					$this->buildZoneComponentFixture(
						'PIN Protection',
						'critical',
						'Issue',
						'PIN is not configured.',
						[ 'Set a PIN before more admins are added.' ]
					),
				]
			),
			$this->buildZoneTileFixture(
				'firewall',
				'Firewall',
				'good',
				'Good',
				'All components healthy',
				[
					$this->buildZoneComponentFixture(
						'WAF Rules',
						'good',
						'Active',
						'Firewall rules are active.'
					),
				]
			),
			$this->buildZoneTileFixture(
				'login',
				'Login',
				'warning',
				'Needs Work',
				'1 component needs work',
				[
					$this->buildZoneComponentFixture(
						'2FA',
						'warning',
						'Needs Work',
						'2FA requires review.',
						[ 'Require 2FA for administrators.' ]
					),
				]
			),
			$this->buildZoneTileFixture(
				'general',
				'General',
				'neutral',
				'General',
				'General settings',
				[
					$this->buildZoneComponentFixture(
						'Traffic Logging',
						'neutral',
						'General',
						'General settings'
					),
				],
				false
			),
		];
	}

	private function buildZoneTileFixture(
		string $key,
		string $label,
		string $status,
		string $statusLabel,
		string $statLine,
		array $components,
		bool $includeInPosture = true
	) :array {
		return [
			'key'               => $key,
			'panel_target'      => $key,
			'is_enabled'        => true,
			'is_disabled'       => false,
			'include_in_posture' => $includeInPosture,
			'label'             => $label,
			'icon_class'        => 'bi bi-gear',
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => 'bi bi-shield-check',
			'stat_line'         => $statLine,
			'settings_href'     => '/admin/'.$key,
			'settings_label'    => 'Configure '.$label.' Settings',
			'settings_action'   => [
				'href'    => '/admin/'.$key,
				'title'   => 'Configure '.$label,
				'icon'    => 'bi bi-gear-fill',
				'tooltip' => '',
				'classes' => [ 'zone_component_action' ],
				'data'    => [
					'zone_component_action' => 'offcanvas_zone_component_config',
					'zone_component_slug'   => $key.'_component',
					'form_context'          => 'offcanvas',
				],
			],
			'panel'             => [
				'title'        => $label,
				'status'       => $status,
				'status_label' => $statusLabel,
				'components'   => $components,
			],
		];
	}

	private function buildZoneComponentFixture(
		string $title,
		string $status,
		string $statusLabel,
		string $note,
		array $explanations = []
	) :array {
		return [
			'title'             => $title,
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => 'bi bi-exclamation-triangle-fill',
			'note'              => $note,
			'explanations'      => $explanations,
			'config_action'     => [
				'title'   => 'Configure '.$title,
				'href'    => 'javascript:{}',
				'icon'    => 'bi bi-gear-fill',
				'tooltip' => '',
				'classes' => [ 'zone_component_action' ],
				'data'    => [
					'zone_component_action' => 'offcanvas_zone_component_config',
					'zone_component_slug'   => \strtolower( \str_replace( ' ', '_', $title ) ),
					'form_context'          => 'offcanvas',
				],
			],
		];
	}

	private function zonePostureFixture( int $percentage ) :array {
		return [
			'components' => [],
			'signals'    => [],
			'totals'     => [
				'score'        => $percentage,
				'max_weight'   => 100,
				'percentage'   => $percentage,
				'letter_score' => 'B',
			],
			'percentage' => $percentage,
			'severity'   => 'warning',
			'status'     => 'warning',
		];
	}
}

class PageConfigureLandingUnitTestDouble extends PageConfigureLanding {

	private array $zonePostureFixture;

	private array $zoneTileFixtures;

	public function __construct( array $zonePostureFixture, array $zoneTileFixtures ) {
		$this->zonePostureFixture = $zonePostureFixture;
		$this->zoneTileFixtures = $zoneTileFixtures;
	}

	protected function getZonePosture() :array {
		return $this->zonePostureFixture;
	}

	protected function getConfigureZoneTiles() :array {
		return $this->zoneTileFixtures;
	}

	protected function renderConfigureZonesLayer() :string {
		return 'ZONES_HTML';
	}

	protected function renderConfigureDiagnosisLayer( string $zoneKey ) :string {
		return 'DIAGNOSIS:'.$zoneKey;
	}

	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return \array_merge(
			[
				'action'      => 'shield_action',
				'ex'          => 'ajax_render',
				'render_slug' => $renderAction::SLUG,
			],
			$auxData
		);
	}
}
