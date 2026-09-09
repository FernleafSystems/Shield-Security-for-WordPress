<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\PluginNotices;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\WordPressOrg\PluginVersions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\ConfigVO;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Labels;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\SelfVersion;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

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

	public function testCheckReturnsNullWhenNoUpdateIsAvailable() :void {
		$this->installEnvironment( '22.1.3', false );

		$this->assertNull( ( new SelfVersion( new SelfVersionFailingPluginVersions() ) )->check() );
	}

	public function testCheckUsesStandardNoticeWhenWordpressOrgVersionsDoNotContainTwoNewerMajors() :void {
		$this->installEnvironment( '22.1.3', true );

		$issue = ( new SelfVersion( new PluginVersions( 'wp-simple-firewall', [
			'importexport-b2' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.importexport-b2.zip',
			'22.1.2'         => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.22.1.2.zip',
			'22.1.3'         => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.22.1.3.zip',
			'23.0.0-beta1'   => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.23.0.0-beta1.zip',
		] ) ) )->check();

		$this->assertIsArray( $issue );
		$this->assertSame( 'self_update_available', $issue[ 'id' ] );
		$this->assertSame( [ 'shield_admin_top_page' ], $issue[ 'locations' ] );
	}

	/**
	 * @dataProvider provideHostileReleaseVersionMembers
	 */
	public function testCheckUsesStandardNoticeForHostileReleaseVersionMembers( $hostileVersion ) :void {
		$this->installEnvironment( '22.1.3', true );

		$issue = ( new SelfVersion( new SelfVersionHostilePluginVersions( $hostileVersion ) ) )->check();

		$this->assertIsArray( $issue );
		$this->assertSame( 'self_update_available', $issue[ 'id' ] );
		$this->assertSame( [ 'shield_admin_top_page' ], $issue[ 'locations' ] );
	}

	public function provideHostileReleaseVersionMembers() :array {
		return [
			'null'  => [ null ],
			'false' => [ false ],
			'array' => [ [] ],
		];
	}

	public function testCheckUsesStandardNoticeWhenVersionLookupThrowsUnexpectedly() :void {
		$this->installEnvironment( '22.1.3', true );

		$issue = ( new SelfVersion( new SelfVersionFailingPluginVersions() ) )->check();

		$this->assertIsArray( $issue );
		$this->assertSame( 'self_update_available', $issue[ 'id' ] );
		$this->assertSame( [ 'shield_admin_top_page' ], $issue[ 'locations' ] );
	}

	public function testCheckUsesStandardNoticeWhenPluginVoIsUnavailable() :void {
		$this->installEnvironment( '22.1.3', true );

		$issue = ( new SelfVersion() )->check();

		$this->assertIsArray( $issue );
		$this->assertSame( 'self_update_available', $issue[ 'id' ] );
		$this->assertSame( [ 'shield_admin_top_page' ], $issue[ 'locations' ] );
	}

	public function testCheckUsesStandardNoticeWhenPluginVoSlugIsMalformed() :void {
		$this->installEnvironment(
			'22.1.3',
			true,
			new SelfVersionPluginVoStub( [
				'slug' => [],
			] )
		);

		$issue = ( new SelfVersion() )->check();

		$this->assertIsArray( $issue );
		$this->assertSame( 'self_update_available', $issue[ 'id' ] );
		$this->assertSame( [ 'shield_admin_top_page' ], $issue[ 'locations' ] );
	}

	public function testCheckUsesExpandedLocationsWhenWordpressOrgVersionsContainTwoNewerMajors() :void {
		$this->installEnvironment( '22.1.3', true );

		$issue = ( new SelfVersion( new PluginVersions( 'wp-simple-firewall', [
			'23.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.23.0.0.zip',
			'23.1.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.23.1.0.zip',
			'24.0.0' => 'https://downloads.wordpress.org/plugin/wp-simple-firewall.24.0.0.zip',
		] ) ) )->check();

		$this->assertIsArray( $issue );
		$this->assertSame( 'self_update_available', $issue[ 'id' ] );
		$this->assertSame( [ 'shield_admin_top_page', 'wp_admin' ], $issue[ 'locations' ] );
	}

	private function installEnvironment( string $currentVersion, bool $updateAvailable, ?WpPluginVo $plugin = null ) :void {
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

		ServicesState::installItems( [
			'service_wpplugins' => new SelfVersionPluginsStub( $updateAvailable, $plugin ),
		] );
	}
}

class SelfVersionPluginsStub extends Plugins {

	private bool $updateAvailable;

	private ?WpPluginVo $plugin;

	public function __construct( bool $updateAvailable, ?WpPluginVo $plugin = null ) {
		$this->updateAvailable = $updateAvailable;
		$this->plugin = $plugin;
	}

	public function isUpdateAvailable( $file ) :bool {
		return $this->updateAvailable;
	}

	public function getUrl_Upgrade( $file ) :string {
		return 'https://example.test/upgrade';
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $this->plugin;
	}
}

class SelfVersionPluginVoStub extends WpPluginVo {

	private array $values;

	public function __construct( array $values ) {
		$this->values = $values;
	}

	public function __get( string $key ) {
		return $this->values[ $key ] ?? null;
	}
}

class SelfVersionFailingPluginVersions extends PluginVersions {

	public function __construct() {
		parent::__construct( 'wp-simple-firewall', [] );
	}

	public function hasAtLeastTwoNewerMajorVersions( string $currentVersion ) :bool {
		throw new \RuntimeException( 'WP.org versions should not be consulted when no update is available.' );
	}
}

class SelfVersionHostilePluginVersions extends PluginVersions {

	private $hostileVersion;

	public function __construct( $hostileVersion ) {
		parent::__construct( 'wp-simple-firewall', [] );
		$this->hostileVersion = $hostileVersion;
	}

	public function releaseVersions() :array {
		return [ '23.0.0', $this->hostileVersion, '24.0.0' ];
	}
}
