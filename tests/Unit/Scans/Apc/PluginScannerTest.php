<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Apc;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc\{
	PluginScanner,
	ScanActionVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestRequest
};
use FernleafSystems\Wordpress\Services\Core\{
	Plugins,
	VOs\Assets\WpPluginVo
};

class PluginScannerTest extends BaseUnitTest {

	private const NOW = 1784280000;

	private const ABANDONED_LIMIT = 63072000;

	private array $servicesSnapshot;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'is_wp_error' )->alias( static fn( $value ) :bool => $value instanceof \WP_Error );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_newer_premium_version_does_not_match_older_wp_org_plugin() :void {
		$this->expectPluginApi( $this->apiResponse( 'cornerstone', '0.8.1', self::NOW - self::ABANDONED_LIMIT - 1 ) );

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '7.8.12' ) );

		$this->assertSame( [], $result );
	}

	public function test_update_uri_bypasses_wp_org_api() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan(
			new PluginScannerTestVo( 'cornerstone', '7.8.12' ),
			[ 'UpdateURI' => 'https://theme.co/cornerstone' ]
		);

		$this->assertSame( [], $result );
	}

	public function test_non_wp_org_plugin_bypasses_wp_org_api() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '7.8.12', false ) );

		$this->assertSame( [], $result );
	}

	/**
	 * @dataProvider provideEligibleInstalledVersions
	 */
	public function test_old_wp_org_plugin_remains_abandoned( string $installedVersion ) :void {
		$lastUpdatedAt = self::NOW - self::ABANDONED_LIMIT - 1;
		$this->expectPluginApi( $this->apiResponse( 'cornerstone', '0.8.1', $lastUpdatedAt ) );

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', $installedVersion ) );

		$this->assertSame( [
			'slug'            => 'cornerstone/cornerstone.php',
			'is_abandoned'    => true,
			'last_updated_at' => $lastUpdatedAt,
		], $result );
	}

	public function provideEligibleInstalledVersions() :array {
		return [
			'older than repository' => [ '0.7.0' ],
			'equal to repository'   => [ '0.8.1' ],
		];
	}

	/**
	 * @dataProvider provideUntrustedApiResponses
	 */
	public function test_untrusted_wp_org_response_produces_no_finding( $apiResponse ) :void {
		$this->expectPluginApi( $apiResponse );

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '0.8.1' ) );

		$this->assertSame( [], $result );
	}

	public function provideUntrustedApiResponses() :array {
		$oldDate = $this->date( self::NOW - self::ABANDONED_LIMIT - 1 );
		return [
			'WordPress error' => [ new \WP_Error( 'api_failed', 'API failed.' ) ],
			'non-object response' => [ [] ],
			'missing slug' => [ (object)[
				'version'      => '0.8.1',
				'last_updated' => $oldDate,
			] ],
			'slug mismatch' => [ (object)[
				'slug'         => 'unrelated-plugin',
				'version'      => '0.8.1',
				'last_updated' => $oldDate,
			] ],
			'missing version' => [ (object)[
				'slug'         => 'cornerstone',
				'last_updated' => $oldDate,
			] ],
			'invalid date' => [ (object)[
				'slug'         => 'cornerstone',
				'version'      => '0.8.1',
				'last_updated' => 'not-a-date',
			] ],
		];
	}

	public function test_missing_installed_version_produces_no_finding() :void {
		$this->expectPluginApi( $this->apiResponse( 'cornerstone', '0.8.1', self::NOW - self::ABANDONED_LIMIT - 1 ) );

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '' ) );

		$this->assertSame( [], $result );
	}

	public function test_recent_wp_org_update_produces_no_finding() :void {
		$this->expectPluginApi( $this->apiResponse( 'cornerstone', '0.8.1', self::NOW - self::ABANDONED_LIMIT + 1 ) );

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '0.8.1' ) );

		$this->assertSame( [], $result );
	}

	private function scan( WpPluginVo $plugin, array $pluginData = [] ) :array {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', self::NOW ),
			'service_wpplugins' => new PluginScannerTestPlugins( $plugin, $pluginData ),
		] );
		$action = new ScanActionVO();
		$action->abandoned_limit = self::ABANDONED_LIMIT;

		return ( new PluginScanner() )
			->setScanActionVO( $action )
			->scan( 'cornerstone/cornerstone.php' );
	}

	private function expectPluginApi( $response ) :void {
		Functions\expect( 'plugins_api' )
			->once()
			->with( 'plugin_information', [
				'slug'   => 'cornerstone',
				'fields' => [
					'sections' => false,
				],
			] )
			->andReturn( $response );
	}

	private function apiResponse( string $slug, string $version, int $lastUpdatedAt ) :\stdClass {
		return (object)[
			'slug'         => $slug,
			'version'      => $version,
			'last_updated' => $this->date( $lastUpdatedAt ),
		];
	}

	private function date( int $timestamp ) :string {
		return \gmdate( 'c', $timestamp );
	}
}

class PluginScannerTestPlugins extends Plugins {

	private WpPluginVo $plugin;

	private array $pluginData;

	public function __construct( WpPluginVo $plugin, array $pluginData ) {
		$this->plugin = $plugin;
		$this->pluginData = $pluginData;
	}

	public function getPlugin( $file ) :array {
		return $this->pluginData;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $this->plugin;
	}
}

class PluginScannerTestVo extends WpPluginVo {

	private string $pluginSlug;

	private string $pluginVersion;

	private bool $isWpOrg;

	public function __construct( string $slug, string $version, bool $isWpOrg = true ) {
		$this->pluginSlug = $slug;
		$this->pluginVersion = $version;
		$this->isWpOrg = $isWpOrg;
	}

	public function __get( string $key ) {
		return $key === 'slug' ? $this->pluginSlug : ( $key === 'Version' ? $this->pluginVersion : null );
	}

	public function isWpOrg() :bool {
		return $this->isWpOrg;
	}
}
