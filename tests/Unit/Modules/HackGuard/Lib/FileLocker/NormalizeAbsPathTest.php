<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\FileLocker;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\NormalizeAbsPath;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class NormalizeAbsPathTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

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
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_existing_dot_segment_paths_compare_equal_after_realpath_resolution() :void {
		$root = $this->createTrackedTempDir( 'shield-normalize-abspath-' );
		$public = $root.'/public';
		@\mkdir( $public, 0777, true );

		$dotPath = $root.'/./public/';
		$this->assertIsString( \realpath( $dotPath ) );
		$this->assertIsString( \realpath( $public ) );

		$normalizer = new NormalizeAbsPath();
		$this->assertNotSame( $normalizer->normalize( $dotPath ), $normalizer->normalize( $public ) );
		$this->assertTrue( $normalizer->areSame( $dotPath, $public ) );
	}

	public function test_missing_path_falls_back_to_normalized_string_when_realpath_returns_false() :void {
		$root = $this->createTrackedTempDir( 'shield-normalize-abspath-' );
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
		$root = $this->createTrackedTempDir( 'shield-normalize-abspath-' );
		$normalizer = new NormalizeAbsPath();

		$this->assertTrue( $normalizer->areSame( $root, $root.'/' ) );
	}
}
