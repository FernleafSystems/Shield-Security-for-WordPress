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
			$opts->optGet( 'delay_tracking' )[ 'plugins' ][ self::BASE_FILE ][ self::NEW_VERSION ] ?? null
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
