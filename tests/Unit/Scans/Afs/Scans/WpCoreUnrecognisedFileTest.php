<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Scans;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions\WpCoreFileUnrecognisedException;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans\WpCoreUnrecognisedFile;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\CoreFileHashes;
use FernleafSystems\Wordpress\Services\Core\Fs;

class WpCoreUnrecognisedFileTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( $path ) :string => (string)\preg_replace( '|(?<=.)/+|', '/', \str_replace( '\\', '/', (string)$path ) )
		);
		Functions\when( 'path_join' )->alias( static fn( string $base, string $path ) :string => \rtrim( $base, '/\\' ).'/'.$path );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideCoreDirectoryFiles' )]
	public function test_file_in_a_core_directory_that_is_not_core_is_unrecognised( string $fragment ) :void {
		$this->expectException( WpCoreFileUnrecognisedException::class );
		$this->scan( $this->absPath().$fragment );
	}

	public static function provideCoreDirectoryFiles() :array {
		return [
			'wp-admin'         => [ 'wp-admin/shell.php' ],
			'wp-admin nested'  => [ 'wp-admin/includes/shell.php' ],
			'wp-includes'      => [ 'wp-includes/shell.php' ],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'provideFilesOutsideCoreDirectories' )]
	public function test_file_outside_the_core_directories_is_not_assessed( string $fragment ) :void {
		$this->assertFalse( $this->scan( $this->absPath().$fragment ) );
	}

	public static function provideFilesOutsideCoreDirectories() :array {
		return [
			'core directory name nested' => [ 'wp-content/wp-admin/shell.php' ],
			'site root'                  => [ 'shell.php' ],
		];
	}

	public function test_file_outside_abspath_is_not_assessed() :void {
		$this->assertFalse( $this->scan( '/elsewhere/wp-admin/shell.php' ) );
	}

	public function test_core_file_is_not_unrecognised() :void {
		$path = $this->absPath().'wp-admin/about.php';
		$this->assertFalse( $this->scan( $path, true ) );
	}

	public function test_file_that_no_longer_exists_is_not_reported() :void {
		$this->assertFalse( $this->scan( $this->absPath().'wp-admin/shell.php', false, false ) );
	}

	private function absPath() :string {
		return \str_replace( '\\', '/', ABSPATH );
	}

	private function scan( string $path, bool $isCore = false, bool $exists = true ) :bool {
		ServicesState::installItems( [
			'service_corefilehashes' => new class( $isCore ) extends CoreFileHashes {

				private bool $isCore;

				public function __construct( bool $isCore ) {
					$this->isCore = $isCore;
				}

				public function isCoreFile( $file ) :bool {
					return $this->isCore;
				}
			},
			'service_wpfs'           => new class( $exists ) extends Fs {

				private bool $exists;

				public function __construct( bool $exists ) {
					$this->exists = $exists;
				}

				public function isAccessibleFile( string $path ) :bool {
					return $this->exists;
				}
			},
		] );
		$action = new ScanActionVO();
		$action->file_exts = [ 'php' ];
		return ( new WpCoreUnrecognisedFile( $path ) )
			->setScanActionVO( $action )
			->isFileValid();
	}
}
