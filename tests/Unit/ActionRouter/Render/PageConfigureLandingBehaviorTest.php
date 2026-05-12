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
	ConfigureSearchResults,
	ConfigureZoneDiagnosisBuilder,
	ConfigureDrillDownDiagnosis,
	OperatorChromeContract,
	PageConfigureLanding,
	StatusDetailGroupsBuilder
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
	private object $secAdminController;

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
		$this->secAdminController = new class {
			public bool $enabled = true;

			public function isEnabledSecAdmin() :bool {
				return $this->enabled;
			}
		};
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			new class( $this->secAdminController ) {
				public object $comps;
				public object $cfg;

				public function __construct( object $secAdminController ) {
					$this->comps = (object)[
						'sec_admin' => $secAdminController,
						'zones'     => new class {
							public function enumZoneComponents() :array {
								return [
									'two_factor_general' => true,
									'traffic_logging' => true,
									'waf_rules'    => true,
								];
							}
						},
					];
					$this->cfg = (object)[
						'configuration' => (object)[
							'options' => [
								'mfa_verify_page' => [ 'section' => 'section_twofactor_auth' ],
								'request_log_enabled' => [ 'section' => 'section_log_requests' ],
							],
						],
					];
				}
			}
		);
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
		$diagnosisAction = \json_decode( (string)( $vars[ 'configure_ajax' ][ 'diagnosis_render_action_json' ] ?? '' ), true );
		$searchAction = \json_decode( (string)( $vars[ 'configure_ajax' ][ 'search_render_action_json' ] ?? '' ), true );

		$this->assertArrayNotHasKey( 'zone_tiles', $vars );
		$this->assertArrayNotHasKey( 'rail', $vars );
		$this->assertArrayNotHasKey( 'configure_render_action', $vars );
		$this->assertSame( 'configure_drill_shell', $vars[ 'drill_shell' ][ 'id' ] ?? '' );
		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( [ 'zones', 'diagnosis' ], \array_column( $vars[ 'drill_shell' ][ 'layers' ] ?? [], 'key' ) );
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'body' ] ?? '' );
		$this->assertSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? 'missing' );
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'compact_back_label' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( 'configure', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? true ) );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'use_operator_chrome' ] ?? false ) );
		$this->assertSame( '/admin/home', $renderData[ 'vars' ][ 'mode_shell' ][ 'home_href' ] ?? '' );
		$this->assertNotSame( '', $renderData[ 'vars' ][ 'mode_shell' ][ 'root_step' ][ 'title' ] ?? '' );
		$this->assertSame( '78%', $renderData[ 'vars' ][ 'mode_shell' ][ 'root_step' ][ 'badge' ] ?? '' );
		$this->assertSame( 'configure', $renderData[ 'vars' ][ 'mode_shell' ][ 'root_step' ][ 'color_key' ] ?? '' );
		$this->assertSame( [], $renderData[ 'vars' ][ 'mode_tiles' ] ?? [ 'unexpected' ] );
		$this->assertArrayNotHasKey( 'configure_posture_strip', $vars );
		$this->assertSame(
			ConfigureDrillDownDiagnosis::SLUG,
			$diagnosisAction[ 'render_slug' ] ?? ''
		);
		$this->assertSame(
			PluginNavs::NAV_ZONES,
			$diagnosisAction[ Constants::NAV_ID ] ?? ''
		);
		$this->assertSame(
			ConfigureSearchResults::SLUG,
			$searchAction[ 'render_slug' ] ?? ''
		);
		$this->assertSame( '', $vars[ 'configure_focus_request_json' ] ?? 'missing' );
	}

	public function test_landing_strings_put_search_guidance_in_the_placeholder() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$strings = $this->invokeNonPublicMethod( $page, 'getLandingStrings' );

		$this->assertArrayHasKey( 'search_placeholder', $strings );
		$this->assertNotSame( '', \trim( (string)( $strings[ 'search_placeholder' ] ?? '' ) ) );
		$this->assertArrayNotHasKey( 'search_hint', $strings );
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
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'breadcrumb_label' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'active_back_label' ] ?? '' );
	}

	public function test_secadmin_diagnosis_header_exposes_disable_context_action_when_enabled() :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [ 'zone' => 'secadmin' ] ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		] );
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$actions = $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'actions' ] ?? [];

		$this->assertCount( 1, $actions );
		$this->assertSame( 'deactivate', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'label' ] ?? '' );
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
		$this->assertNotSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'compact_back_label' ] ?? '' );
		$this->assertSame( '', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? 'missing' );
	}

	public function test_valid_focus_deep_link_is_normalized_into_landing_payload() :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [
				'zone'        => 'login',
				'row_key'     => 'two_factor_general',
				'config_item' => 'mfa_verify_page',
			] ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		] );
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$focus = \json_decode( (string)( $vars[ 'configure_focus_request_json' ] ?? '' ), true );

		$this->assertSame( [
			'row_key'     => 'two_factor_general',
			'config_item' => 'mfa_verify_page',
		], $focus );
	}

	public function test_invalid_row_key_is_rejected_from_focus_payload() :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [
				'zone'        => 'login',
				'row_key'     => 'missing_row',
				'config_item' => 'mfa_verify_page',
			] ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		] );
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( '', $vars[ 'configure_focus_request_json' ] ?? 'missing' );
	}

	public function test_invalid_config_item_is_cleared_when_not_owned_by_selected_row() :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [
				'zone'        => 'login',
				'row_key'     => 'two_factor_general',
				'config_item' => 'request_log_enabled',
			] ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		] );
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$focus = \json_decode( (string)( $vars[ 'configure_focus_request_json' ] ?? '' ), true );

		$this->assertSame( [
			'row_key'     => 'two_factor_general',
			'config_item' => '',
		], $focus );
	}

	public function test_zone_sections_split_critical_warning_general_and_healthy() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$sections = $this->invokeNonPublicMethod( $page, 'getConfigureZoneSections' );

		$this->assertSame(
			[ 'critical', 'warning', 'general', 'healthy' ],
			\array_column( $sections, 'key' )
		);
		$this->assertSame(
			[ 'secadmin' ],
			\array_column( $sections[ 0 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertSame(
			[ 'login' ],
			\array_column( $sections[ 1 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertSame(
			[ 'general' ],
			\array_column( $sections[ 2 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertSame(
			[ 'firewall' ],
			\array_column( $sections[ 3 ][ 'cards' ] ?? [], 'key' )
		);
	}

	public function test_landing_refresh_reuses_the_configure_root_step_contract() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->zonePostureFixture( 78 ), $this->zoneTileFixtures() );

		$refresh = $this->invokeNonPublicMethod( $page, 'buildConfigureLandingRefresh' );
		$rootStep = \json_decode( $refresh[ 'root_step_json' ] ?? '', true );

		$this->assertNotSame( '', $rootStep[ 'title' ] ?? '' );
		$this->assertSame( '78%', $rootStep[ 'badge' ] ?? '' );
		$this->assertSame( 'warning', $rootStep[ 'badge_status' ] ?? '' );
		$this->assertSame( 'configure', $rootStep[ 'color_key' ] ?? '' );
		$this->assertNotSame( '', $rootStep[ 'next_step' ] ?? '' );
	}

	private function zoneTileFixtures() :array {
		return [
			$this->buildZoneTileFixture(
				'secadmin',
				'Security Admin',
				'critical',
				'Critical',
				'Stable security admin summary.',
				'1 critical group',
				[
					$this->buildZoneRowFixture(
						'pin_protection',
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
				'Stable firewall summary.',
				'All groups healthy',
				[
					$this->buildZoneRowFixture(
						'waf_rules',
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
				'Stable login summary.',
				'1 group needs work',
				[
					$this->buildZoneRowFixture(
						'two_factor_general',
						'2FA General Settings',
						'warning',
						'Needs Work',
						'2FA requires review.',
						[ 'Require 2FA for administrators.' ],
						[
							'option_keys' => 'mfa_verify_page,allow_backupcodes',
							'config_item' => 'mfa_verify_page',
						]
					),
				]
			),
			$this->buildZoneTileFixture(
				'general',
				'General',
				'neutral',
				'General',
				'Stable general summary.',
				'General settings',
				[
					$this->buildZoneRowFixture(
						'traffic_logging',
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
		string $summary,
		string $statLine,
		array $rows,
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
			'summary'           => $summary,
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => 'bi bi-shield-check',
			'stat_line'         => $statLine,
			'panel'             => [
				'title'        => $label,
				'status'       => $status,
				'status_label' => $statusLabel,
				'rows'         => $rows,
				'detail_groups' => ( new StatusDetailGroupsBuilder() )
					->buildForConfigure( $rows ),
			],
		];
	}

	private function buildZoneRowFixture(
		string $key,
		string $title,
		string $status,
		string $statusLabel,
		string $note,
		array $explanations = [],
		array $actionData = []
	) :array {
		return [
			'key'               => $key,
			'title'             => $title,
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => 'bi bi-exclamation-triangle-fill',
			'note'              => $note,
			'explanations'      => $explanations,
			'config_action'     => [
				'label'     => 'Configure',
				'title'     => 'Configure '.$title,
				'href'      => '',
				'is_action' => true,
				'icon'      => 'bi bi-gear-fill',
				'tooltip'   => '',
				'classes'   => [ 'zone_component_action' ],
				'data'      => [
					'zone_component_action' => 'offcanvas_zone_component_config',
					'zone_component_slug'   => $key,
					'form_context'          => 'offcanvas',
				] + $actionData,
			],
		];
	}

	private function zonePostureFixture( int $percentage ) :array {
		return [
			'severity'   => 'warning',
			'percentage' => $percentage,
			'controls'   => [
				'total'    => 4,
				'good'     => 2,
				'warning'  => 1,
				'critical' => 1,
			],
			'zones'      => [
				'total'    => 3,
				'good'     => 1,
				'warning'  => 1,
				'critical' => 1,
			],
		];
	}
}

class PageConfigureLandingUnitTestDouble extends PageConfigureLanding {

	private array $configureLandingViewData;

	public function __construct( array $zonePostureFixture, array $zoneTileFixtures ) {
		$this->configureLandingViewData = $this->buildLandingViewDataFixture( $zonePostureFixture, $zoneTileFixtures );
	}

	protected function buildConfigureLandingViewData() :array {
		return $this->configureLandingViewData;
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

	private function buildLandingViewDataFixture( array $zonePostureFixture, array $zoneTileFixtures ) :array {
		$diagnosisBuilder = new ConfigureZoneDiagnosisBuilder();
		$tileLookup = [];
		$diagnoses = [];
		$percentage = (int)( $zonePostureFixture[ 'percentage' ] ?? 0 );
		$postureSummary = sprintf( '%d%% - 1 critical zone - 1 zone needs review - 1 zone ready', $percentage );

		foreach ( $zoneTileFixtures as $zoneTile ) {
			$tileLookup[ $zoneTile[ 'key' ] ] = $zoneTile;
			$diagnoses[ $zoneTile[ 'key' ] ] = $diagnosisBuilder->build( $zoneTile );
		}

		$rootStep = OperatorChromeContract::normalizeStep( [
			'breadcrumb_label' => 'Configure',
			'title'            => 'Configure',
			'summary'          => $postureSummary,
			'focus'            => 'Warning',
			'next_step'        => 'Open a zone to review findings and move into focused settings changes.',
			'icon_class'       => 'bi bi-gear',
			'badge'            => sprintf( '%d%%', $percentage ),
			'badge_status'     => 'warning',
			'color_key'        => 'configure',
		] );

		return [
			'tiles'           => $zoneTileFixtures,
			'tile_lookup'     => $tileLookup,
			'diagnoses'       => $diagnoses,
			'sections'        => [
				[
					'key'   => 'critical',
					'cards' => [
						$this->buildZoneCardFixture( $zoneTileFixtures[ 0 ], $diagnoses[ 'secadmin' ] ),
					],
				],
				[
					'key'   => 'warning',
					'cards' => [
						$this->buildZoneCardFixture( $zoneTileFixtures[ 2 ], $diagnoses[ 'login' ] ),
					],
				],
				[
					'key'   => 'general',
					'cards' => [
						$this->buildZoneCardFixture( $zoneTileFixtures[ 3 ], $diagnoses[ 'general' ] ),
					],
				],
				[
					'key'   => 'healthy',
					'cards' => [
						$this->buildZoneCardFixture( $zoneTileFixtures[ 1 ], $diagnoses[ 'firewall' ] ),
					],
				],
			],
			'posture_summary' => [
				'status'     => 'warning',
				'chip_label' => 'Warning',
				'icon_class' => 'bi bi-exclamation-circle-fill',
				'eyebrow'    => 'Configuration Coverage',
				'summary'    => $postureSummary,
				'meter'      => [
					'percentage'      => $percentage,
					'status'          => 'warning',
					'aria_label'      => 'Configuration Coverage',
					'aria_value_text' => sprintf( '%d%%', $percentage ),
				],
			],
			'root_step'       => $rootStep,
			'root_step_json'  => OperatorChromeContract::encodeJson( $rootStep ),
		];
	}

	private function buildZoneCardFixture( array $zoneTile, array $diagnosis ) :array {
		return [
			'key'            => $zoneTile[ 'key' ],
			'label'          => $zoneTile[ 'label' ],
			'icon_class'     => $zoneTile[ 'icon_class' ],
			'status'         => $zoneTile[ 'status' ],
			'status_label'   => $zoneTile[ 'status_label' ],
			'summary'        => $zoneTile[ 'summary' ],
			'selection_json' => $diagnosis[ 'zone_selection_json' ],
			'is_disabled'    => $zoneTile[ 'is_disabled' ],
		];
	}
}
