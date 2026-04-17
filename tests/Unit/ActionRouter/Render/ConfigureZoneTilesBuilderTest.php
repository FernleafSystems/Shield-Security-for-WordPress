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

	public function test_build_returns_expected_tile_contract_and_general_settings_rows() :void {
		$tiles = ( new ConfigureZoneTilesBuilder() )->build();
		$this->assertCount( 10, $tiles );
		$this->assertSame(
			\array_column( PluginNavs::configureLandingTileDefinitions(), 'key' ),
			\array_column( $tiles, 'key' )
		);

		$tilesByKey = [];
		foreach ( $tiles as $tile ) {
			$tilesByKey[ $tile[ 'key' ] ] = $tile;
			$this->assertArrayNotHasKey( 'settings_href', $tile );
			$this->assertArrayNotHasKey( 'settings_label', $tile );
			$this->assertArrayNotHasKey( 'settings_action', $tile );
			$this->assertSame( $tile[ 'key' ], $tile[ 'panel_target' ] );
			$this->assertSame( !$tile[ 'is_enabled' ], $tile[ 'is_disabled' ] );
			$this->assertSame( 'bi bi-', \substr( $tile[ 'icon_class' ], 0, 6 ) );
			$this->assertSame( 'bi bi-', \substr( $tile[ 'status_icon_class' ], 0, 6 ) );
			$this->assertNotSame( '', \trim( (string)( $tile[ 'summary' ] ?? '' ) ) );
			$this->assertSame(
				!\in_array( $tile[ 'key' ], [ 'general', 'reports_alerts' ], true ),
				$tile[ 'include_in_posture' ]
			);
		}

		$this->assertSame( 'good', $tilesByKey[ 'secadmin' ][ 'status' ] );
		$this->assertSame( 'All groups healthy', $tilesByKey[ 'secadmin' ][ 'stat_line' ] );
		$this->assertCount( 2, $tilesByKey[ 'secadmin' ][ 'panel' ][ 'rows' ] );
		$this->assertSame( 'PIN Protection', $tilesByKey[ 'secadmin' ][ 'panel' ][ 'rows' ][ 0 ][ 'title' ] ?? '' );
		$this->assertSame( 'pin_protection', $tilesByKey[ 'secadmin' ][ 'panel' ][ 'rows' ][ 0 ][ 'key' ] ?? '' );
		$secadminGeneral = $this->findRowByOptionKeys(
			$tilesByKey[ 'secadmin' ][ 'panel' ][ 'rows' ],
			'admin_access_restrict_plugins'
		);
		$this->assertSame( 'general_settings', $secadminGeneral[ 'key' ] ?? '' );
		$this->assertSame(
			'admin_access_restrict_plugins',
			$secadminGeneral[ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);
		$this->assertSame(
			'module_secadmin',
			$secadminGeneral[ 'config_action' ][ 'data' ][ 'zone_component_slug' ] ?? ''
		);
		$this->assertSame( 'neutral', $secadminGeneral[ 'status' ] ?? '' );

		$this->assertSame( 'warning', $tilesByKey[ 'login' ][ 'status' ] );
		$this->assertSame( '1 group needs work', $tilesByKey[ 'login' ][ 'stat_line' ] );
		$this->assertSame(
			[ '2FA General Settings', 'Email Authentication', 'OTP & Passkeys', 'Hide WP Login' ],
			\array_column( $tilesByKey[ 'login' ][ 'panel' ][ 'rows' ], 'title' )
		);
		$this->assertSame(
			[ 'two_factor_general', 'two_factor_email', 'two_factor_otp_passkeys', 'hide_wp_login' ],
			\array_column( $tilesByKey[ 'login' ][ 'panel' ][ 'rows' ], 'key' )
		);
		$this->assertSame( 'warning', $tilesByKey[ 'login' ][ 'panel' ][ 'rows' ][ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'neutral', $tilesByKey[ 'login' ][ 'panel' ][ 'rows' ][ 1 ][ 'status' ] ?? '' );
		$this->assertSame(
			'mfa_verify_page,allow_backupcodes',
			$tilesByKey[ 'login' ][ 'panel' ][ 'rows' ][ 0 ][ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);
		$this->assertSame(
			'enable_email_authentication,two_factor_auth_user_roles',
			$tilesByKey[ 'login' ][ 'panel' ][ 'rows' ][ 1 ][ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);
		$this->assertSame(
			'enable_google_authenticator,enable_passkeys',
			$tilesByKey[ 'login' ][ 'panel' ][ 'rows' ][ 2 ][ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);
		$this->assertSame(
			'rename_wplogin_path',
			$tilesByKey[ 'login' ][ 'panel' ][ 'rows' ][ 3 ][ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);

		$this->assertSame( 'critical', $tilesByKey[ 'spam' ][ 'status' ] );
		$this->assertSame( '1 critical group, 1 group needs work', $tilesByKey[ 'spam' ][ 'stat_line' ] );
		$this->assertCount( 4, $tilesByKey[ 'spam' ][ 'panel' ][ 'rows' ] );
		$this->assertSame(
			[ 'Bot SPAM Blocking', 'Human SPAM Filtering', 'Trusted Commenters' ],
			\array_column( \array_slice( $tilesByKey[ 'spam' ][ 'panel' ][ 'rows' ], 0, 3 ), 'title' )
		);
		$this->assertSame(
			[ 'bot_spam_blocking', 'human_spam_filtering', 'trusted_commenters' ],
			\array_column( \array_slice( $tilesByKey[ 'spam' ][ 'panel' ][ 'rows' ], 0, 3 ), 'key' )
		);
		$spamGeneral = $this->findRowByOptionKeys(
			$tilesByKey[ 'spam' ][ 'panel' ][ 'rows' ],
			'comments_cooldown'
		);
		$this->assertSame(
			'comments_cooldown',
			$spamGeneral[ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);
		$this->assertSame( 'neutral', $spamGeneral[ 'status' ] ?? '' );

		$this->assertSame( 'neutral', $tilesByKey[ 'general' ][ 'status' ] );
		$this->assertNotSame( '', $tilesByKey[ 'general' ][ 'stat_line' ] );
		$this->assertNotSame( '', $tilesByKey[ 'general' ][ 'status_label' ] );
		$this->assertCount( 2, $tilesByKey[ 'general' ][ 'panel' ][ 'rows' ] );

		$tileDefinitionsByKey = \array_column( PluginNavs::configureLandingTileDefinitions(), null, 'key' );
		$this->assertSame( 'neutral', $tilesByKey[ 'reports_alerts' ][ 'status' ] );
		$this->assertSame(
			$tileDefinitionsByKey[ 'login' ][ 'summary' ],
			$tilesByKey[ 'login' ][ 'summary' ]
		);
		$this->assertSame(
			$tileDefinitionsByKey[ 'reports_alerts' ][ 'stat_line' ],
			$tilesByKey[ 'reports_alerts' ][ 'stat_line' ]
		);
		$this->assertCount( 2, $tilesByKey[ 'reports_alerts' ][ 'panel' ][ 'rows' ] );
		$this->assertSame(
			[ Component\InstantAlerts::Slug(), Component\Reporting::Slug() ],
			\array_column( $tilesByKey[ 'reports_alerts' ][ 'panel' ][ 'rows' ], 'key' )
		);
		$this->assertSame(
			'instant_alert_admins,instant_alert_admin_login',
			$tilesByKey[ 'reports_alerts' ][ 'panel' ][ 'rows' ][ 0 ][ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);
		$this->assertSame(
			'frequency_alert,frequency_info',
			$tilesByKey[ 'reports_alerts' ][ 'panel' ][ 'rows' ][ 1 ][ 'config_action' ][ 'data' ][ 'option_keys' ] ?? ''
		);
		foreach ( $tilesByKey[ 'reports_alerts' ][ 'panel' ][ 'rows' ] as $row ) {
			$this->assertNotSame( '', $row[ 'title' ] );
			$this->assertSame( 'neutral', $row[ 'status' ] );
			$this->assertSame(
				$row[ 'key' ],
				$row[ 'config_action' ][ 'data' ][ 'zone_component_slug' ]
			);
		}
	}

	public function test_build_rejects_duplicate_row_keys_within_a_zone() :void {
		$this->installDuplicateKeyControllerStub();

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Configure row keys must be unique within a zone' );

		( new ConfigureZoneTilesBuilder() )->build();
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return array<string,mixed>|array{}
	 */
	private function findRowByOptionKeys( array $rows, string $optionKeys ) :array {
		foreach ( $rows as $row ) {
			if ( (string)( $row[ 'config_action' ][ 'data' ][ 'option_keys' ] ?? '' ) === $optionKeys ) {
				return $row;
			}
		}
		return [];
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
					$this->newComponent( 'WAF Rules', EnumEnabledStatus::GOOD, 'WAF subtitle', [ 'WAF rules are active.' ], [
						'waf_rules',
					] ),
				],
				\spl_object_id( $ipsZone )      => [
					$this->newComponent( 'Auto IP Blocking', EnumEnabledStatus::GOOD, 'IP subtitle', [ 'IP blocking is active.' ], [
						'user_auto_recover',
					] ),
					$this->newComponent( 'IP Blocking Rules', EnumEnabledStatus::GOOD, 'Rules subtitle', [ 'Rules are active.' ], [
						'request_whitelist',
					] ),
				],
				\spl_object_id( $scansZone )    => [
					$this->newComponent( 'Scan Schedule', EnumEnabledStatus::NEUTRAL_ENABLED, 'Scan subtitle', [ 'Scans are active.' ], [
						'scan_frequency',
					] ),
				],
				\spl_object_id( $loginZone )    => [
					$this->newComponentWithRows(
						'2FA Enforcement',
						EnumEnabledStatus::OKAY,
						'2FA subtitle',
						[ '2FA is not enforced.' ],
						[
							'mfa_verify_page',
							'allow_backupcodes',
							'enable_email_authentication',
							'two_factor_auth_user_roles',
							'enable_google_authenticator',
							'enable_passkeys',
						],
						[
							[
								'key'          => 'two_factor_general',
								'title'        => '2FA General Settings',
								'status'       => EnumEnabledStatus::OKAY,
								'note'         => 'Configure the core login-verification flow and backup access behaviour.',
								'explanations' => [ '2FA is not enforced.' ],
								'option_keys'  => [ 'mfa_verify_page', 'allow_backupcodes' ],
							],
							[
								'key'          => 'two_factor_email',
								'title'        => 'Email Authentication',
								'status'       => EnumEnabledStatus::NEUTRAL,
								'note'         => 'Configure email-based verification and role enforcement.',
								'explanations' => [],
								'option_keys'  => [ 'enable_email_authentication', 'two_factor_auth_user_roles' ],
							],
							[
								'key'          => 'two_factor_otp_passkeys',
								'title'        => 'OTP & Passkeys',
								'status'       => EnumEnabledStatus::NEUTRAL,
								'note'         => 'Configure authenticator apps, Yubikey OTP, and passkey support.',
								'explanations' => [],
								'option_keys'  => [ 'enable_google_authenticator', 'enable_passkeys' ],
							],
						]
					),
					$this->newComponent( 'Hide WP Login', EnumEnabledStatus::NEUTRAL, 'Login hide subtitle', [], [
						'rename_wplogin_path',
					] ),
				],
				\spl_object_id( $usersZone )    => [
					$this->newComponent( 'Inactive Users', EnumEnabledStatus::OKAY, 'Inactive user policy', [ 'Suspension policy needs review.' ] ),
				],
				\spl_object_id( $spamZone )     => [
					$this->newComponent( 'Bot SPAM Blocking', EnumEnabledStatus::BAD, 'Bot subtitle', [ 'Bot SPAM blocking is disabled.' ], [
						'spam_block_bots',
					] ),
					$this->newComponent( 'Human SPAM Filtering', EnumEnabledStatus::OKAY, 'Human subtitle', [ 'Human SPAM filtering needs setup.' ], [
						'spam_filter_human',
					] ),
					$this->newComponent( 'Trusted Commenters', EnumEnabledStatus::GOOD, 'Trusted commenters subtitle', [ 'Trusted commenters require history.' ], [
						'trusted_commenter_minimum',
					] ),
				],
				\spl_object_id( $headersZone )  => [
					$this->newComponent( 'CSP Headers', EnumEnabledStatus::GOOD, 'CSP subtitle', [ 'CSP is active.' ], [
						'headers_policy_mode',
					] ),
				],
			],
			[
				'module_secadmin'                => $this->newComponent(
					'Security Admin Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'pin_toggle',
						'admin_access_restrict_plugins',
					]
				),
				'module_firewall'                => $this->newComponent(
					'Firewall Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'waf_rules',
						'clean_wp_rubbish',
					]
				),
				'module_ips'                     => $this->newComponent(
					'IPs Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'user_auto_recover',
						'request_whitelist',
					]
				),
				'module_scans'                   => $this->newComponent(
					'Scans Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'scan_frequency',
						'optimise_scan_speed',
					]
				),
				'module_login'                   => $this->newComponent(
					'Login Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'mfa_verify_page',
						'allow_backupcodes',
						'enable_email_authentication',
						'two_factor_auth_user_roles',
						'enable_google_authenticator',
						'enable_passkeys',
						'rename_wplogin_path',
					]
				),
				'module_users'                   => $this->newComponent(
					'Users Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'manual_suspend',
					]
				),
				'module_spam'                    => $this->newComponent(
					'SPAM Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'spam_block_bots',
						'spam_filter_human',
						'trusted_commenter_minimum',
						'comments_cooldown',
					]
				),
				'module_headers'                 => $this->newComponent(
					'Headers Module',
					EnumEnabledStatus::GOOD,
					'',
					[],
					[
						'headers_policy_mode',
					]
				),
				'plugin_general'                 => $this->newComponent(
					'General Plugin Configuration',
					EnumEnabledStatus::GOOD,
					'General plugin settings.',
					[ 'General configuration is active.' ],
					[
						'ipdetect_source',
					]
				),
				Component\RequestLogging::Slug() => $this->newComponent(
					'Request Logging',
					EnumEnabledStatus::OKAY,
					'Request logging note from subtitle.',
					[],
					[
						'request_log_paths',
						'request_log_enabled',
					]
				),
				Component\InstantAlerts::Slug()  => $this->newComponentWithRows(
					'Instant Alerts',
					EnumEnabledStatus::GOOD,
					'Instant alerts on critical events.',
					[],
					[
						'instant_alert_admins',
						'instant_alert_admin_login',
					],
					[
						[
							'key'          => Component\InstantAlerts::Slug(),
							'title'        => 'Instant Alerts',
							'status'       => EnumEnabledStatus::NEUTRAL,
							'note'         => 'Manage immediate alerts for important security events.',
							'explanations' => [],
							'option_keys'  => [ 'instant_alert_admins', 'instant_alert_admin_login' ],
						],
					],
					Component\InstantAlerts::Slug()
				),
				Component\Reporting::Slug()       => $this->newComponentWithRows(
					'Reporting',
					EnumEnabledStatus::OKAY,
					"See what's happening with reports.",
					[],
					[
						'frequency_alert',
						'frequency_info',
					],
					[
						[
							'key'          => Component\Reporting::Slug(),
							'title'        => 'Reports',
							'status'       => EnumEnabledStatus::NEUTRAL,
							'note'         => 'Manage report email delivery and reporting frequency.',
							'explanations' => [],
							'option_keys'  => [ 'frequency_alert', 'frequency_info' ],
						],
					],
					Component\Reporting::Slug()
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
				public array $options = [];
				public array $sections = [];
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) {
				return null;
			}

			public function optHasAccess( string $key ) :bool {
				return true;
			}

			public function optDef( string $key ) :array {
				return [];
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

	private function installDuplicateKeyControllerStub() :void {
		$loginZone = $this->newZone( 'module_login' );
		$buildComponent = static function ( string $title, array $options, string $slug ) :Component\Base {
			return new class( $title, $options, $slug ) extends Component\Base {
				private string $localTitle;
				private array $localOptions;
				private string $localSlug;

				public function __construct( string $title, array $options, string $slug ) {
					$this->localTitle = $title;
					$this->localOptions = $options;
					$this->localSlug = $slug;
				}

				public function title() :string {
					return $this->localTitle;
				}

				public function enabledStatus() :string {
					return EnumEnabledStatus::GOOD;
				}

				public function getOptions() :array {
					return $this->localOptions;
				}

				protected function configZoneComponentSlugs() :array {
					return [ $this->localSlug ];
				}
			};
		};

		/** @var SecurityZonesCon $zonesCon */
		$zonesCon = new class( $loginZone, $buildComponent ) extends SecurityZonesCon {
			private Zone\Base $loginZone;
			private $buildComponent;

			public function __construct( Zone\Base $loginZone, callable $buildComponent ) {
				$this->loginZone = $loginZone;
				$this->buildComponent = $buildComponent;
			}

			public function getZone( string $slug ) :Zone\Base {
				return $this->loginZone;
			}

			public function getZoneComponent( string $slug ) :Component\Base {
				return ( $this->buildComponent )( 'Unused', [], $slug );
			}

			public function getComponentsForZone( Zone\Base $zone ) :array {
				return [
					( $this->buildComponent )( 'First Duplicate', [ 'first_option' ], 'duplicate_key' ),
					( $this->buildComponent )( 'Second Duplicate', [ 'second_option' ], 'duplicate_key' ),
				];
			}
		};

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
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
				public array $options = [];
				public array $sections = [];
			},
		];
		$controller->opts = new class {
			public function optGet( string $key ) {
				return null;
			}

			public function optHasAccess( string $key ) :bool {
				return true;
			}

			public function optDef( string $key ) :array {
				return [];
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

			public function getConfigZoneComponentSlugs() :array {
				return [ $this->moduleSlug ];
			}
		};
	}

	private function newComponent(
		string $title,
		string $enabledStatus,
		string $subtitle,
		array $explanation,
		array $options = [],
		?string $slug = null
	) :Component\Base {
		return new class( $title, $enabledStatus, $subtitle, $explanation, $options, $slug ) extends Component\Base {
			private string $localTitle;
			private string $localEnabledStatus;
			private string $localSubtitle;
			private array $localExplanation;
			private array $localOptions;
			private string $localSlug;

			public function __construct( string $title, string $enabledStatus, string $subtitle, array $explanation, array $options, ?string $slug ) {
				$this->localTitle = $title;
				$this->localEnabledStatus = $enabledStatus;
				$this->localSubtitle = $subtitle;
				$this->localExplanation = $explanation;
				$this->localOptions = $options;
				$this->localSlug = $slug ?? \strtolower( \str_replace( ' ', '_', $title ) );
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

			protected function configZoneComponentSlugs() :array {
				return [ $this->localSlug ];
			}
		};
	}

	private function newComponentWithRows(
		string $title,
		string $enabledStatus,
		string $subtitle,
		array $explanation,
		array $options,
		array $rows,
		?string $slug = null
	) :Component\Base {
		return new class( $title, $enabledStatus, $subtitle, $explanation, $options, $rows, $slug ) extends Component\Base {
			private string $localTitle;
			private string $localEnabledStatus;
			private string $localSubtitle;
			private array $localExplanation;
			private array $localOptions;
			private array $localRows;
			private string $localSlug;

			public function __construct(
				string $title,
				string $enabledStatus,
				string $subtitle,
				array $explanation,
				array $options,
				array $rows,
				?string $slug
			) {
				$this->localTitle = $title;
				$this->localEnabledStatus = $enabledStatus;
				$this->localSubtitle = $subtitle;
				$this->localExplanation = $explanation;
				$this->localOptions = $options;
				$this->localRows = $rows;
				$this->localSlug = $slug ?? \strtolower( \str_replace( ' ', '_', $title ) );
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

			public function configureRows() :array {
				return \array_map(
					function ( array $row ) :array {
						return $this->buildConfigureRowInput(
							$row[ 'key' ],
							$row[ 'title' ],
							$row[ 'status' ],
							$row[ 'note' ],
							$row[ 'explanations' ],
							$this->buildConfigureRowScope(
								[ $this->localSlug ],
								$row[ 'option_keys' ],
								'',
								'Edit Settings'
							)
						);
					},
					$this->localRows
				);
			}

			protected function configZoneComponentSlugs() :array {
				return [ $this->localSlug ];
			}
		};
	}
}
