<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\PluginNotices;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\ConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\SelfVersion;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Utilities\HttpRequest;

class SelfVersionTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	/**
	 * @dataProvider provideVersionLists
	 */
	public function testMajorVersionAgeCheckHandlesExternalTagData( array $versions, string $currentVersion, bool $expected ) :void {
		$this->assertSame(
			$expected,
			$this->invokeVersionAgeCheck( $versions, $currentVersion )
		);
	}

	public function provideVersionLists() :array {
		return [
			'non-version tags are ignored'               => [
				[ 'importexport-b2', '22.1.3', '22.1.2' ],
				'22.1.3',
				false,
			],
			'tags without dots are ignored'              => [
				[ '24', '25.0.0' ],
				'23.0.0',
				false,
			],
			'non-string cached values are ignored'       => [
				[ '25.0.0', null, false, [ 'name' => '24.0.0' ], '24.0.1' ],
				'23.2.1',
				true,
			],
			'duplicate newer major versions count once'  => [
				[ '23.0.0', '23.1.0', '23.2.0', '22.9.9' ],
				'22.1.3',
				false,
			],
			'two newer major versions are too old'       => [
				[ '24.0.0', '23.0.0', '22.9.9' ],
				'22.1.3',
				true,
			],
			'invalid current version is not too old'     => [
				[ '24.0.0', '25.0.0' ],
				'importexport-b2',
				false,
			],
			'empty current version is not too old'       => [
				[ '24.0.0', '25.0.0' ],
				'',
				false,
			],
			'beta suffix after dotted version is allowed' => [
				[ '24.0.0-beta1', '23.0.0-rc1' ],
				'22.1.3',
				true,
			],
		];
	}

	/**
	 * @dataProvider provideMajorVersionExtraction
	 */
	public function testMajorVersionExtractionOnlyAcceptsDottedNumericVersions( string $version, ?int $expected ) :void {
		$method = new \ReflectionMethod( SelfVersion::class, 'extractMajorVersion' );
		$method->setAccessible( true );

		$this->assertSame(
			$expected,
			$method->invoke( new SelfVersion(), $version )
		);
	}

	public function provideMajorVersionExtraction() :array {
		return [
			'standard release'       => [ '22.1.3', 22 ],
			'two-part release'      => [ '23.0', 23 ],
			'beta release'          => [ '24.0.0-beta1', 24 ],
			'non-version tag'       => [ 'importexport-b2', null ],
			'major only tag'        => [ '24', null ],
			'leading v tag'         => [ 'v24.0.0', null ],
			'empty version'         => [ '', null ],
			'non-numeric prefix'    => [ 'release-24.0.0', null ],
		];
	}

	public function testCachedNonVersionReleaseTagsDoNotFatalInPluginTooOldCheck() :void {
		$this->installPluginTooOldEnvironment( '22.1.3', [ 'importexport-b2', '22.1.3', '22.1.2' ] );

		$this->assertFalse( $this->invokePluginTooOldCheck() );
	}

	public function testCachedMixedReleaseTagsStillDetectTwoNewerMajorVersions() :void {
		$this->installPluginTooOldEnvironment( '22.1.3', [ 'importexport-b2', '24.0.0', '23.0.0' ] );

		$this->assertTrue( $this->invokePluginTooOldCheck() );
	}

	public function testFetchedNonVersionReleaseTagsDoNotFatalWhenTransientIsEmpty() :void {
		$http = new SelfVersionHttpRequestStub( [
			[ 'name' => 'importexport-b2' ],
			[ 'name' => '22.1.3' ],
			[ 'name' => '22.1.2' ],
		] );

		$general = $this->installPluginTooOldEnvironment( '22.1.3', false, $http );

		$this->assertFalse( $this->invokePluginTooOldCheck() );
		$this->assertSame( 'https://api.github.com/repos/FernleafSystems/Shield-Security-for-WordPress/tags', $http->lastRequestedUrl );
		$this->assertSame( [ '22.1.2', '22.1.3', 'importexport-b2' ], \array_values( $general->lastStoredTransientValue ) );
		$this->assertSame( \HOUR_IN_SECONDS*6, $general->lastStoredTransientLifetime );
	}

	public function testCheckDoesNotFatalWithUpdateAvailableAndCachedNonVersionReleaseTags() :void {
		$this->installPluginTooOldEnvironment(
			'22.1.3',
			[ 'importexport-b2', '22.1.3', '22.1.2' ],
			null,
			new SelfVersionPluginsStub( true )
		);

		$issue = ( new SelfVersion() )->check();

		$this->assertIsArray( $issue );
		$this->assertSame( 'self_update_available', $issue[ 'id' ] );
		$this->assertSame( [ 'shield_admin_top_page' ], $issue[ 'locations' ] );
	}

	private function invokeVersionAgeCheck( array $versions, string $currentVersion ) :bool {
		$method = new \ReflectionMethod( SelfVersion::class, 'hasAtLeastTwoNewerMajorVersions' );
		$method->setAccessible( true );
		return $method->invoke( new SelfVersion(), $versions, $currentVersion );
	}

	private function invokePluginTooOldCheck() :bool {
		$method = new \ReflectionMethod( SelfVersion::class, 'isPluginTooOld' );
		$method->setAccessible( true );
		return $method->invoke( new SelfVersion() );
	}

	private function installPluginTooOldEnvironment(
		string $currentVersion,
		$transientValue,
		?HttpRequest $http = null,
		?Plugins $plugins = null
	) :SelfVersionGeneralStub {
		$labels = new Labels();
		$labels->Name = 'Shield Security';

		$cfg = ( new ConfigVO() )->applyFromArray( [
			'properties' => [
				'slug_parent' => 'icwp',
				'slug_plugin' => 'wpsf',
				'version'     => $currentVersion,
			],
		] );

		UnitTestControllerFactory::install(
			null,
			null,
			(object)[
				'base_file' => 'wp-simple-firewall/icwp-wpsf.php',
				'cfg'       => $cfg,
				'labels'    => $labels,
			]
		);

		$wpGeneral = new SelfVersionGeneralStub( $transientValue );
		$services = [
			'service_wpgeneral' => $wpGeneral,
		];

		if ( $http !== null ) {
			$services[ 'service_httprequest' ] = $http;
		}

		if ( $plugins !== null ) {
			$services[ 'service_wpplugins' ] = $plugins;
		}

		ServicesState::installItems( $services );

		return $wpGeneral;
	}
}

class SelfVersionGeneralStub extends General {

	private $transientValue;

	public $lastStoredTransientValue;

	public int $lastStoredTransientLifetime = 0;

	public function __construct( $transientValue ) {
		$this->transientValue = $transientValue;
	}

	public function canUseTransients() :bool {
		return true;
	}

	public function getTransient( $sKey ) {
		return $this->transientValue;
	}

	public function setTransient( $sKey, $mValue, $nExpire = 0 ) {
		$this->lastStoredTransientValue = $mValue;
		$this->lastStoredTransientLifetime = $nExpire;
		return true;
	}
}

class SelfVersionPluginsStub extends Plugins {

	private bool $updateAvailable;

	public function __construct( bool $updateAvailable ) {
		$this->updateAvailable = $updateAvailable;
	}

	public function isUpdateAvailable( $file ) :bool {
		return $this->updateAvailable;
	}

	public function getUrl_Upgrade( $file ) :string {
		return 'https://example.test/upgrade';
	}
}

class SelfVersionHttpRequestStub extends HttpRequest {

	public string $lastRequestedUrl = '';

	private array $tags;

	public function __construct( array $tags ) {
		$this->tags = $tags;
	}

	public function getContent( string $url, $args = [] ) :string {
		$this->lastRequestedUrl = $url;
		return (string)\json_encode( $this->tags );
	}
}
