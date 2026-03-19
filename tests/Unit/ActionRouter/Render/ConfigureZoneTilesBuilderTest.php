<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\SecurityZonesCon;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone;

class ConfigureZoneTilesBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'esc_attr' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text )
				? ( \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) ) ?? '' )
				: ''
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_returns_expected_tile_contract() :void {
		$tiles = ( new ConfigureZoneTilesBuilder() )->build();
		$this->assertCount( 9, $tiles );

		$this->assertSame(
			\array_column( PluginNavs::configureLandingTileDefinitions(), 'key' ),
			\array_column( $tiles, 'key' )
		);

		$this->assertSame(
			[
				'secadmin',
				'firewall',
				'ips',
				'scans',
				'login',
				'users',
				'spam',
				'headers',
				'general',
			],
			\array_column( $tiles, 'key' )
		);

		$tilesByKey = [];
		foreach ( $tiles as $tile ) {
			$tilesByKey[ $tile[ 'key' ] ] = $tile;
			$this->assertSame( $tile[ 'key' ], $tile[ 'panel_target' ] );
			$this->assertSame( !$tile[ 'is_enabled' ], $tile[ 'is_disabled' ] );
			$this->assertSame( 'bi bi-', \substr( $tile[ 'icon_class' ], 0, 6 ) );
			$this->assertSame( 'bi bi-', \substr( $tile[ 'status_icon_class' ], 0, 6 ) );
			$this->assertSame( $tile[ 'include_in_posture' ], $tile[ 'key' ] !== 'general' );
		}

		$this->assertSame( 'good', $tilesByKey[ 'secadmin' ][ 'status' ] );
		$this->assertSame( 'All components healthy', $tilesByKey[ 'secadmin' ][ 'stat_line' ] );
		$this->assertSame( 'zone_component_action', $tilesByKey[ 'secadmin' ][ 'settings_action' ][ 'classes' ][ 0 ] ?? '' );
		$this->assertSame( 'offcanvas_zone_component_config', $tilesByKey[ 'secadmin' ][ 'settings_action' ][ 'data' ][ 'zone_component_action' ] ?? '' );
		$this->assertSame( 'offcanvas', $tilesByKey[ 'secadmin' ][ 'settings_action' ][ 'data' ][ 'form_context' ] ?? '' );
		$this->assertSame( '5', $tilesByKey[ 'secadmin' ][ 'settings_action' ][ 'data' ][ 'retry-count' ] ?? '' );
		$this->assertArrayNotHasKey( '', $tilesByKey[ 'secadmin' ][ 'settings_action' ][ 'data' ] ?? [] );

		$this->assertSame( 'warning', $tilesByKey[ 'login' ][ 'status' ] );
		$this->assertSame( '1 component needs work', $tilesByKey[ 'login' ][ 'stat_line' ] );

		$this->assertSame( 'critical', $tilesByKey[ 'spam' ][ 'status' ] );
		$this->assertSame( '1 critical, 1 need work', $tilesByKey[ 'spam' ][ 'stat_line' ] );
		$this->assertCount( 2, $tilesByKey[ 'spam' ][ 'panel' ][ 'components' ] );

		$this->assertSame( 'neutral', $tilesByKey[ 'general' ][ 'status' ] );
		$this->assertSame( 'General settings', $tilesByKey[ 'general' ][ 'stat_line' ] );
		$this->assertSame( 'General', $tilesByKey[ 'general' ][ 'status_label' ] );
		$this->assertSame( 'bi bi-info-circle-fill', $tilesByKey[ 'general' ][ 'status_icon_class' ] );
		$this->assertSame( '/admin/zone_components/plugin_general', $tilesByKey[ 'general' ][ 'settings_href' ] );
		$this->assertSame(
			'neutral',
			$tilesByKey[ 'general' ][ 'panel' ][ 'components' ][ 0 ][ 'status' ]
		);
		$this->assertNotEmpty( $tilesByKey[ 'general' ][ 'panel' ][ 'components' ][ 0 ][ 'config_action' ] );
		$this->assertSame( 'offcanvas', $tilesByKey[ 'general' ][ 'panel' ][ 'components' ][ 0 ][ 'config_action' ][ 'data' ][ 'form_context' ] ?? '' );
		$this->assertSame( 'toggle', $tilesByKey[ 'secadmin' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'type' ] );
		$this->assertSame( 'pin_toggle', $tilesByKey[ 'secadmin' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'option_key' ] );
		$this->assertTrue( $tilesByKey[ 'secadmin' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'value' ] );
		$this->assertSame( 'select', $tilesByKey[ 'login' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'type' ] );
		$this->assertSame( 'login_action', $tilesByKey[ 'login' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'option_key' ] );
		$this->assertSame(
			[
				[
					'key'         => 'log',
					'label'       => 'Log Only',
					'is_disabled' => false,
				],
				[
					'key'         => 'block',
					'label'       => 'Block',
					'is_disabled' => false,
				],
			],
			$tilesByKey[ 'login' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'options' ]
		);
		$this->assertSame(
			'request_log_enabled',
			$tilesByKey[ 'general' ][ 'panel' ][ 'components' ][ 2 ][ 'inline_control' ][ 'option_key' ]
		);
		$this->assertSame( 'toggle', $tilesByKey[ 'general' ][ 'panel' ][ 'components' ][ 2 ][ 'inline_control' ][ 'type' ] );
		$this->assertSame( 'select', $tilesByKey[ 'headers' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'type' ] );
		$this->assertTrue( $tilesByKey[ 'headers' ][ 'panel' ][ 'components' ][ 0 ][ 'inline_control' ][ 'is_disabled' ] );
	}

	private function installControllerStub() :void {
		$secadminZone = $this->newZone( 'module_secadmin' );
		$firewallZone = $this->newZone( 'module_firewall' );
		$ipsZone = $this->newZone( 'module_ips' );
		$scansZone = $this->newZone( 'module_scans' );
		$loginZone = $this->newZone( 'module_login' );
		$usersZone = $this->newZone( 'module_users' );
		$spamZone = $this->newZone( 'module_spam' );
		$headersZone = $this->newZone( 'module_headers' );

		/** @var SecurityZonesCon $zonesCon */
		$zonesCon = new class(
			[
				Zone\Secadmin::Slug() => $secadminZone,
				Zone\Firewall::Slug() => $firewallZone,
				Zone\Ips::Slug()      => $ipsZone,
				Zone\Scans::Slug()    => $scansZone,
				Zone\Login::Slug()    => $loginZone,
				Zone\Users::Slug()    => $usersZone,
				Zone\Spam::Slug()     => $spamZone,
				Zone\Headers::Slug()  => $headersZone,
			],
			[
				\spl_object_id( $secadminZone ) => [
					$this->newComponent( 'PIN Protection', EnumEnabledStatus::GOOD, 'PIN subtitle', [ 'PIN is active.' ], [
						'pin_toggle',
					] ),
				],
				\spl_object_id( $firewallZone ) => [
					$this->newComponent( 'WAF Rules', EnumEnabledStatus::NEUTRAL, 'WAF subtitle', [ 'WAF rules need review.' ] ),
				],
				\spl_object_id( $ipsZone )      => [
					$this->newComponent( 'IP Blocking', EnumEnabledStatus::GOOD, 'IP subtitle', [ 'IP blocking is active.' ] ),
				],
				\spl_object_id( $scansZone )    => [
					$this->newComponent( 'Scan Schedule', EnumEnabledStatus::NEUTRAL_ENABLED, 'Scan subtitle', [ 'Scans are active.' ] ),
				],
				\spl_object_id( $loginZone )    => [
					$this->newComponent( '2FA Enforcement', EnumEnabledStatus::OKAY, '2FA subtitle', [ '2FA is not enforced.' ], [
						'login_action',
					] ),
				],
				\spl_object_id( $usersZone )    => [
					$this->newComponent( 'Inactive Users', EnumEnabledStatus::OKAY, 'Inactive user policy', [ 'Suspension policy needs review.' ] ),
				],
				\spl_object_id( $spamZone )     => [
					$this->newComponent( 'Bot SPAM Blocking', EnumEnabledStatus::BAD, 'Bot subtitle', [ 'Bot SPAM blocking is disabled.' ] ),
					$this->newComponent( 'Human SPAM Filtering', EnumEnabledStatus::OKAY, 'Human subtitle', [ 'Human SPAM filtering needs setup.' ] ),
				],
				\spl_object_id( $headersZone )  => [
					$this->newComponent( 'CSP Headers', EnumEnabledStatus::GOOD, 'CSP subtitle', [ 'CSP is active.' ], [
						'headers_policy_mode',
					] ),
				],
			],
			[
				'plugin_general'                  => $this->newComponent(
					'General Plugin Configuration',
					EnumEnabledStatus::GOOD,
					'General plugin settings.',
					[ 'General configuration is active.' ]
				),
				Component\ActivityLogging::Slug() => $this->newComponent(
					'WordPress Activity Logging',
					EnumEnabledStatus::GOOD,
					'Activity logging subtitle',
					[ 'Activity logging is enabled.' ]
				),
				Component\RequestLogging::Slug()  => $this->newComponent(
					'Request Logging',
					EnumEnabledStatus::OKAY,
					'Request logging note from subtitle.',
					[],
					[
						'request_log_paths',
						'request_log_enabled',
					]
				),
			]
		) extends SecurityZonesCon {
			private array $zonesBySlug;
			private array $componentsByZoneObjectId;
			private array $componentsBySlug;

			public function __construct( array $zonesBySlug, array $componentsByZoneObjectId, array $componentsBySlug ) {
				$this->zonesBySlug = $zonesBySlug;
				$this->componentsByZoneObjectId = $componentsByZoneObjectId;
				$this->componentsBySlug = $componentsBySlug;
			}

			public function getZone( string $slug ) :Zone\Base {
				return $this->zonesBySlug[ $slug ];
			}

			public function getZoneComponent( string $slug ) :Component\Base {
				return $this->componentsBySlug[ $slug ];
			}

			public function getComponentsForZone( Zone\Base $zone ) :array {
				return $this->componentsByZoneObjectId[ \spl_object_id( $zone ) ] ?? [];
			}
		};

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function zone( string $zoneSlug ) :string {
				return '/admin/zones/'.$zoneSlug;
			}

			public function cfgForZoneComponent( string $componentSlug ) :string {
				return '/admin/zone_components/'.$componentSlug;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->labels = new class {
			public bool $is_whitelabelled = false;
			public string $Name = 'Shield';

			public function getBrandName( string $brand ) :string {
				return $brand;
			}
		};
		$controller->caps = new class {
			public function hasCap( string $cap ) :bool {
				return true;
			}
		};
		$controller->cfg = (object)[
			'configuration' => new class {
				public array $options;
				public array $sections;

				public function __construct() {
					$this->options = [
						'pin_toggle' => [
							'key'     => 'pin_toggle',
							'section' => 'section_security_admin_settings',
							'type'    => 'checkbox',
							'default' => 'N',
						],
						'login_action' => [
							'key'           => 'login_action',
							'section'       => 'section_brute_force_login_protection',
							'type'          => 'select',
							'default'       => 'log',
							'value_options' => [
								[
									'value_key' => 'log',
									'text'      => 'Log Only',
								],
								[
									'value_key' => 'block',
									'text'      => 'Block',
								],
							],
						],
						'request_log_paths' => [
							'key'     => 'request_log_paths',
							'section' => 'section_log_requests',
							'type'    => 'array',
							'default' => [],
						],
						'request_log_enabled' => [
							'key'     => 'request_log_enabled',
							'section' => 'section_log_requests',
							'type'    => 'checkbox',
							'default' => 'N',
						],
						'headers_policy_mode' => [
							'key'           => 'headers_policy_mode',
							'section'       => 'section_security_headers',
							'type'          => 'select',
							'default'       => 'report',
							'premium'       => true,
							'value_options' => [
								[
									'value_key' => 'report',
									'text'      => 'Report Only',
								],
								[
									'value_key' => 'enforce',
									'text'      => 'Enforce',
								],
							],
						],
					];
					$this->sections = [
						[ 'slug' => 'section_security_admin_settings' ],
						[ 'slug' => 'section_brute_force_login_protection' ],
						[ 'slug' => 'section_log_requests' ],
						[ 'slug' => 'section_security_headers' ],
					];
				}

				public function optsForSection( string $section ) :array {
					return \array_values( \array_filter(
						$this->options,
						fn( array $opt ) :bool => $opt[ 'section' ] === $section
					) );
				}
			},
		];
		$controller->opts = new class {
			private array $values = [
				'pin_toggle'          => 'Y',
				'login_action'        => 'log',
				'request_log_paths'   => [],
				'request_log_enabled' => 'Y',
				'headers_policy_mode' => 'report',
			];
			private array $defs = [
				'pin_toggle'          => [ 'section' => 'section_security_admin_settings', 'type' => 'checkbox' ],
				'login_action'        => [ 'section' => 'section_brute_force_login_protection', 'type' => 'select' ],
				'request_log_paths'   => [ 'section' => 'section_log_requests', 'type' => 'array' ],
				'request_log_enabled' => [ 'section' => 'section_log_requests', 'type' => 'checkbox' ],
				'headers_policy_mode' => [ 'section' => 'section_security_headers', 'type' => 'select' ],
			];

			public function optGet( string $key ) {
				return $this->values[ $key ] ?? null;
			}

			public function optHasAccess( string $key ) :bool {
				return $key !== 'headers_policy_mode';
			}

			public function optDef( string $key ) :array {
				return $this->defs[ $key ] ?? [];
			}
		};
		$controller->comps = (object)[
			'zones'       => $zonesCon,
			'opts_lookup' => new class {
				public function getFirewallParametersWhitelist() :array {
					return [];
				}
			},
			'license'     => new class {
				public function hasValidWorkingLicense() :bool {
					return false;
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}

	private function newZone( string $moduleSlug ) :Zone\Base {
		return new class( $moduleSlug ) extends Zone\Base {
			private string $moduleSlug;

			public function __construct( string $moduleSlug ) {
				$this->moduleSlug = $moduleSlug;
			}

			public function getAction_Config() :?array {
				return [
					'title'   => 'Config',
					'data'    => [
						'zone_component_action' => 'offcanvas_zone_component_config',
						'zone_component_slug'   => $this->moduleSlug,
						'Retry-Count'           => 5,
						''                      => 'drop-me',
					],
					'icon'    => 'bi bi-gear',
					'classes' => [
						'list-group-item-primary',
						'zone_component_action',
					],
				];
			}
		};
	}

	private function newComponent(
		string $title,
		string $enabledStatus,
		string $subtitle,
		array $explanation,
		array $options = []
	) :Component\Base {
		return new class( $title, $enabledStatus, $subtitle, $explanation, $options ) extends Component\Base {
			private string $localTitle;
			private string $localEnabledStatus;
			private string $localSubtitle;
			private array $localExplanation;
			private array $localOptions;

			public function __construct( string $title, string $enabledStatus, string $subtitle, array $explanation, array $options ) {
				$this->localTitle = $title;
				$this->localEnabledStatus = $enabledStatus;
				$this->localSubtitle = $subtitle;
				$this->localExplanation = $explanation;
				$this->localOptions = $options;
			}

			public function title() :string {
				return $this->localTitle;
			}

			public function subtitle() :string {
				return $this->localSubtitle;
			}

			public function enabledStatus() :string {
				return $this->localEnabledStatus;
			}

			public function explanation() :array {
				return $this->localExplanation;
			}

			public function getOptions() :array {
				return $this->localOptions;
			}
		};
	}
}
