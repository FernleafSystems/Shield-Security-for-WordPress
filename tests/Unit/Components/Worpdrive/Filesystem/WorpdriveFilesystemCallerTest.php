<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\Worpdrive\Filesystem;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map\{
	Listing\AbstractFileListing,
	MapHandler,
	MapVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate\Zipper;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route\{
	FilesystemZip,
	RouteProcessorMap
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Services\Core\Fs;

class WorpdriveFilesystemCallerTest extends BaseUnitTest {

	private array $servicesSnapshot;

	private array $tempPaths = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();

		Functions\when( 'wp_normalize_path' )->alias( fn( string $path ) :string => $this->normalizePath( $path ) );
		Functions\when( 'trailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ).'/' );
		Functions\when( 'untrailingslashit' )->alias( fn( string $path ) :string => \rtrim( $this->normalizePath( $path ), '/' ) );
		Functions\when( 'path_join' )->alias(
			fn( string $base, string $path ) :string => \rtrim( $this->normalizePath( $base ), '/' ).'/'.\ltrim( $this->normalizePath( $path ), '/' )
		);
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) :string => (string)\json_encode( $data ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		foreach ( \array_reverse( $this->tempPaths ) as $path ) {
			$this->removePath( $path );
		}
		parent::tearDown();
	}

	/**
	 * @dataProvider invalidRequiredZipPayloadProvider
	 */
	public function test_filesystem_zip_route_rejects_invalid_required_paths_before_zip_handler( array $filePaths, string $expectedMessage ) :void {
		$request = new \WP_REST_Request( [
			'file_paths' => $filePaths,
			'dir'        => ABSPATH,
			'uuid'       => 'unit-test',
			'time_limit' => 30,
		] );

		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( $expectedMessage );

		( ( new RouteProcessorMap() )->map()[ FilesystemZip::class ] )( $request );
	}

	public function invalidRequiredZipPayloadProvider() :array {
		return [
			'invalid base64' => [
				[ \base64_encode( 'index.php' ), 'not valid base64' ],
				'Invalid encoded WorpDrive payload.',
			],
			'empty decoded'  => [
				[ \base64_encode( '' ) ],
				'Invalid encoded WorpDrive payload.',
			],
		];
	}

	/**
	 * @dataProvider invalidZipCreatePathProvider
	 */
	public function test_zipper_create_rejects_invalid_paths_before_archive_creation( string $path ) :void {
		$this->expectException( \InvalidArgumentException::class );

		( new Zipper( ABSPATH, [ $path ], $this->tempFilePath( 'worpdrive-invalid', '.zip' ) ) )->create();
	}

	public function invalidZipCreatePathProvider() :array {
		return [
			'traversal' => [ '../wp-config.php' ],
			'absolute'  => [ '/wp-content/index.php' ],
			'drive'     => [ 'C:\\site\\wp-content\\index.php' ],
			'nul'       => [ "wp-content/index.php\0.txt" ],
		];
	}

	public function test_zipper_create_accepts_normal_relative_paths_with_ziparchive() :void {
		if ( !\class_exists( \ZipArchive::class ) ) {
			$this->markTestSkipped( 'ZipArchive extension is not available in this environment.' );
		}

		ServicesState::mergeItems( [
			'service_wpfs' => new WorpdriveFilesystemCallerFs(),
		] );
		$baseDir = $this->tempDir( 'worpdrive-zip-base' );
		$nestedDir = path_join( $baseDir, 'wp-content/plugins/shield' );
		\mkdir( $nestedDir, 0777, true );
		\file_put_contents( path_join( $baseDir, 'index.php' ), 'root file' );
		\file_put_contents( path_join( $nestedDir, 'file.php' ), 'nested file' );
		$targetZip = $this->tempFilePath( 'worpdrive-valid', '.zip' );

		( new Zipper( $baseDir, [ 'index.php', 'wp-content/plugins/shield/file.php' ], $targetZip ) )->create();

		$zip = new \ZipArchive();
		$this->assertTrue( $zip->open( $targetZip ) );
		$this->assertNotFalse( $zip->locateName( 'index.php' ) );
		$this->assertNotFalse( $zip->locateName( 'wp-content/plugins/shield/file.php' ) );
		$this->assertSame( 'root file', $zip->getFromName( 'index.php' ) );
		$this->assertSame( 'nested file', $zip->getFromName( 'wp-content/plugins/shield/file.php' ) );
		$zip->close();
	}

	public function test_map_handler_ignores_invalid_and_empty_decoded_exclusions_while_preserving_valid_exclusions() :void {
		ServicesState::mergeItems( [
			'service_wpfs' => new WorpdriveFilesystemCallerFs(),
		] );
		$mapRoot = $this->tempDirUnderWpRoot( 'worpdrive-map-root' );
		\file_put_contents( path_join( $mapRoot, 'a-skip.txt' ), 'skip' );
		\file_put_contents( path_join( $mapRoot, 'z-keep.txt' ), 'keep' );
		$listing = new WorpdriveInMemoryFileListing();
		$mapVO = new MapVO();
		$mapVO->type = 'full';
		$mapVO->dir = trailingslashit( $mapRoot );
		$mapVO->exclusions = [
			'contains' => [
				\base64_encode( 'a-skip' ),
				'not valid base64',
				\base64_encode( '' ),
			],
			'regex'    => [
				\base64_encode( '#never-matches#' ),
				'also not base64',
				\base64_encode( '' ),
			],
		];
		$mapVO->maxFileSize = 1;
		$mapVO->hashAlgo = '';
		$workingRoot = $this->tempDirUnderWpRoot( 'worpdrive-map-work' );
		$workingDir = path_join( $workingRoot, 'archive' );
		\mkdir( $workingDir, 0777, true );
		$handler = new WorpdriveMapHandlerForTest(
			$mapVO,
			'unit-test',
			\time() - 1,
			$listing,
			$workingDir
		);

		$result = $handler->run();

		$this->assertSame( 1, $result[ 'map_count' ] );
		$this->assertCount( 1, $listing->paths() );
		$this->assertSame( 'z-keep.txt', \basename( $this->normalizePath( $listing->paths()[ 0 ] ) ) );
		$this->assertStringNotContainsString( 'a-skip.txt', $this->normalizePath( $listing->paths()[ 0 ] ) );
	}

	private function tempDir( string $prefix ) :string {
		$dir = \rtrim( $this->normalizePath( \sys_get_temp_dir() ), '/' ).'/'.$prefix.'-'.\uniqid();
		\mkdir( $dir, 0777, true );
		$this->tempPaths[] = $dir;
		return $dir;
	}

	private function tempDirUnderWpRoot( string $prefix ) :string {
		$dir = trailingslashit( ABSPATH ).$prefix.'-'.\uniqid();
		\mkdir( $dir, 0777, true );
		$this->tempPaths[] = $dir;
		return $dir;
	}

	private function tempFilePath( string $prefix, string $suffix ) :string {
		$path = \rtrim( $this->normalizePath( \sys_get_temp_dir() ), '/' ).'/'.$prefix.'-'.\uniqid().$suffix;
		$this->tempPaths[] = $path;
		return $path;
	}

	private function removePath( string $path ) :void {
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

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}

class WorpdriveMapHandlerForTest extends MapHandler {

	private AbstractFileListing $listing;

	private string $workingDirForTest;

	public function __construct(
		MapVO $mapVO,
		string $uuid,
		int $stopAtTS,
		AbstractFileListing $listing,
		string $workingDir
	) {
		$this->listing = $listing;
		$this->workingDirForTest = $workingDir;
		parent::__construct( $mapVO, $uuid, $stopAtTS );
	}

	protected function validate() :void {
	}

	protected function workingDir() :string {
		return trailingslashit( wp_normalize_path( $this->workingDirForTest ) );
	}

	protected function map() :AbstractFileListing {
		return $this->listing;
	}

	protected function mapForWpConfig() :void {
	}
}

class WorpdriveInMemoryFileListing extends AbstractFileListing {

	private array $items = [];

	public function __construct() {
		parent::__construct( '' );
	}

	public function startLargeListing() :void {
	}

	public function finishLargeListing( bool $successfulCreation ) :void {
	}

	public function addRaw( string $path, string $hash = '', string $hashAlt = '', ?int $mtime = null, ?int $size = null ) :void {
		$this->items[] = $path;
	}

	public function paths() :array {
		return $this->items;
	}
}

class WorpdriveFilesystemCallerFs extends Fs {

	private ?object $filesystem = null;

	public function fs() {
		return $this->filesystem ??= new class {
			public function is_readable( string $path ) :bool {
				return \is_readable( $path );
			}

			public function mtime( string $path ) :int {
				return (int)\filemtime( $path );
			}

			public function size( string $path ) :int {
				return (int)\filesize( $path );
			}
		};
	}

	public function isFile( $path ) :bool {
		return \is_file( $path );
	}

	public function deleteFile( $path ) {
		return \is_file( $path ) ? @\unlink( $path ) : true;
	}

	public function putFileContent( $path, $contents, $compress = false ) :bool {
		$dir = \dirname( (string)$path );
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
		return \file_put_contents( (string)$path, (string)$contents ) !== false;
	}

	public function mkdir( $path ) :bool {
		return \is_dir( $path ) || @\mkdir( $path, 0777, true );
	}
}
