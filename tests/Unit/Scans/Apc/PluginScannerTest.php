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

	public function test_valid_raw_slug_uses_vo_resolved_slug_for_wp_org_lookup() :void {
		$lastUpdatedAt = self::NOW - self::ABANDONED_LIMIT - 1;
		$this->expectPluginApi(
			$this->apiResponse( 'premium-cornerstone', '7.8.12', $lastUpdatedAt ),
			'premium-cornerstone'
		);

		$result = $this->scan( new PluginScannerTestVo( 'premium-cornerstone', '7.8.12', true, [
			'slug'    => 'cornerstone',
			'Version' => '7.8.12',
		] ) );

		$this->assertSame( $lastUpdatedAt, $result[ 'last_updated_at' ] ?? null );
	}

	public function test_update_uri_bypasses_wp_org_api() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan(
			new PluginScannerTestVo( 'cornerstone', '7.8.12' ),
			[ 'UpdateURI' => 'https://theme.co/cornerstone' ]
		);

		$this->assertSame( [], $result );
	}

	public function test_legacy_update_uri_bypasses_wp_org_api() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan(
			new PluginScannerTestVo( 'cornerstone', '7.8.12' ),
			[ 'Update URI' => 'https://theme.co/cornerstone' ]
		);

		$this->assertSame( [], $result );
	}

	public function test_whitespace_update_uris_are_absent() :void {
		$lastUpdatedAt = self::NOW - self::ABANDONED_LIMIT - 1;
		$this->expectPluginApi( $this->apiResponse( 'cornerstone', '0.8.1', $lastUpdatedAt ) );

		$result = $this->scan(
			new PluginScannerTestVo( 'cornerstone', '0.8.1' ),
			[
				'UpdateURI'  => " \t ",
				'Update URI' => "\n",
			]
		);

		$this->assertSame( $lastUpdatedAt, $result[ 'last_updated_at' ] ?? null );
	}

	/**
	 * @dataProvider provideMalformedUpdateUris
	 */
	public function test_malformed_update_uri_is_conservatively_ineligible( string $key, $value ) :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan(
			new PluginScannerTestVo( 'cornerstone', '0.8.1' ),
			[ $key => $value ]
		);

		$this->assertSame( [], $result );
	}

	public function provideMalformedUpdateUris() :array {
		return [
			'modern null'  => [ 'UpdateURI', null ],
			'modern array' => [ 'UpdateURI', [] ],
			'legacy bool'  => [ 'Update URI', false ],
			'legacy int'   => [ 'Update URI', 12 ],
		];
	}

	public function test_non_wp_org_plugin_bypasses_wp_org_api() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '7.8.12', false ) );

		$this->assertSame( [], $result );
	}

	public function test_wp_org_shaped_id_still_respects_vo_eligibility_contract() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan(
			new PluginScannerTestVo( 'cornerstone', '0.8.1', false, null, 'w.org/plugins/cornerstone' )
		);

		$this->assertSame( [], $result );
	}

	/**
	 * @dataProvider provideMalformedWpOrgIds
	 */
	public function test_malformed_wp_org_id_never_reaches_api_or_string_conversion( $id ) :void {
		PluginScannerStringableValue::$calls = 0;
		Functions\expect( 'plugins_api' )->never();

		try {
			$this->assertSame(
				[],
				$this->scan( new PluginScannerTestVo( 'cornerstone', '0.8.1', true, null, $id ) )
			);
			$this->assertSame( 0, PluginScannerStringableValue::$calls );
		}
		finally {
			\is_resource( $id ) && \fclose( $id );
		}
	}

	public function provideMalformedWpOrgIds() :array {
		return [
			'null'               => [ null ],
			'boolean'            => [ true ],
			'integer'            => [ 12 ],
			'float'              => [ 1.2 ],
			'array'              => [ [] ],
			'object'             => [ new \stdClass() ],
			'string-convertible' => [ new PluginScannerStringableValue() ],
			'resource'           => [ \fopen( 'php://memory', 'rb' ) ],
		];
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
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '' ) );

		$this->assertSame( [], $result );
	}

	/**
	 * @dataProvider provideWrongTypeFamily
	 */
	public function test_installed_slug_wrong_type_family_never_reaches_api( $value ) :void {
		PluginScannerStringableValue::$calls = 0;
		Functions\expect( 'plugins_api' )->never();
		$plugin = new PluginScannerTestVo( 'cornerstone', '0.8.1', true, [
			'slug'    => $value,
			'Version' => '0.8.1',
		] );

		try {
			$this->assertSame( [], $this->scan( $plugin ) );
			$this->assertSame( 0, PluginScannerStringableValue::$calls );
		}
		finally {
			\is_resource( $value ) && \fclose( $value );
		}
	}

	public function provideWrongTypeFamily() :array {
		return [
			'null'                  => [ null ],
			'boolean'               => [ true ],
			'integer'               => [ 12 ],
			'float'                 => [ 1.2 ],
			'array'                 => [ [] ],
			'object'                => [ new \stdClass() ],
			'string-convertible'    => [ new PluginScannerStringableValue() ],
			'resource'              => [ \fopen( 'php://memory', 'rb' ) ],
		];
	}

	public function test_malformed_raw_installed_version_never_reaches_api() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '0.8.1', true, [
			'Version' => new \stdClass(),
		] ) );

		$this->assertSame( [], $result );
	}

	public function test_missing_raw_installed_version_never_uses_vendor_cast_path() :void {
		Functions\expect( 'plugins_api' )->never();

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '0.8.1', true, [] ) );

		$this->assertSame( [], $result );
	}

	/**
	 * @dataProvider provideMalformedApiFields
	 */
	public function test_each_malformed_api_field_produces_no_finding_after_one_api_call( string $field ) :void {
		$response = $this->apiResponse( 'cornerstone', '0.8.1', self::NOW - self::ABANDONED_LIMIT - 1 );
		$response->{$field} = new \stdClass();
		$this->expectPluginApi( $response );

		$result = $this->scan( new PluginScannerTestVo( 'cornerstone', '0.8.1' ) );

		$this->assertSame( [], $result );
	}

	public function provideMalformedApiFields() :array {
		return [
			'slug'         => [ 'slug' ],
			'version'      => [ 'version' ],
			'last updated' => [ 'last_updated' ],
		];
	}

	public function test_trimmed_installed_and_api_strings_preserve_valid_finding() :void {
		$lastUpdatedAt = self::NOW - self::ABANDONED_LIMIT - 1;
		$this->expectPluginApi( (object)[
			'slug'         => ' cornerstone ',
			'version'      => ' 0.8.1 ',
			'last_updated' => ' '.$this->date( $lastUpdatedAt ).' ',
		] );

		$result = $this->scan( new PluginScannerTestVo( ' cornerstone ', 'fallback-unused', true, [
			'slug'    => ' cornerstone ',
			'Version' => ' 0.8.1 ',
		] ) );

		$this->assertSame( $lastUpdatedAt, $result[ 'last_updated_at' ] ?? null );
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

	private function expectPluginApi( $response, string $slug = 'cornerstone' ) :void {
		Functions\expect( 'plugins_api' )
			->once()
			->with( 'plugin_information', [
				'slug'   => $slug,
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

	private $pluginSlug;

	private $pluginVersion;

	private bool $isWpOrg;

	private $pluginId;

	public function __construct( $slug, $version, bool $isWpOrg = true, ?array $raw = null, $pluginId = null ) {
		$this->pluginSlug = $slug;
		$this->pluginVersion = $version;
		$this->isWpOrg = $isWpOrg;
		$this->pluginId = \func_num_args() >= 5
			? $pluginId
			: ( $isWpOrg ? 'w.org/plugins/cornerstone' : 'example.com/plugins/cornerstone' );
		$this->applyFromArray( $raw ?? [ 'Version' => $version ] );
	}

	public function __get( string $key ) {
		return $key === 'slug'
			? $this->pluginSlug
			: ( $key === 'Version' ? $this->pluginVersion : ( $key === 'id' ? $this->pluginId : null ) );
	}

	public function isWpOrg() :bool {
		return $this->isWpOrg;
	}
}

class PluginScannerStringableValue {

	public static int $calls = 0;

	public function __toString() :string {
		self::$calls++;
		return 'cornerstone';
	}
}
