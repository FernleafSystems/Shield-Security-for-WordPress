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
					$this->newComponent( 'PIN Protection', EnumEnabledStatus::GOOD, 'PIN subtitle', [ 'PIN is active.' ] ),
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
					$this->newComponent( '2FA Enforcement', EnumEnabledStatus::OKAY, '2FA subtitle', [ '2FA is not enforced.' ] ),
				],
				\spl_object_id( $usersZone )    => [
					$this->newComponent( 'Inactive Users', EnumEnabledStatus::OKAY, 'Inactive user policy', [ 'Suspension policy needs review.' ] ),
				],
				\spl_object_id( $spamZone )     => [
					$this->newComponent( 'Bot SPAM Blocking', EnumEnabledStatus::BAD, 'Bot subtitle', [ 'Bot SPAM blocking is disabled.' ] ),
					$this->newComponent( 'Human SPAM Filtering', EnumEnabledStatus::OKAY, 'Human subtitle', [ 'Human SPAM filtering needs setup.' ] ),
				],
				\spl_object_id( $headersZone )  => [
					$this->newComponent( 'CSP Headers', EnumEnabledStatus::GOOD, 'CSP subtitle', [ 'CSP is active.' ] ),
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
					[]
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
		$controller->comps = (object)[
			'zones' => $zonesCon,
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
		array $explanation
	) :Component\Base {
		return new class( $title, $enabledStatus, $subtitle, $explanation ) extends Component\Base {
			private string $localTitle;
			private string $localEnabledStatus;
			private string $localSubtitle;
			private array $localExplanation;

			public function __construct( string $title, string $enabledStatus, string $subtitle, array $explanation ) {
				$this->localTitle = $title;
				$this->localEnabledStatus = $enabledStatus;
				$this->localSubtitle = $subtitle;
				$this->localExplanation = $explanation;
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
		};
	}
}
