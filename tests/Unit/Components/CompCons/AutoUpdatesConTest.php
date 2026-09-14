<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\AutoUpdatesCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	MaintenancePluginsService,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\Themes;
use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;

class AutoUpdatesConTest extends BaseUnitTest {

	private const BASE_FILE = 'wp-plugin-shield/icwp-wpsf.php';
	private const OTHER_PLUGIN_FILE = 'akismet/akismet.php';
	private const NEW_VERSION = '22.0.1';
	private const NOW = 1700000000;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_self_auto_update_delay_is_honoured_when_general_delay_is_zero_and_tracking_is_missing() :void {
		$opts = $this->installEnvironment( [], $this->updatesFor( self::BASE_FILE ) );

		$result = ( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) );

		$this->assertFalse( $result );
		$this->assertSame(
			self::NOW,
			$this->delayTracking( $opts )[ 'plugins' ][ self::BASE_FILE ][ self::NEW_VERSION ]
		);
	}

	public function test_self_auto_update_is_allowed_after_self_delay_window() :void {
		$this->installEnvironment( [
			'delay_tracking' => [
				'plugins' => [
					self::BASE_FILE => [
						self::NEW_VERSION => self::NOW - 5*\DAY_IN_SECONDS - 1,
					],
				],
			],
		], $this->updatesFor( self::BASE_FILE ) );

		$result = ( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) );

		$this->assertTrue( $result );
	}

	public function test_self_auto_update_uses_longer_configured_update_delay() :void {
		$this->installEnvironment( [
			'update_delay'    => 7,
			'delay_tracking' => [
				'plugins' => [
					self::BASE_FILE => [
						self::NEW_VERSION => self::NOW - 6*\DAY_IN_SECONDS,
					],
				],
			],
		], $this->updatesFor( self::BASE_FILE ) );

		$result = ( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) );

		$this->assertFalse( $result );
	}

	public function test_immediate_self_auto_update_bypasses_delay() :void {
		$this->installEnvironment( [
			'autoupdate_plugin_self' => 'immediate',
		], $this->updatesFor( self::BASE_FILE ) );

		$result = ( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) );

		$this->assertTrue( $result );
	}

	public function test_disabled_self_auto_update_is_denied() :void {
		$this->installEnvironment( [
			'autoupdate_plugin_self' => 'disabled',
		], $this->updatesFor( self::BASE_FILE ) );

		$result = ( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) );

		$this->assertFalse( $result );
	}

	public function test_vulnerable_self_plugin_still_honours_auto_delay() :void {
		$this->installEnvironment(
			[],
			$this->updatesFor( self::BASE_FILE ),
			new AutoUpdatesConTestWpv( true, [ self::BASE_FILE ] )
		);

		$result = ( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) );

		$this->assertFalse( $result );
	}

	public function test_non_self_plugin_with_zero_update_delay_preserves_incoming_wordpress_decision() :void {
		$this->installEnvironment( [], $this->updatesFor( self::OTHER_PLUGIN_FILE ) );

		$subject = new AutoUpdatesCon();

		$this->assertTrue( $subject->autoupdate_plugins( true, $this->pluginUpdateItem( self::OTHER_PLUGIN_FILE ) ) );
		$this->assertFalse( $subject->autoupdate_plugins( false, $this->pluginUpdateItem( self::OTHER_PLUGIN_FILE ) ) );
	}

	public function test_self_auto_update_allows_expired_whole_number_version_delay() :void {
		$timestamp = self::NOW - 5*\DAY_IN_SECONDS - 1;
		$opts = $this->installEnvironment( [
			'delay_tracking' => [
				'plugins' => [
					self::BASE_FILE => [ '2' => $timestamp ],
				],
			],
		], [
			self::BASE_FILE => (object)[ 'new_version' => '2' ],
		] );

		$this->assertTrue(
			( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) )
		);
		$this->assertSame( $timestamp, $this->delayTracking( $opts )[ 'plugins' ][ self::BASE_FILE ][ '2' ] );
	}

	public function test_valid_vulnerable_non_self_plugin_forces_auto_update() :void {
		$wpv = new AutoUpdatesConTestWpv( true, [ self::OTHER_PLUGIN_FILE ] );
		$this->installEnvironment( [ 'update_delay' => 7 ], $this->updatesFor( self::OTHER_PLUGIN_FILE ), $wpv );

		$this->assertTrue(
			( new AutoUpdatesCon() )->autoupdate_plugins( false, $this->pluginUpdateItem( self::OTHER_PLUGIN_FILE ) )
		);
		$this->assertSame( [ self::OTHER_PLUGIN_FILE ], $wpv->vulnerabilityChecks );
	}

	public function test_valid_core_future_delay_is_preserved_and_blocks_update() :void {
		$timestamp = self::NOW + 300;
		$opts = $this->installEnvironment( [
			'update_delay' => 7,
			'delay_tracking' => [
				'core' => [ 'wp' => [ '7' => $timestamp ] ],
			],
		], [] );

		$this->assertFalse(
			( new AutoUpdatesCon() )->autoupdate_core( true, (object)[ 'current' => '7' ] )
		);
		$this->assertSame( $timestamp, $this->delayTracking( $opts )[ 'core' ][ 'wp' ][ '7' ] );
	}

	public function test_valid_core_expired_delay_preserves_incoming_decision() :void {
		$this->installEnvironment( [
			'update_delay' => 7,
			'delay_tracking' => [
				'core' => [ 'wp' => [ '6.8.1' => self::NOW - 7*\DAY_IN_SECONDS - 1 ] ],
			],
		], [] );

		$this->assertSame(
			'upstream',
			( new AutoUpdatesCon() )->autoupdate_core( 'upstream', (object)[ 'current' => '6.8.1' ] )
		);
	}

	public function test_numeric_theme_slug_and_whole_number_version_delay_are_honoured() :void {
		$this->installEnvironment( [
			'update_delay' => 7,
			'delay_tracking' => [
				'themes' => [ '2024' => [ '2' => self::NOW - 1 ] ],
			],
		], [] );
		ServicesState::mergeItems( [
			'service_wpthemes' => new AutoUpdatesConTestThemes( [
				'2024' => [ 'new_version' => '2' ],
			] ),
		] );

		$this->assertFalse(
			( new AutoUpdatesCon() )->autoupdate_themes( true, (object)[ 'theme' => '2024' ] )
		);
	}

	public function test_valid_theme_expired_delay_preserves_incoming_decision() :void {
		$this->installEnvironment( [
			'update_delay' => 7,
			'delay_tracking' => [
				'themes' => [ 'twentynineteen' => [ '3.3' => self::NOW - 7*\DAY_IN_SECONDS - 1 ] ],
			],
		], [] );
		ServicesState::mergeItems( [
			'service_wpthemes' => new AutoUpdatesConTestThemes( [
				'twentynineteen' => [ 'new_version' => '3.3' ],
			] ),
		] );

		$this->assertSame(
			'upstream',
			( new AutoUpdatesCon() )->autoupdate_themes( 'upstream', (object)[ 'theme' => 'twentynineteen' ] )
		);
	}

	public function test_self_auto_update_preserves_incoming_decision_when_update_version_is_invalid() :void {
		$this->installEnvironment( [], [
			self::BASE_FILE => (object)[ 'new_version' => 22.1 ],
		] );

		$subject = new AutoUpdatesCon();

		$this->assertFalse( $subject->autoupdate_plugins( false, $this->pluginUpdateItem( self::BASE_FILE ) ) );
		$this->assertTrue( $subject->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) ) );
	}

	public function test_disabled_self_auto_update_does_not_require_valid_update_version() :void {
		$this->installEnvironment( [ 'autoupdate_plugin_self' => 'disabled' ], [
			self::BASE_FILE => (object)[ 'new_version' => [] ],
		] );

		$this->assertFalse(
			( new AutoUpdatesCon() )->autoupdate_plugins( true, $this->pluginUpdateItem( self::BASE_FILE ) )
		);
	}

	/**
	 * @dataProvider provideInvalidIdentifiers
	 */
	public function test_plugin_auto_update_preserves_incoming_decision_for_invalid_identifier( $identifier ) :void {
		$wpv = new AutoUpdatesConTestWpv( true, [ self::OTHER_PLUGIN_FILE ] );
		$this->installEnvironment( [ 'update_delay' => 7 ], [], $wpv );
		$subject = new AutoUpdatesCon();
		$item = (object)[ 'plugin' => $identifier ];

		$this->assertTrue( $subject->autoupdate_plugins( true, $item ) );
		$this->assertFalse( $subject->autoupdate_plugins( false, $item ) );
		$this->assertSame( [], $wpv->vulnerabilityChecks );
	}

	/**
	 * @dataProvider provideInvalidIdentifiers
	 */
	public function test_theme_auto_update_preserves_incoming_decision_for_invalid_identifier( $identifier ) :void {
		$this->installEnvironment( [ 'update_delay' => 7 ], [] );
		$subject = new AutoUpdatesCon();
		$item = (object)[ 'theme' => $identifier ];

		$this->assertTrue( $subject->autoupdate_themes( true, $item ) );
		$this->assertFalse( $subject->autoupdate_themes( false, $item ) );
	}

	public function test_auto_update_filters_preserve_decisions_for_resource_identifiers() :void {
		$resource = \fopen( 'php://memory', 'rb' );
		$this->assertIsResource( $resource );
		try {
			$this->installEnvironment( [ 'update_delay' => 7 ], [] );
			$subject = new AutoUpdatesCon();
			$this->assertSame( 'plugin-decision', $subject->autoupdate_plugins(
				'plugin-decision',
				(object)[ 'plugin' => $resource ]
			) );
			$this->assertSame( 'theme-decision', $subject->autoupdate_themes(
				'theme-decision',
				(object)[ 'theme' => $resource ]
			) );
		}
		finally {
			\fclose( $resource );
		}
	}

	public function provideInvalidIdentifiers() :array {
		return [
			'null'       => [ null ],
			'false'      => [ false ],
			'integer'    => [ 123 ],
			'float'      => [ 1.5 ],
			'array'      => [ [] ],
			'object'     => [ (object)[] ],
			'empty'      => [ '' ],
			'whitespace' => [ " \t\n" ],
		];
	}

	public function test_core_update_tracking_ignores_malformed_transient_entries() :void {
		$opts = $this->installEnvironment( [], [] );

		$updates = (object)[
			'updates' => [
				'not-an-update-object',
				(object)[ 'response' => 'autoupdate' ],
				(object)[
					'response' => 'manual',
					'current'  => '6.8.0',
				],
				[
					'response' => 'autoupdate',
					'current'  => '6.8.1',
				],
				[
					'response' => 'autoupdate',
					'current'  => [],
				],
				(object)[
					'response' => 'autoupdate',
					'current'  => (object)[],
				],
				(object)[
					'response' => 'autoupdate',
					'current'  => '6.8.2',
				],
			],
		];

		( new AutoUpdatesCon() )->trackUpdateTimesCore( $updates );

		$this->assertSame(
			[
				'6.8.1' => self::NOW,
				'6.8.2' => self::NOW,
			],
			$this->delayTracking( $opts )[ 'core' ][ 'wp' ]
		);
	}

	public function test_core_tracking_preserves_whole_number_version_key() :void {
		$opts = $this->installEnvironment( [], [] );

		( new AutoUpdatesCon() )->trackUpdateTimesCore( (object)[
			'updates' => [ (object)[
				'response' => 'autoupdate',
				'current'  => '7',
			] ],
		] );

		$this->assertSame( self::NOW, $this->delayTracking( $opts )[ 'core' ][ 'wp' ][ '7' ] );
	}

	public function test_plugin_update_tracking_ignores_malformed_transient_entries() :void {
		$opts = $this->installEnvironment( [], [] );

		$updates = (object)[
			'response' => [
				self::BASE_FILE         => [ 'new_version' => self::NEW_VERSION ],
				self::OTHER_PLUGIN_FILE => 'not-an-update-object',
				'empty/plugin.php'      => (object)[],
				'array-version.php'     => (object)[ 'new_version' => [] ],
			],
		];

		( new AutoUpdatesCon() )->trackUpdateTimesPlugins( $updates );

		$this->assertSame(
			[ self::NEW_VERSION => self::NOW ],
			$this->delayTracking( $opts )[ 'plugins' ][ self::BASE_FILE ]
		);
		$this->assertArrayNotHasKey(
			self::OTHER_PLUGIN_FILE,
			$this->delayTracking( $opts )[ 'plugins' ]
		);
		$this->assertArrayNotHasKey(
			'empty/plugin.php',
			$this->delayTracking( $opts )[ 'plugins' ]
		);
		$this->assertArrayNotHasKey(
			'array-version.php',
			$this->delayTracking( $opts )[ 'plugins' ]
		);
	}

	public function test_theme_update_tracking_ignores_malformed_transient_entries() :void {
		$opts = $this->installEnvironment( [], [] );

		$updates = (object)[
			'response' => [
				'twentynineteen'  => [ 'new_version' => '3.3' ],
				'twentytwenty'    => false,
				'twentytwentyone' => (object)[],
				'twentytwentytwo' => (object)[ 'new_version' => [] ],
			],
		];

		( new AutoUpdatesCon() )->trackUpdateTimesThemes( $updates );

		$this->assertSame(
			[ '3.3' => self::NOW ],
			$this->delayTracking( $opts )[ 'themes' ][ 'twentynineteen' ]
		);
		$this->assertArrayNotHasKey(
			'twentytwenty',
			$this->delayTracking( $opts )[ 'themes' ]
		);
		$this->assertArrayNotHasKey(
			'twentytwentyone',
			$this->delayTracking( $opts )[ 'themes' ]
		);
		$this->assertArrayNotHasKey(
			'twentytwentytwo',
			$this->delayTracking( $opts )[ 'themes' ]
		);
	}

	public function test_core_auto_update_preserves_wordpress_decision_for_malformed_core_upgrade() :void {
		$this->installEnvironment( [ 'update_delay' => 7 ], [] );
		$subject = new AutoUpdatesCon();

		$this->assertTrue( $subject->autoupdate_core( true, (object)[] ) );
		$this->assertTrue( $subject->autoupdate_core( true, (object)[ 'current' => [] ] ) );
		$this->assertFalse( $subject->autoupdate_core( false, 'not-a-core-upgrade-object' ) );
	}

	public function test_plugin_auto_update_preserves_wordpress_decision_for_malformed_update_version() :void {
		$this->installEnvironment( [ 'update_delay' => 7 ], [
			self::OTHER_PLUGIN_FILE => (object)[ 'new_version' => [] ],
		] );

		$subject = new AutoUpdatesCon();

		$this->assertTrue( $subject->autoupdate_plugins( true, $this->pluginUpdateItem( self::OTHER_PLUGIN_FILE ) ) );
		$this->assertFalse( $subject->autoupdate_plugins( false, $this->pluginUpdateItem( self::OTHER_PLUGIN_FILE ) ) );
	}

	public function test_plugin_auto_update_rejects_direct_integer_update_version() :void {
		$wpv = new AutoUpdatesConTestWpv( true, [ self::OTHER_PLUGIN_FILE ] );
		$this->installEnvironment( [ 'update_delay' => 7 ], [
			self::OTHER_PLUGIN_FILE => (object)[ 'new_version' => 2 ],
		], $wpv );

		$this->assertSame(
			'upstream',
			( new AutoUpdatesCon() )->autoupdate_plugins( 'upstream', $this->pluginUpdateItem( self::OTHER_PLUGIN_FILE ) )
		);
		$this->assertSame( [], $wpv->vulnerabilityChecks );
	}

	public function test_theme_auto_update_preserves_wordpress_decision_for_malformed_update_version() :void {
		$this->installEnvironment( [ 'update_delay' => 7 ], [] );
		ServicesState::mergeItems( [
			'service_wpthemes' => new AutoUpdatesConTestThemes( [
				'twentynineteen' => [ 'new_version' => [] ],
			] ),
		] );

		$subject = new AutoUpdatesCon();

		$this->assertTrue( $subject->autoupdate_themes( true, (object)[ 'theme' => 'twentynineteen' ] ) );
		$this->assertFalse( $subject->autoupdate_themes( false, (object)[ 'theme' => 'twentynineteen' ] ) );
	}

	public function test_delay_tracking_option_accepts_corrupted_stored_value() :void {
		$opts = $this->installEnvironment( [ 'delay_tracking' => 'corrupt-cache-value' ], [] );

		( new AutoUpdatesCon() )->trackUpdateTimesPlugins( (object)[
			'response' => [
				self::BASE_FILE => (object)[ 'new_version' => self::NEW_VERSION ],
			],
		] );

		$this->assertSame(
			[ self::NEW_VERSION => self::NOW ],
			$this->delayTracking( $opts )[ 'plugins' ][ self::BASE_FILE ]
		);
	}

	public function test_delay_tracking_is_canonicalized_at_the_smallest_invalid_member() :void {
		$opts = $this->installEnvironment( [
			'delay_tracking' => [
				'core' => [
					'wp' => [
						' 6.8.1 ' => self::NOW - 10,
						'6.8.2'    => (string)( self::NOW - 9 ),
						'6.8.3'    => self::NOW + 1,
						'6.8.4'    => -1,
						'6.8.5'    => \PHP_INT_MAX,
						'6.8.6'    => [],
						'6.8.7'    => (object)[],
					],
					'other' => [ '6.8.4' => self::NOW - 8 ],
				],
				'plugins' => [
					' '.self::BASE_FILE.' ' => [
						' '.self::NEW_VERSION.' ' => self::NOW - 7,
						'bad'                       => 0,
					],
					'bad-row' => 'not-an-array',
					'bad-object-row' => (object)[],
				],
				'themes' => [
					'twentynineteen' => [ '3.3' => self::NOW - 5 ],
					2024 => [ 2 => self::NOW - 4 ],
				],
				'extra' => [ 'ignored' ],
			],
		], [] );

		$this->assertSame( [
			'core' => [
				'wp' => [
					'6.8.1' => self::NOW - 10,
					'6.8.3' => self::NOW + 1,
					'6.8.5' => \PHP_INT_MAX,
				],
			],
			'plugins' => [
				self::BASE_FILE => [ self::NEW_VERSION => self::NOW - 7 ],
			],
			'themes' => [
				'twentynineteen' => [ '3.3' => self::NOW - 5 ],
				2024 => [ 2 => self::NOW - 4 ],
			],
		], ( new AutoUpdatesCon() )->getDelayTracking() );
		$this->assertSame( 1, $opts->setCounts[ 'delay_tracking' ] ?? 0 );
	}

	public function test_delay_tracking_drops_scalar_and_object_contexts() :void {
		$opts = $this->installEnvironment( [
			'delay_tracking' => [
				'core'    => 'invalid',
				'plugins' => (object)[],
				'themes'  => null,
			],
		], [] );

		$this->assertSame( [
			'core'    => [],
			'plugins' => [],
			'themes'  => [],
		], ( new AutoUpdatesCon() )->getDelayTracking() );
		$this->assertSame( 1, $opts->setCounts[ 'delay_tracking' ] ?? 0 );
	}

	public function test_tracking_rejects_non_string_versions_without_losing_valid_siblings() :void {
		$opts = $this->installEnvironment( [], [] );
		$subject = new AutoUpdatesCon();

		$subject->trackUpdateTimesPlugins( (object)[ 'response' => [
			self::BASE_FILE => (object)[ 'new_version' => self::NEW_VERSION ],
			'bad-version'   => (object)[ 'new_version' => 2.5 ],
			'blank-version' => (object)[ 'new_version' => '  ' ],
		] ] );

		$this->assertSame( [
			self::BASE_FILE => [ self::NEW_VERSION => self::NOW ],
		], $this->delayTracking( $opts )[ 'plugins' ] );
	}

	public function test_theme_tracking_recovers_php_coerced_numeric_map_keys() :void {
		$opts = $this->installEnvironment( [], [] );

		( new AutoUpdatesCon() )->trackUpdateTimesThemes( (object)[ 'response' => [
			2024 => (object)[ 'new_version' => '2' ],
		] ] );

		$this->assertSame(
			self::NOW,
			$this->delayTracking( $opts )[ 'themes' ][ '2024' ][ '2' ]
		);
	}

	public function test_plugins_list_normalizes_outer_sections_and_rows() :void {
		$this->installEnvironment( [], [] );
		$subject = new AutoUpdatesCon();
		$validShieldRow = [ 'Name' => 'Shield' ];

		$this->assertSame( [], $subject->indicateAutoUpdate( 'invalid' ) );
		$this->assertSame( [
			'all' => [
				self::BASE_FILE => $validShieldRow + [ 'auto-update-forced' => true ],
				self::OTHER_PLUGIN_FILE => [ 'Name' => 'Akismet' ],
			],
			'invalid-row' => [],
			'invalid-object-row' => [],
		], $subject->indicateAutoUpdate( [
			'all' => [
				self::BASE_FILE => $validShieldRow,
				self::OTHER_PLUGIN_FILE => [ 'Name' => 'Akismet' ],
			],
			'invalid-section' => 'not-an-array',
			'invalid-object-section' => (object)[],
			'invalid-row' => [ self::BASE_FILE => 'not-an-array' ],
			'invalid-object-row' => [ self::BASE_FILE => (object)[] ],
		] ) );
	}

	private function delayTracking( AutoUpdatesConTestOptions $opts ) :array {
		$delayTracking = $opts->optGet( 'delay_tracking' );
		$this->assertIsArray( $delayTracking );
		return $delayTracking;
	}

	private function installEnvironment(
		array $optionOverrides,
		array $updates,
		?AutoUpdatesConTestWpv $wpv = null
	) :AutoUpdatesConTestOptions {
		$opts = new AutoUpdatesConTestOptions( $optionOverrides );

		ServicesState::installItems( [
			'service_datamanipulation' => new DataManipulation(),
			'service_request'          => new UnitTestRequest( [], '127.0.0.1', self::NOW ),
			'service_wpplugins'        => new MaintenancePluginsService( [
				'updates' => $updates,
			] ),
		] );

		UnitTestControllerFactory::install( null, null, (object)[
			'base_file' => self::BASE_FILE,
			'cfg'       => new AutoUpdatesConTestConfig(),
			'comps'     => (object)[
				'scans' => new AutoUpdatesConTestScans( $wpv ?? new AutoUpdatesConTestWpv() ),
			],
			'opts'      => $opts,
		] );

		return $opts;
	}

	private function updatesFor( string $pluginFile ) :array {
		return [
			$pluginFile => (object)[
				'plugin'      => $pluginFile,
				'new_version' => self::NEW_VERSION,
			],
		];
	}

	private function pluginUpdateItem( string $pluginFile ) :\stdClass {
		return (object)[ 'plugin' => $pluginFile ];
	}
}

class AutoUpdatesConTestConfig {

	public array $properties = [
		'autoupdate_days' => 5,
	];
}

class AutoUpdatesConTestOptions {

	private array $values;

	public array $setCounts = [];

	public function __construct( array $overrides = [] ) {
		$this->values = \array_merge( [
			'autoupdate_plugin_self' => 'auto',
			'delay_tracking'         => [],
			'update_delay'           => 0,
		], $overrides );
	}

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? null;
	}

	public function optSet( string $key, $value ) :self {
		$this->setCounts[ $key ] = ( $this->setCounts[ $key ] ?? 0 ) + 1;
		$this->values[ $key ] = $value;
		return $this;
	}
}

class AutoUpdatesConTestScans {

	private AutoUpdatesConTestWpv $wpv;

	public function __construct( AutoUpdatesConTestWpv $wpv ) {
		$this->wpv = $wpv;
	}

	public function WPV() :AutoUpdatesConTestWpv {
		return $this->wpv;
	}
}

class AutoUpdatesConTestWpv {

	public array $vulnerabilityChecks = [];

	private bool $autoupdatesEnabled;

	private array $vulnerablePlugins;

	public function __construct( bool $autoupdatesEnabled = false, array $vulnerablePlugins = [] ) {
		$this->autoupdatesEnabled = $autoupdatesEnabled;
		$this->vulnerablePlugins = $vulnerablePlugins;
	}

	public function isAutoupdatesEnabled() :bool {
		return $this->autoupdatesEnabled;
	}

	public function hasVulnerabilities( string $pluginFile ) :bool {
		$this->vulnerabilityChecks[] = $pluginFile;
		return \in_array( $pluginFile, $this->vulnerablePlugins, true );
	}
}

class AutoUpdatesConTestThemes extends Themes {

	private array $updates;

	public function __construct( array $updates ) {
		$this->updates = $updates;
	}

	public function getUpdateInfo( $slug ) {
		return $this->updates[ $slug ] ?? null;
	}
}
