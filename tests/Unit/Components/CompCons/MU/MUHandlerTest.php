<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU;

if ( !\function_exists( __NAMESPACE__.'\\path_join' ) ) {
	function path_join( string $base, string $path ) :string {
		return \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' );
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\MU;

use function Brain\Monkey\Actions\expectAdded;
use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\MUHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\{
	Fs,
	General,
	Rest
};

class MUHandlerTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();

		if ( !\defined( 'WPMU_PLUGIN_DIR' ) ) {
			\define( 'WPMU_PLUGIN_DIR', 'vfs/mu-plugins' );
		}
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testExecuteRegistersOptionsStoreHookOnce() :void {
		[ $con ] = $this->installHookController( true, [] );
		$handler = new MUHandler();

		expectAdded( $con->prefix( 'pre_options_store' ) )
			->once()
			->with( [ $handler, 'rewriteOnWhiteLabelOptionSave' ], 10, 1 );

		$handler->execute();
		$handler->execute();

		$this->assertSame( 10, has_action( $con->prefix( 'pre_options_store' ), [ $handler, 'rewriteOnWhiteLabelOptionSave' ] ) );
	}

	public function testWhiteLabelOptionChangeRewritesMuFileWhenMuEnabled() :void {
		[ , $fs ] = $this->installHookController( true, [ 'wl_pluginnamemain' ] );

		( new MUHandler() )->rewriteOnWhiteLabelOptionSave();

		$this->assertSame( 1, $fs->writeCount() );
	}

	public function testWhiteLabelOptionChangeDoesNotRewriteMuFileWhenMuDisabled() :void {
		[ , $fs ] = $this->installHookController( false, [ 'wl_pluginnamemain' ] );

		( new MUHandler() )->rewriteOnWhiteLabelOptionSave();

		$this->assertSame( 0, $fs->writeCount() );
	}

	public function testNonWhiteLabelOptionChangeDoesNotRewriteMuFile() :void {
		[ , $fs ] = $this->installHookController( true, [ 'non_whitelabel_option' ] );

		( new MUHandler() )->rewriteOnWhiteLabelOptionSave();

		$this->assertSame( 0, $fs->writeCount() );
	}

	public function testCachedLabelsAreInvalidatedBeforeMuFileRewrite() :void {
		$cachedLabels = (object)[
			'Name'      => 'cached',
			'PluginURI' => 'cached',
			'Author'    => 'cached',
		];
		[ $con, $fs ] = $this->installHookController( true, [ 'wl_pluginnamemain' ], $cachedLabels );

		( new MUHandler() )->rewriteOnWhiteLabelOptionSave();

		$this->assertSame( 1, $fs->writeCount() );
		$this->assertNotSame( $cachedLabels, $con->labels );
	}

	public function testConvertToMuUsesPluginLoopbackHelperWithoutCallingRestRoute() :void {
		$fs = new MUHandlerFsStub();
		UnitTestControllerFactory::install( null, null, (object)[
			'plugin'    => new MUHandlerLoopbackPluginStub( true ),
			'root_file' => 'vfs/wp-content/plugins/wp-simple-firewall/icwp-wpsf.php',
			'labels'    => (object)[
				'Name'      => 'Shield',
				'PluginURI' => 'https://example.test/shield',
				'Author'    => 'Shield',
			],
		] );
		ServicesState::installItems( [
			'service_wpfs'      => $fs,
			'service_wpgeneral' => new MUHandlerWpGeneralStub(),
			'service_rest'      => new class extends Rest {
				public function callInternal( array $req ) :\WP_REST_Response {
					unset( $req );
					throw new \RuntimeException( 'REST loopback route should not be called.' );
				}
			},
		] );

		$this->assertTrue( ( new MUHandler() )->convertToMU() );
	}

	public function testConvertToMuRevertsFileWhenPluginLoopbackHelperFails() :void {
		$fs = new MUHandlerFsStub();
		UnitTestControllerFactory::install( null, null, (object)[
			'plugin'    => new MUHandlerLoopbackPluginStub( false ),
			'root_file' => 'vfs/wp-content/plugins/wp-simple-firewall/icwp-wpsf.php',
			'labels'    => (object)[
				'Name'      => 'Shield',
				'PluginURI' => 'https://example.test/shield',
				'Author'    => 'Shield',
			],
		] );
		ServicesState::installItems( [
			'service_wpfs'      => $fs,
			'service_wpgeneral' => new MUHandlerWpGeneralStub(),
		] );

		try {
			( new MUHandler() )->convertToMU();
			$this->fail( 'Expected MU conversion to fail when loopback helper fails.' );
		}
		catch ( \Exception $e ) {
			$this->assertFalse( $fs->hasFile( WPMU_PLUGIN_DIR.'/'.MUHandler::PLUGIN_FILE_NAME ) );
		}
	}

	private function installHookController(
		bool $muEnabled,
		array $changedOptions,
		object $cachedLabels = null
	) :array {
		$fs = new MUHandlerFsStub();
		$opts = new MUHandlerOptionsStub( $muEnabled, $changedOptions );
		$controllerExtras = [
			'plugin'         => new MUHandlerLoopbackPluginStub( true ),
			'root_file'      => 'vfs/wp-content/plugins/wp-simple-firewall/icwp-wpsf.php',
			'cfg'            => (object)[
				'properties'    => [
					'slug_parent' => 'icwp',
					'slug_plugin' => 'wpsf',
				],
				'labels'        => [
					'Name'      => 'Shield',
					'Title'     => 'Shield',
					'MenuTitle' => 'Shield',
					'PluginURI' => 'https://example.test/shield',
					'Author'    => 'Shield',
				],
				'configuration' => (object)[
					'options' => [
						'wl_pluginnamemain'     => [
							'section' => 'section_whitelabel',
						],
						'whitelabel_enable'     => [
							'section' => 'section_whitelabel',
						],
						'non_whitelabel_option' => [
							'section' => 'section_defaults',
						],
					],
				],
			],
			'opts'           => $opts,
			'modules_loaded' => true,
			'comps'          => (object)[
				'license' => new MUHandlerLicenseStub(),
			],
		];

		if ( $cachedLabels !== null ) {
			$controllerExtras[ 'labels' ] = $cachedLabels;
		}

		$con = UnitTestControllerFactory::install( null, null, (object)$controllerExtras );
		ServicesState::installItems( [
			'service_wpfs'      => $fs,
			'service_wpgeneral' => new MUHandlerWpGeneralStub(),
		] );

		return [ $con, $fs, $opts ];
	}
}

class MUHandlerFsStub extends Fs {

	private array $files = [];

	private int $writeCount = 0;

	public function isDir( string $path ) :bool {
		unset( $path );
		return true;
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		unset( $compress );
		$this->files[ $path ] = $contents;
		++$this->writeCount;
		return true;
	}

	public function isAccessibleFile( string $path ) :bool {
		return isset( $this->files[ $path ] );
	}

	public function getFileContent( $path, $uncompress = false ) {
		unset( $uncompress );
		if ( \basename( $path ) === '.mu-template.txt' ) {
			return 'SHIELD_ROOT_FILE SHIELD_PLUGIN_NAME SHIELD_PLUGIN_URL SHIELD_PLUGIN_AUTHOR';
		}
		return $this->files[ $path ] ?? null;
	}

	public function deleteFile( $path ) {
		unset( $this->files[ $path ] );
		return true;
	}

	public function hasFile( string $path ) :bool {
		return isset( $this->files[ $path ] );
	}

	public function writeCount() :int {
		return $this->writeCount;
	}
}

class MUHandlerWpGeneralStub extends General {

	public function getWordpressIsAtLeastVersion( string $version, bool $ignoreCP = true ) :bool {
		unset( $version, $ignoreCP );
		return true;
	}
}

class MUHandlerLoopbackPluginStub extends ModCon {

	private bool $canLoopback;

	public function __construct( bool $canLoopback ) {
		$this->canLoopback = $canLoopback;
	}

	public function canSiteLoopback() :bool {
		return $this->canLoopback;
	}
}

class MUHandlerOptionsStub {

	private bool $muEnabled;

	private array $changedOptions;

	public function __construct( bool $muEnabled, array $changedOptions ) {
		$this->muEnabled = $muEnabled;
		$this->changedOptions = $changedOptions;
	}

	public function optIs( string $key, $value ) :bool {
		return $key === 'enable_mu' && ( $this->muEnabled ? 'Y' : 'N' ) === $value;
	}

	public function optChanged( string $key ) :bool {
		return \in_array( $key, $this->changedOptions, true );
	}

	public function optSet( string $key, $value ) :self {
		if ( $key === 'enable_mu' ) {
			$this->muEnabled = $value === 'Y';
		}
		return $this;
	}
}

class MUHandlerLicenseStub {

	public function hasValidWorkingLicense() :bool {
		return false;
	}
}
