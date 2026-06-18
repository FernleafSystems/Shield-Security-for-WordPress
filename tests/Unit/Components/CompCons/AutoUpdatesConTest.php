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
