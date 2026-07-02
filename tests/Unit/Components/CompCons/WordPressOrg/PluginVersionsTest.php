<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\WordPressOrg;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\WordPressOrg\PluginVersions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\General;

class PluginVersionsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testReleaseUrlsNormalizeWordpressOrgVersionMap() :void {
		$subject = new PluginVersions( 'wp-simple-firewall', [
			'trunk'          => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.zip',
			'importexport-b2' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.importexport-b2.zip',
			'24'             => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.zip',
			'24.0.0-beta1'   => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0-beta1.zip',
			'22.1.3'         => ' https://downloads.wordpress.org/plugin/wp-simple-firewall.22.1.3.zip ',
			' 23.0 '         => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.23.0.zip',
			'21.9.9'         => '',
			99               => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.99.zip',
			'25.0.0'         => false,
		] );

		$this->assertSame(
			[
				'22.1.3' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.22.1.3.zip',
				'23.0'   => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.23.0.zip',
			],
			$subject->releaseUrls()
		);
	}

	public function testReleaseUrlsUseCachedWordpressOrgVersionMap() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new PluginVersionsGeneralStub( [
				$this->cacheKeyForSlug( 'wp-simple-firewall' ) => [
					'trunk'  => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.zip',
					'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
				],
			] ),
		] );

		$subject = new PluginVersionsLoadStub(
			'wp-simple-firewall',
			[
				'25.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.25.0.0.zip',
			]
		);

		$this->assertSame(
			[
				'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
			],
			$subject->releaseUrls()
		);
		$this->assertSame( 0, $subject->loadCount );
	}

	public function testReleaseUrlsNormalizeSlugBeforeReadingCache() :void {
		ServicesState::installItems( [
			'service_wpgeneral' => new PluginVersionsGeneralStub( [
				$this->cacheKeyForSlug( 'wp-simple-firewall' ) => [
					'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
				],
			] ),
		] );

		$subject = new PluginVersionsLoadStub(
			' wp-simple-firewall ',
			[
				'25.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.25.0.0.zip',
			]
		);

		$this->assertSame(
			[
				'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
			],
			$subject->releaseUrls()
		);
		$this->assertSame( 0, $subject->loadCount );
	}

	public function testReleaseUrlsDoNotLoadOrCacheWhenSlugIsEmpty() :void {
		$general = new PluginVersionsGeneralStub();
		ServicesState::installItems( [
			'service_wpgeneral' => $general,
		] );

		$subject = new PluginVersionsLoadStub(
			' ',
			[
				'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
			]
		);

		$this->assertSame( [], $subject->releaseUrls() );
		$this->assertSame( 0, $subject->loadCount );
		$this->assertSame( '', $general->lastStoredTransientKey );
	}

	public function testReleaseUrlsNormalizeProvidedMapWhenSlugIsEmpty() :void {
		$subject = new PluginVersions( '', [
			'24.0.0'       => ' https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip ',
			'importexport' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.importexport.zip',
		] );

		$this->assertSame(
			[
				'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
			],
			$subject->releaseUrls()
		);
	}

	public function testReleaseUrlsCacheLoadedWordpressOrgVersionMapForTenMinutes() :void {
		$general = new PluginVersionsGeneralStub();
		ServicesState::installItems( [
			'service_wpgeneral' => $general,
		] );

		$subject = new PluginVersionsLoadStub(
			'wp-simple-firewall',
			[
				'trunk'  => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.zip',
				'24.0.0' => ' https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip ',
			]
		);

		$this->assertSame(
			[
				'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
			],
			$subject->releaseUrls()
		);
		$this->assertSame( 1, $subject->loadCount );
		$this->assertSame( $this->cacheKeyForSlug( 'wp-simple-firewall' ), $general->lastStoredTransientKey );
		$this->assertSame(
			[
				'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
			],
			$general->lastStoredTransientValue
		);
		$this->assertSame( \MINUTE_IN_SECONDS*10, $general->lastStoredTransientLifetime );
	}

	public function testLatestVersionNewerThanReturnsHighestNormalizedRelease() :void {
		$subject = $this->fromVersions( [
			'22.1.4',
			'24.0.0',
			'23.0.0',
			'24.0.0-beta1',
		] );

		$this->assertSame( '24.0.0', $subject->latestVersionNewerThan( '22.1.3' ) );
	}

	public function testLatestVersionNewerThanReturnsNullForInvalidCurrentVersion() :void {
		$this->assertNull(
			$this->fromVersions( [ '23.0.0', '24.0.0' ] )
			     ->latestVersionNewerThan( 'importexport-b2' )
		);
	}

	/**
	 * @dataProvider provideReleaseVersionNormalizationCases
	 */
	public function testNormalizeReleaseVersionOnlyAcceptsDottedNumericScalars( $version, string $expected ) :void {
		$this->assertSame( $expected, PluginVersions::normalizeReleaseVersion( $version ) );
	}

	public static function provideReleaseVersionNormalizationCases() :array {
		return [
			'standard version'  => [ '22.1.3', '22.1.3' ],
			'padded version'    => [ ' 22.1.3 ', '22.1.3' ],
			'two-part version'  => [ '23.0', '23.0' ],
			'major-only tag'    => [ '24', '' ],
			'beta suffix'       => [ '24.0.0-beta1', '' ],
			'non-version tag'   => [ 'importexport-b2', '' ],
			'non-scalar value'  => [ [], '' ],
			'empty value'       => [ '', '' ],
		];
	}

	/**
	 * @dataProvider provideMajorVersionCases
	 */
	public function testDetectsAtLeastTwoNewerMajorVersions( array $versions, string $currentVersion, bool $expected ) :void {
		$this->assertSame(
			$expected,
			$this->fromVersions( $versions )->hasAtLeastTwoNewerMajorVersions( $currentVersion )
		);
	}

	public static function provideMajorVersionCases() :array {
		return [
			'non-version tags are ignored'              => [
				[ 'importexport-b2', '22.1.3', '22.1.2' ],
				'22.1.3',
				false,
			],
			'tags without dots are ignored'             => [
				[ '24', '25.0.0' ],
				'23.0.0',
				false,
			],
			'duplicate newer major versions count once' => [
				[ '23.0.0', '23.1.0', '23.2.0', '22.9.9' ],
				'22.1.3',
				false,
			],
			'two newer major versions are too old'      => [
				[ '24.0.0', '23.0.0', '22.9.9' ],
				'22.1.3',
				true,
			],
			'invalid current version is not too old'    => [
				[ '24.0.0', '25.0.0' ],
				'importexport-b2',
				false,
			],
			'empty current version is not too old'      => [
				[ '24.0.0', '25.0.0' ],
				'',
				false,
			],
			'suffixed tags are not release versions'    => [
				[ '24.0.0-beta1', '23.0.0-rc1' ],
				'22.1.3',
				false,
			],
		];
	}

	private function fromVersions( array $versions ) :PluginVersions {
		$versionUrls = [];
		foreach ( $versions as $version ) {
			$versionUrls[ $version ] = sprintf(
				'https://downloads.wordpress.org/plugin/wp-simple-firewall.%s.zip',
				(string)$version
			);
		}

		return new PluginVersions( 'wp-simple-firewall', $versionUrls );
	}

	private function cacheKeyForSlug( string $slug ) :string {
		return 'apto-shield-wporg-plugin-versions-'.\md5( $slug );
	}
}

class PluginVersionsLoadStub extends PluginVersions {

	public int $loadCount = 0;

	private array $loadedVersionUrls;

	public function __construct( string $slug, array $loadedVersionUrls ) {
		parent::__construct( $slug );
		$this->loadedVersionUrls = $loadedVersionUrls;
	}

	protected function loadVersionUrls() :array {
		$this->loadCount++;
		return $this->loadedVersionUrls;
	}
}

class PluginVersionsGeneralStub extends General {

	private array $transients;

	public string $lastStoredTransientKey = '';

	public $lastStoredTransientValue = null;

	public int $lastStoredTransientLifetime = 0;

	public function __construct( array $transients = [] ) {
		$this->transients = $transients;
	}

	public function canUseTransients() :bool {
		return true;
	}

	public function getTransient( $sKey ) {
		return $this->transients[ $sKey ] ?? null;
	}

	public function setTransient( $sKey, $mValue, $nExpire = 0 ) {
		$this->lastStoredTransientKey = $sKey;
		$this->lastStoredTransientValue = $mValue;
		$this->lastStoredTransientLifetime = $nExpire;
		$this->transients[ $sKey ] = $mValue;
		return true;
	}
}
