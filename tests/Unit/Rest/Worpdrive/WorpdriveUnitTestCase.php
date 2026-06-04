<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures\{
	WorpdriveTestCacheDirHandler,
	WorpdriveTestConfig,
	WorpdriveTestRequest,
	WorpdriveTestUrls
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};
use FernleafSystems\WorpdriveClient\Host\WorpdriveRuntime;

abstract class WorpdriveUnitTestCase extends BaseUnitTest {

	protected array $servicesSnapshot = [];

	protected array $tempPaths = [];

	protected function setUp() :void {
		parent::setUp();

		require_once \dirname( __DIR__, 3 ).'/Helpers/PluginControllerShim.php';
		require_once \dirname( __DIR__, 3 ).'/bootstrap/worpdrive/WPTheme.php';
		require_once \dirname( __DIR__, 3 ).'/bootstrap/worpdrive/Wpdb.php';

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_request' => new WorpdriveTestRequest(),
		] );
		$this->registerWordPressPathFunctionMocks();
		$this->installController();
	}

	protected function tearDown() :void {
		WorpdriveRuntime::resetHost();
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( \array_reverse( $this->tempPaths ) as $path ) {
			$this->removePath( $path );
		}
		parent::tearDown();
	}

	protected function installController( ?string $pluginRoot = null, ?string $cacheDir = null ) :void {
		$pluginRoot = $pluginRoot ?? $this->tempDir( 'worpdrive-plugin' );
		$cacheDir = $cacheDir ?? $this->tempDir( 'worpdrive-cache' );

		$extras = new \stdClass();
		$extras->cfg = new WorpdriveTestConfig( '22.1-test' );
		$extras->urls = new WorpdriveTestUrls();
		$extras->cache_dir_handler = new WorpdriveTestCacheDirHandler( $cacheDir );
		$extras->root_file = $pluginRoot.'/shield.php';

		UnitTestControllerFactory::install( null, null, $extras );
	}

	protected function assertRuntimeReset() :void {
		try {
			WorpdriveRuntime::host();
			$this->fail( 'WorpDrive runtime host should be reset.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'WorpDrive host runtime has not been configured.', $e->getMessage() );
		}
	}

	protected function tempDir( string $prefix ) :string {
		$dir = $this->normalizePath( \sys_get_temp_dir().'/'.$prefix.'-'.\uniqid() );
		\mkdir( $dir, 0777, true );
		$this->tempPaths[] = $dir;
		return $dir;
	}

	protected function writeFile( string $path, string $contents ) :string {
		$path = $this->normalizePath( $path );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			\mkdir( $dir, 0777, true );
		}
		\file_put_contents( $path, $contents );
		$this->tempPaths[] = $path;
		return $path;
	}

	protected function removePath( string $path ) :void {
		if ( \is_file( $path ) ) {
			@\unlink( $path );
			return;
		}
		if ( !\is_dir( $path ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			/** @var \SplFileInfo $item */
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $path );
	}

	protected function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}

	private function registerWordPressPathFunctionMocks() :void {
		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalizePath( $path ) );
		Functions\when( 'trailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ).'/' );
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ) );
		Functions\when( 'path_join' )->alias(
			fn( string $base, string $path ) :string => \rtrim( $this->normalizePath( $base ), '/' ).'/'.\ltrim( $this->normalizePath( $path ), '/' )
		);
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => (string)\json_encode( $data ) );
		Functions\when( 'wp_mkdir_p' )->alias(
			static fn( string $path ) :bool => \is_dir( $path ) || @\mkdir( $path, 0777, true )
		);
		Functions\when( 'path_is_absolute' )->alias(
			fn( string $path ) :bool => \preg_match( '#^(?:[A-Za-z]:)?/#', $this->normalizePath( $path ) ) === 1
		);
		Functions\when( 'remove_query_arg' )->alias(
			static function ( $key, string $url ) :string {
				$parts = \parse_url( $url );
				$query = [];
				if ( \is_array( $parts ) && isset( $parts[ 'query' ] ) ) {
					\parse_str( $parts[ 'query' ], $query );
					unset( $query[ (string)$key ] );
					$url = \strtok( $url, '?' );
					if ( !empty( $query ) ) {
						$url .= '?'.\http_build_query( $query );
					}
				}
				return $url;
			}
		);
	}
}
