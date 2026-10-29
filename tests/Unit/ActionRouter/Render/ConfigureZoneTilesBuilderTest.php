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

	public function test_build_returns_one_tile_per_goal_with_settings_in_definition_order() :void {
		$tiles = ( new ConfigureZoneTilesBuilder() )->build();

		$this->assertSame( [ 'accounts', 'alerts' ], \array_column( $tiles, 'key' ) );
		$definitions = \array_column( PluginNavs::configureLandingTileDefinitions(), null, 'key' );
		foreach ( $tiles as $tile ) {
			$this->assertSame( $tile[ 'key' ], $tile[ 'panel_target' ] );
			$this->assertSame( $definitions[ $tile[ 'key' ] ][ 'label' ], $tile[ 'label' ] );
			$this->assertSame( $definitions[ $tile[ 'key' ] ][ 'summary' ], $tile[ 'summary' ] );
			$this->assertSame( 'bi bi-', \substr( $tile[ 'icon_class' ], 0, 6 ) );
			$this->assertNotSame( '', $tile[ 'stat_line' ] );
		}
		$tilesByKey = \array_column( $tiles, null, 'key' );
		$this->assertTrue( $tilesByKey[ 'accounts' ][ 'include_in_posture' ] );
		$this->assertFalse( $tilesByKey[ 'alerts' ][ 'include_in_posture' ] );
	}

	public function test_topic_rows_gather_their_options_across_components() :void {
		$accounts = $this->tile( 'accounts' );

		// Two-factor has no settings in this stub, so it has no row.
		$this->assertSame( [ 'passwords', 'sessions', 'suspensions' ], \array_column( $accounts[ 'panel' ][ 'rows' ], 'key' ) );

		$passwords = $accounts[ 'panel' ][ 'rows' ][ 0 ];
		$this->assertSame( 'Passwords', $passwords[ 'title' ] );
		$this->assertSame(
			'enable_password_policies,pass_min_strength,pass_force_existing,pass_prevent_pwned,pass_expire,auto_password',
			$passwords[ 'config_action' ][ 'data' ][ 'option_keys' ],
			'Options follow the panel layout order, with hidden options left out'
		);
		$this->assertSame(
			'password_policies,pwned_passwords,password_strength,module_users,inactive_users',
			$passwords[ 'config_action' ][ 'data' ][ 'zone_component_slug' ]
		);
		$this->assertSame( 'accounts:passwords', $passwords[ 'config_action' ][ 'data' ][ 'configure_panel' ] );
	}

	public function test_topic_status_is_the_worst_of_the_components_behind_it() :void {
		$rows = \array_column( $this->tile( 'accounts' )[ 'panel' ][ 'rows' ], null, 'key' );

		$this->assertSame( 'critical', $rows[ 'passwords' ][ 'status' ] );
		$this->assertSame(
			[ 'Password policy is active.', 'Pwned password checks are disabled.', 'Minimum password strength is too low.', 'Suspension policy needs review.' ],
			$rows[ 'passwords' ][ 'explanations' ]
		);
		$this->assertSame( 'good', $rows[ 'sessions' ][ 'status' ] );
		$this->assertSame( 'warning', $rows[ 'suspensions' ][ 'status' ] );
		$this->assertSame( 'critical', $this->tile( 'accounts' )[ 'status' ] );
	}

	public function test_topics_without_a_status_stay_neutral() :void {
		$alerts = $this->tile( 'alerts' );
		$this->assertSame( [ 'reports', 'instant_alerts' ], \array_column( $alerts[ 'panel' ][ 'rows' ], 'key' ) );
		foreach ( $alerts[ 'panel' ][ 'rows' ] as $row ) {
			$this->assertSame( 'neutral', $row[ 'status' ] );
		}
	}

	private function tile( string $key ) :array {
		foreach ( ( new ConfigureZoneTilesBuilder() )->build() as $tile ) {
			if ( $tile[ 'key' ] === $key ) {
				return $tile;
			}
		}
		$this->fail( 'No tile '.$key );
	}

	/**
	 * @param array<string,list<string>> $visible option key => the components it belongs to
	 */
	private function optionDefinitions( array $visible, array $hiddenOptionKeys = [] ) :array {
		$options = [];
		foreach ( $visible as $optionKey => $componentSlugs ) {
			$options[ $optionKey ] = [
				'key'             => $optionKey,
				'section'         => 'section_stub',
				'zone_comp_slugs' => $componentSlugs,
			];
		}
		foreach ( $hiddenOptionKeys as $optionKey ) {
			$options[ $optionKey ] = [
				'key'             => $optionKey,
				'section'         => 'section_hidden',
				'zone_comp_slugs' => [ 'password_policies' ],
			];
		}
		return $options;
	}

	private function installControllerStub() :void {
		$components = [
			'password_policies'        => $this->newComponent( 'Password Policies', EnumEnabledStatus::GOOD, '', [ 'Password policy is active.' ], [
				'enable_password_policies',
				'pass_expire',
				'pass_force_existing',
			], 'password_policies' ),
			'pwned_passwords'          => $this->newComponent( 'Block Pwned Passwords', EnumEnabledStatus::BAD, '', [ 'Pwned password checks are disabled.' ], [
				'enable_password_policies',
				'pass_prevent_pwned',
			], 'pwned_passwords' ),
			'password_strength'        => $this->newComponent( 'Password Strength', EnumEnabledStatus::OKAY, '', [ 'Minimum password strength is too low.' ], [
				'enable_password_policies',
				'pass_min_strength',
			], 'password_strength' ),
			'inactive_users'           => $this->newComponent( 'User Suspension', EnumEnabledStatus::OKAY, '', [ 'Suspension policy needs review.' ], [
				'manual_suspend',
				'auto_password',
			], 'inactive_users' ),
			'session_theft_protection' => $this->newComponent( 'Sessions', EnumEnabledStatus::GOOD, '', [], [
				'session_timeout_interval',
				'session_lock',
			], 'session_theft_protection' ),
			'reporting'                => $this->newComponent( 'Reporting', EnumEnabledStatus::NEUTRAL, '', [], [
				'frequency_alert',
			], 'reporting' ),
			'instant_alerts'           => $this->newComponent( 'Instant Alerts', EnumEnabledStatus::NEUTRAL, '', [], [
				'instant_alert_admin_login',
			], 'instant_alerts' ),
		];

		/** @var SecurityZonesCon $zonesCon */
		$zonesCon = new class( $components ) extends SecurityZonesCon {
			private array $componentsBySlug;

			public function __construct( array $componentsBySlug ) {
				$this->componentsBySlug = $componentsBySlug;
			}

			public function getZoneComponent( string $slug ) :Component\Base {
				return $this->componentsBySlug[ $slug ];
			}
		};

		$optionDefinitions = $this->optionDefinitions( [
			'enable_password_policies'  => [ 'password_policies', 'pwned_passwords', 'password_strength', 'module_users' ],
			'pass_min_strength'         => [ 'password_strength', 'module_users' ],
			'pass_force_existing'       => [ 'password_policies', 'module_users' ],
			'pass_prevent_pwned'        => [ 'pwned_passwords', 'module_users' ],
			'pass_expire'               => [ 'password_policies', 'module_users' ],
			'auto_password'             => [ 'inactive_users', 'module_users' ],
			'manual_suspend'            => [ 'inactive_users', 'module_users' ],
			'session_timeout_interval'  => [ 'session_theft_protection', 'module_login' ],
			'session_lock'              => [ 'session_theft_protection', 'module_login' ],
			'frequency_alert'           => [ 'reporting' ],
			'instant_alert_admin_login' => [ 'instant_alerts' ],
		], [ 'enable_user_login_email_notification' ] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->cfg = (object)[
			'configuration' => new class( $optionDefinitions ) {
				public array $options;
				public array $sections = [];

				public function __construct( array $options ) {
					$this->options = $options;
				}
			},
		];
		$controller->comps = (object)[
			'zones' => $zonesCon,
		];

		PluginControllerInstaller::install( $controller );
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
}
