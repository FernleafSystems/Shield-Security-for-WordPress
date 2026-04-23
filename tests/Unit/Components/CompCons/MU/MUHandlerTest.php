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
}

class MUHandlerFsStub extends Fs {

	private array $files = [];

	public function isDir( string $path ) :bool {
		unset( $path );
		return true;
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		unset( $compress );
		$this->files[ $path ] = $contents;
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
