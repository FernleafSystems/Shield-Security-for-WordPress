<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Lib\Snapshots\Build;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\{
	BuildHashesForAsset,
	BuildHashesFromDir
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BuildHashesFromDirTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	/** @dataProvider algorithmsAndEncoding */
	public function test_raw_bytes_paths_depth_extensions_and_encoding_are_preserved( string $algorithm, bool $binary ) :void {
		$dir = $this->createTrackedTempDir( 'shield-dir-hashes-' );
		$nested = $this->createTrackedTempDir( 'nested-', $dir );
		$contents = "<?php\r\n// raw\rbytes\n\0\xff";
		$file = $this->createTrackedTempFile( 'Main-', '.PHP', $contents, $dir );
		$this->createTrackedTempFile( 'excluded-', '.txt', 'excluded', $dir );
		$child = $this->createTrackedTempFile( 'Child-', '.php', 'nested bytes', $nested );
		$root = \str_replace( '\\', '/', $dir ).'/';
		$relative = static fn( string $path ) :string => \strtolower( \str_replace( $root, '', \str_replace( '\\', '/', $path ) ) );
		$builder = ( new BuildHashesFromDir() )->setHashAlgo( $algorithm )->setFileExts( [ 'php' ] );
		$all = $builder->build( $root, $binary );
		$this->assertCount( 2, $all );
		$this->assertSame( \hash( $algorithm, $contents, $binary ), $all[ $relative( $file ) ] );
		$this->assertSame( \hash( $algorithm, 'nested bytes', $binary ), $all[ $relative( $child ) ] );
		$this->assertSame( [ $relative( $file ) => \hash( $algorithm, $contents, $binary ) ], $builder->setDepth( 1 )->build( $root, $binary ) );
	}

	public static function algorithmsAndEncoding() :array {
		return [ [ 'md5', false ], [ 'md5', true ], [ 'sha1', false ], [ 'sha1', true ], [ 'sha256', false ], [ 'sha256', true ] ];
	}

	public function test_directory_and_asset_builders_keep_md5_defaults() :void {
		$this->assertSame( 'md5', ( new BuildHashesFromDir() )->getHashAlgo() );
		$this->assertSame( 'md5', ( new BuildHashesForAsset() )->getHashAlgo() );
	}

	/** @dataProvider invalidSetterInputs */
	public function test_invalid_setter_keeps_previous_valid_algorithm( string $builderClass, string $invalid ) :void {
		$builder = ( new $builderClass() )->setHashAlgo( 'sha256' );
		try {
			$builder->setHashAlgo( $invalid );
			$this->fail( 'Unsupported algorithm was accepted.' );
		}
		catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'sha256', $builder->getHashAlgo() );
		}
	}

	public static function invalidSetterInputs() :array {
		$cases = [];
		foreach ( [ BuildHashesFromDir::class, BuildHashesForAsset::class ] as $class ) {
			foreach ( [ '', 'SHA256', 'sha384', 'sha512' ] as $invalid ) {
				$cases[] = [ $class, $invalid ];
			}
		}
		return $cases;
	}

	public function test_caught_failure_after_a_real_hash_discards_accumulated_results() :void {
		$dir = $this->createTrackedTempDir( 'shield-dir-partial-' );
		$this->createTrackedTempFile( 'first-', '.php', 'first bytes', $dir );
		$this->createTrackedTempFile( 'second-', '.php', 'second bytes', $dir );
		$calls = 0;
		Functions\when( 'wp_normalize_path' )->alias(
			static function ( string $path ) use ( &$calls ) :string {
				if ( ++$calls === 3 ) {
					throw new \RuntimeException( 'Synthetic boundary failure after the first real file hash.' );
				}
				return \str_replace( '\\', '/', $path );
			}
		);
		$this->assertSame( [], ( new BuildHashesFromDir() )->setHashAlgo( 'sha256' )->build( $dir.'/' ) );
		$this->assertSame( 3, $calls );
	}
}
