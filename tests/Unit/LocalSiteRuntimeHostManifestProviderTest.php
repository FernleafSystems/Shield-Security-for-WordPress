<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteRuntimeHostManifestProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class LocalSiteRuntimeHostManifestProviderTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-host-manifest-' );
		$this->seedRuntimeFiles();
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testFullModeBuildsCurrentRuntimeManifestShape() :void {
		$manifest = ( new LocalSiteRuntimeHostManifestProvider() )->manifest(
			$this->projectRoot,
			LocalSiteRuntimeHostManifestProvider::MODE_FULL,
			static function () :void {}
		);

		$this->assertSame( 1, $manifest[ 'schema_version' ] );
		$this->assertArrayHasKey( 'icwp-wpsf.php', $manifest[ 'files' ] );
		$this->assertArrayHasKey( 'plugin.json', $manifest[ 'files' ] );
		$this->assertArrayHasKey( 'src/Example.php', $manifest[ 'files' ] );
		$this->assertSame(
			\hash_file( 'sha256', Path::join( $this->projectRoot, 'icwp-wpsf.php' ) ),
			$manifest[ 'files' ][ 'icwp-wpsf.php' ][ 'sha256' ]
		);
		$this->assertSame( 5, $manifest[ 'files' ][ 'icwp-wpsf.php' ][ 'size' ] );
	}

	public function testAutoModeReusesCacheWhenFileMetadataIsUnchanged() :void {
		$provider = new LocalSiteRuntimeHostManifestProvider();
		$provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );
		$this->setCachedManifestGeneratedAt( 123 );

		$manifest = $provider->manifest(
			$this->projectRoot,
			LocalSiteRuntimeHostManifestProvider::MODE_AUTO,
			static function () :void {}
		);

		$this->assertSame( 123, $manifest[ 'generated_at_unix' ] );
	}

	public function testAutoModeRebuildsWhenFileMetadataChanges() :void {
		$provider = new LocalSiteRuntimeHostManifestProvider();

		$provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );
		$this->setCachedManifestGeneratedAt( 123 );
		\file_put_contents( Path::join( $this->projectRoot, 'src', 'Added.php' ), '<?php' );
		$addedManifest = $provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );
		$this->assertNotSame( 123, $addedManifest[ 'generated_at_unix' ] );
		$this->assertArrayHasKey( 'src/Added.php', $addedManifest[ 'files' ] );

		$this->setCachedManifestGeneratedAt( 123 );
		\unlink( Path::join( $this->projectRoot, 'src', 'Added.php' ) );
		$deletedManifest = $provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );
		$this->assertNotSame( 123, $deletedManifest[ 'generated_at_unix' ] );
		$this->assertArrayNotHasKey( 'src/Added.php', $deletedManifest[ 'files' ] );

		$this->setCachedManifestGeneratedAt( 123 );
		\file_put_contents( Path::join( $this->projectRoot, 'src', 'Example.php' ), '<?php updated' );
		$sizeManifest = $provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );
		$this->assertNotSame( 123, $sizeManifest[ 'generated_at_unix' ] );

		$this->setCachedManifestGeneratedAt( 123 );
		$path = Path::join( $this->projectRoot, 'src', 'Example.php' );
		$mtime = \filemtime( $path );
		$this->assertIsInt( $mtime );
		\touch( $path, $mtime + 10 );
		$mtimeManifest = $provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );
		$this->assertNotSame( 123, $mtimeManifest[ 'generated_at_unix' ] );
	}

	public function testAutoModeFallsBackWhenCacheIsMalformedOrSchemaMismatched() :void {
		$provider = new LocalSiteRuntimeHostManifestProvider();
		$provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );
		\file_put_contents( $this->cachePath(), '{not-json' );

		$malformedManifest = $provider->manifest(
			$this->projectRoot,
			LocalSiteRuntimeHostManifestProvider::MODE_AUTO,
			static function () :void {}
		);
		$this->assertArrayHasKey( 'icwp-wpsf.php', $malformedManifest[ 'files' ] );

		foreach ( [
			'schema_version' => 0,
			'managed_roots_hash' => [ 'not-string' ],
			'fingerprint' => [ 'not-string' ],
			'manifest' => 'not-manifest',
		] as $cacheKey => $invalidValue ) {
			$this->setCachedManifestGeneratedAt( 123 );
			$cache = $this->readCache();
			$cache[ $cacheKey ] = $invalidValue;
			$this->writeCache( $cache );
			$manifest = $provider->manifest(
				$this->projectRoot,
				LocalSiteRuntimeHostManifestProvider::MODE_AUTO,
				static function () :void {}
			);
			$this->assertNotSame( 123, $manifest[ 'generated_at_unix' ], $cacheKey );
		}
	}

	public function testAutoModeRebuildsWhenCachedManifestPayloadIsCorrupt() :void {
		$provider = new LocalSiteRuntimeHostManifestProvider();
		$provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_AUTO, static function () :void {} );

		foreach ( [
			'missing current file key' => static function ( array $cache ) :array {
				unset( $cache[ 'manifest' ][ 'files' ][ 'src/Example.php' ] );
				return $cache;
			},
			'extra cached file key' => static function ( array $cache ) :array {
				$cache[ 'manifest' ][ 'files' ][ 'src/Deleted.php' ] = [
					'sha256' => \str_repeat( 'a', 64 ),
					'size' => 1,
				];
				return $cache;
			},
			'cached size mismatch' => static function ( array $cache ) :array {
				$cache[ 'manifest' ][ 'files' ][ 'src/Example.php' ][ 'size' ]++;
				return $cache;
			},
			'cached size wrong type' => static function ( array $cache ) :array {
				$cache[ 'manifest' ][ 'files' ][ 'src/Example.php' ][ 'size' ] = '5';
				return $cache;
			},
			'cached hash missing' => static function ( array $cache ) :array {
				unset( $cache[ 'manifest' ][ 'files' ][ 'src/Example.php' ][ 'sha256' ] );
				return $cache;
			},
			'cached hash wrong type' => static function ( array $cache ) :array {
				$cache[ 'manifest' ][ 'files' ][ 'src/Example.php' ][ 'sha256' ] = 123;
				return $cache;
			},
			'cached hash invalid hex' => static function ( array $cache ) :array {
				$cache[ 'manifest' ][ 'files' ][ 'src/Example.php' ][ 'sha256' ] = \str_repeat( 'z', 64 );
				return $cache;
			},
		] as $case => $mutateCache ) {
			$this->setCachedManifestGeneratedAt( 123 );
			$this->writeCache( $mutateCache( $this->readCache() ) );

			$manifest = $provider->manifest(
				$this->projectRoot,
				LocalSiteRuntimeHostManifestProvider::MODE_AUTO,
				static function () :void {}
			);

			$this->assertNotSame( 123, $manifest[ 'generated_at_unix' ], $case );
			$this->assertArrayHasKey( 'src/Example.php', $manifest[ 'files' ], $case );
			$this->assertArrayNotHasKey( 'src/Deleted.php', $manifest[ 'files' ], $case );
			$this->assertSame( 5, $manifest[ 'files' ][ 'src/Example.php' ][ 'size' ], $case );
			$this->assertMatchesRegularExpression(
				'/^[a-f0-9]{64}$/',
				$manifest[ 'files' ][ 'src/Example.php' ][ 'sha256' ],
				$case
			);
		}
	}

	public function testFullModeDetectsSameSizeContentChangeWhenMetadataIsPreserved() :void {
		$provider = new LocalSiteRuntimeHostManifestProvider();
		$path = Path::join( $this->projectRoot, 'src', 'Example.php' );
		$before = $provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_FULL, static function () :void {} );
		$mtime = \filemtime( $path );
		$this->assertIsInt( $mtime );

		\file_put_contents( $path, '<?phq' );
		\touch( $path, $mtime );
		$after = $provider->manifest( $this->projectRoot, LocalSiteRuntimeHostManifestProvider::MODE_FULL, static function () :void {} );

		$this->assertSame( $before[ 'files' ][ 'src/Example.php' ][ 'size' ], $after[ 'files' ][ 'src/Example.php' ][ 'size' ] );
		$this->assertNotSame(
			$before[ 'files' ][ 'src/Example.php' ][ 'sha256' ],
			$after[ 'files' ][ 'src/Example.php' ][ 'sha256' ]
		);
	}

	private function seedRuntimeFiles() :void {
		\mkdir( Path::join( $this->projectRoot, 'src' ), 0777, true );
		\file_put_contents( Path::join( $this->projectRoot, 'icwp-wpsf.php' ), '<?php' );
		\file_put_contents( Path::join( $this->projectRoot, 'plugin.json' ), '{}' );
		\file_put_contents( Path::join( $this->projectRoot, 'src', 'Example.php' ), '<?php' );
	}

	private function setCachedManifestGeneratedAt( int $generatedAt ) :void {
		$cache = $this->readCache();
		$cache[ 'manifest' ][ 'generated_at_unix' ] = $generatedAt;
		$this->writeCache( $cache );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function readCache() :array {
		$decoded = \json_decode( (string)\file_get_contents( $this->cachePath() ), true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}

	/**
	 * @param array<string,mixed> $cache
	 */
	private function writeCache( array $cache ) :void {
		$json = \json_encode( $cache, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );
		$this->assertIsString( $json );
		\file_put_contents( $this->cachePath(), $json.\PHP_EOL );
	}

	private function cachePath() :string {
		return Path::join( $this->projectRoot, 'tmp/.browser-runtime-manifest-cache/host-manifest-cache.json' );
	}
}
