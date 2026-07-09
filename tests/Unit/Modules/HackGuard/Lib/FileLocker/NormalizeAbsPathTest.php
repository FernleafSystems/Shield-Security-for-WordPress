<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\NormalizeAbsPath;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class NormalizeAbsPathTest extends BaseUnitTest {

	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		Functions\when( 'trailingslashit' )->alias(
			static fn( string $path ) :string => \rtrim( \str_replace( '\\', '/', $path ), '/' ).'/'
		);
	}

	protected function tearDown() :void {
		foreach ( \array_reverse( $this->tempDirs ) as $dir ) {
			$this->removeDir( $dir );
		}
		parent::tearDown();
	}

	public function test_existing_dot_segment_paths_compare_equal_after_realpath_resolution() :void {
		$root = $this->makeTempDir();
		$public = $root.'/public';
		$this->mkdir( $public );

		$dotPath = $root.'/./public/';
		$this->assertIsString( \realpath( $dotPath ) );
		$this->assertIsString( \realpath( $public ) );

		$normalizer = new NormalizeAbsPath();
		$this->assertNotSame( $normalizer->normalize( $dotPath ), $normalizer->normalize( $public ) );
		$this->assertTrue( $normalizer->areSame( $dotPath, $public ) );
	}

	public function test_missing_path_falls_back_to_normalized_string_when_realpath_returns_false() :void {
		$root = $this->makeTempDir();
		$missing = $root.'/missing-public';
		$this->assertFalse( \realpath( $missing ) );

		$normalizer = new NormalizeAbsPath();
		$result = $normalizer->normalizeResolved( $missing );

		$this->assertIsString( $result );
		$this->assertSame( $normalizer->normalize( $missing ), $result );
		$this->assertFalse( $normalizer->areSame( $missing, $root ) );
	}

	public function test_normalize_converts_backslashes_and_adds_trailing_slash() :void {
		$normalizer = new NormalizeAbsPath();

		$this->assertSame( 'C:/hosting/site/public/', $normalizer->normalize( 'C:\\hosting\\site\\public' ) );
	}

	public function test_same_raw_path_with_different_trailing_slash_compares_equal() :void {
		$root = $this->makeTempDir();
		$normalizer = new NormalizeAbsPath();

		$this->assertTrue( $normalizer->areSame( $root, $root.'/' ) );
	}

	private function makeTempDir() :string {
		$dir = \rtrim( \str_replace( '\\', '/', \sys_get_temp_dir() ), '/' )
			   .'/shield-normalize-abspath-'.\uniqid();
		$this->mkdir( $dir );
		$this->tempDirs[] = $dir;
		return $dir;
	}

	private function mkdir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			@\mkdir( $dir, 0777, true );
		}
	}

	private function removeDir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $dir );
	}
}
