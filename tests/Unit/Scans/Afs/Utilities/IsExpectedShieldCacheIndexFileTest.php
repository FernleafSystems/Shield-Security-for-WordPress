<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Utilities;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\IsExpectedShieldCacheIndexFile;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\CacheDirHandler;
use FernleafSystems\Wordpress\Services\Core\Fs;

class IsExpectedShieldCacheIndexFileTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => \str_replace( '\\', '/', $path ) );
		Functions\when( 'trailingslashit' )->alias( static fn( string $path ) :string => \rtrim( \str_replace( '\\', '/', $path ), '/' ).'/' );
		Functions\when( 'path_join' )->alias( static fn( string $base, string $path ) :string => \rtrim( $base, '/\\' ).'/'.\ltrim( $path, '/\\' ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_accepts_expected_index_in_each_wordpress_content_root() :void {
		foreach ( \array_unique( [ WP_CONTENT_DIR, path_join( ABSPATH, 'wp-content' ) ] ) as $contentRoot ) {
			$path = \rtrim( $contentRoot, '/\\' ).'/uploads/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc30/index.php';
			$this->installFs( [ $path => CacheDirHandler::CACHE_INDEX_FILE_CONTENT ] );

			$this->assertTrue( ( new IsExpectedShieldCacheIndexFile() )->check( $path ) );
		}
	}

	public function test_rejects_unexpected_cache_index_files() :void {
		$root = \rtrim( WP_CONTENT_DIR, '/\\' );
		$expected = CacheDirHandler::CACHE_INDEX_FILE_CONTENT;
		$cases = [
			'changed content' => [
				$root.'/uploads/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc30/index.php',
				$expected."\n",
				true,
			],
			'nested index' => [
				$root.'/uploads/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc30/nested/index.php',
				$expected,
				true,
			],
			'uppercase hash' => [
				$root.'/uploads/shield-v2-11D5B6DC251F1F1BFCEDDD2C1B7ACC30/index.php',
				$expected,
				true,
			],
			'wrong hash length' => [
				$root.'/uploads/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc3/index.php',
				$expected,
				true,
			],
			'wrong filename' => [
				$root.'/uploads/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc30/cache.php',
				$expected,
				true,
			],
			'outside content' => [
				'/var/cache/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc30/index.php',
				$expected,
				true,
			],
			'content root prefix only' => [
				$root.'-evil/uploads/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc30/index.php',
				$expected,
				true,
			],
			'missing file' => [
				$root.'/uploads/shield-v2-11d5b6dc251f1f1bfcebdd2c1b7acc30/index.php',
				'',
				false,
			],
		];

		foreach ( $cases as [ $path, $content, $accessible ] ) {
			$this->installFs( $accessible ? [ $path => $content ] : [] );

			$this->assertFalse( ( new IsExpectedShieldCacheIndexFile() )->check( $path ) );
		}
	}

	private function installFs( array $contentsByPath ) :void {
		ServicesState::installItems( [
			'service_wpfs' => new ExpectedShieldCacheIndexFs( $contentsByPath ),
		] );
	}
}

class ExpectedShieldCacheIndexFs extends Fs {

	private array $contentsByPath;

	public function __construct( array $contentsByPath ) {
		$this->contentsByPath = $contentsByPath;
	}

	public function isAccessibleFile( string $path ) :bool {
		return \array_key_exists( $path, $this->contentsByPath );
	}

	public function getFileContent( $path, $uncompress = false ) {
		unset( $uncompress );
		return $this->contentsByPath[ $path ] ?? false;
	}
}
