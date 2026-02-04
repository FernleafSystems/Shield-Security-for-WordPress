<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Infrastructure;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PackagerConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;

class PluginPackagerStraussTest extends TestCase {

	use PluginPathsTrait;

	private string $packagePath;
	private string $straussVersion;

	protected function setUp() :void {
		parent::setUp();

		if ( !$this->isTestingPackage() ) {
			$this->markTestSkipped( 'Strauss package tests run only when SHIELD_PACKAGE_PATH points to a built package.' );
		}

		$this->packagePath = $this->getPluginRoot();
		$version = PackagerConfig::getStraussVersion();
		if ( $version === null || $version === '' ) {
			$this->markTestSkipped( 'SHIELD_STRAUSS_VERSION not set and packager config not available.' );
		}
		$this->straussVersion = $version;
	}

	public function testVendorPrefixedExists() :void {
		$prefixed = $this->packagePath.'/vendor_prefixed';
		$this->assertDirectoryExists( $prefixed, 'vendor_prefixed directory missing' );
		$this->assertFileExists( $prefixed.'/autoload.php' );
	}

	public function testPackagePathParity() :void {
		$vendorPackages = $this->collectPackagePaths( $this->packagePath.'/vendor' );
		$prefixedPackages = $this->collectPackagePaths( $this->packagePath.'/vendor_prefixed' );

		$overlap = array_values( array_intersect( $vendorPackages, $prefixedPackages ) );
		$this->assertSame( [], $overlap, 'Packages duplicated between vendor and vendor_prefixed: '.implode( ', ', $overlap ) );

		$requiredPrefixedOnly = [
			'monolog/monolog',
			'twig/twig',
			'crowdsec/capi-client',
		];

		foreach ( $requiredPrefixedOnly as $package ) {
			$this->assertContains(
				$package,
				$prefixedPackages,
				"Required prefixed package missing: {$package}"
			);
			$this->assertNotContains(
				$package,
				$vendorPackages,
				"Prefixed-only package should not exist in vendor: {$package}"
			);
		}
	}

	public function testPrefixedLibrariesPresent() :void {
		$prefixed = $this->packagePath.'/vendor_prefixed';
		foreach ( [ 'monolog', 'twig', 'crowdsec' ] as $dir ) {
			$this->assertDirectoryExists(
				$prefixed.'/'.$dir,
				"Prefixed directory missing: {$dir}"
			);
		}
	}

	public function testUnprefixedRemoved() :void {
		$vendor = $this->packagePath.'/vendor';
		foreach ( [ 'monolog', 'twig', 'bin' ] as $dir ) {
			$this->assertDirectoryDoesNotExist(
				$vendor.'/'.$dir,
				"Unprefixed directory should be removed: {$dir}"
			);
		}
	}

	public function testStraussPharRemoved() :void {
		$this->assertFileDoesNotExist( $this->packagePath.'/strauss.phar' );
	}

	public function testAutoloadsPruned() :void {
		$composerDir = $this->packagePath.'/vendor/composer';
		$files = [
			'autoload_files.php',
			'autoload_psr4.php',
			'autoload_static.php',
		];

		foreach ( $files as $file ) {
			$path = $composerDir.'/'.$file;
			if ( !file_exists( $path ) ) {
				continue;
			}
			$content = file_get_contents( $path );
			$this->assertNotFalse( $content );
			$this->assertStringNotContainsString(
				'/twig/twig/',
				(string)$content,
				"Autoload file should not contain twig references: {$file}"
			);
		}
	}

	public function testPrefixedAutoloadsHaveNoVendorLeaks() :void {
		$autoloadFiles = glob( $this->packagePath.'/vendor_prefixed/autoload*.php' ) ?: [];
		$this->assertNotSame( [], $autoloadFiles, 'No prefixed autoload files found to inspect.' );

		$leaks = [];
		foreach ( $autoloadFiles as $file ) {
			$content = file_get_contents( $file );
			$this->assertNotFalse( $content, "Failed reading {$file}" );
			if ( preg_match_all( '#/vendor/(?!prefixed/)#', (string)$content, $matches ) ) {
				$leaks[ basename( $file ) ] = array_values( array_unique( $matches[0] ) );
			}
		}

		$this->assertSame(
			[],
			$leaks,
			'Prefixed autoload files reference unprefixed vendor paths: '.json_encode( $leaks )
		);
	}

	public function testPrefixedClassmapContainsKeyNamespaces() :void {
		$classmapPath = $this->packagePath.'/vendor_prefixed/composer/autoload_classmap.php';
		$this->assertFileExists( $classmapPath );

		$content = file_get_contents( $classmapPath );
		$this->assertNotFalse( $content );

		// Note: We search for double-backslashes because the classmap file contains
		// PHP source code where backslashes are escaped (e.g., 'AptowebDeps\\Monolog\\')
		foreach ( [ 'AptowebDeps\\\\Monolog\\\\', 'AptowebDeps\\\\Twig\\\\', 'AptowebDeps\\\\CrowdSec\\\\' ] as $namespace ) {
			$this->assertStringContainsString(
				$namespace,
				(string)$content,
				"Prefixed classmap missing namespace: {$namespace}"
			);
		}
	}

	public function testPrefixedAutoloadSmoke() :void {
		$prefixedAutoload = $this->packagePath.'/vendor_prefixed/autoload.php';
		$vendorAutoload = $this->packagePath.'/vendor/autoload.php';

		$this->assertFileExists( $prefixedAutoload );
		$this->assertFileExists( $vendorAutoload );

		require_once $prefixedAutoload;
		require_once $vendorAutoload;

		$logger = new \AptowebDeps\Monolog\Logger( 'test' );
		$this->assertInstanceOf( \AptowebDeps\Monolog\Logger::class, $logger );

		$loader = new \AptowebDeps\Twig\Loader\ArrayLoader( [] );
		$env = new \AptowebDeps\Twig\Environment( $loader );
		$this->assertInstanceOf( \AptowebDeps\Twig\Environment::class, $env );

		$crowdSecClass = 'AptowebDeps\\CrowdSec\\CapiClient\\Watcher';
		if ( !class_exists( $crowdSecClass ) ) {
			$psr4Path = $this->packagePath.'/vendor_prefixed/composer/autoload_psr4.php';
			$namespaces = [];
			if ( file_exists( $psr4Path ) ) {
				$psr4 = require $psr4Path;
				if ( is_array( $psr4 ) ) {
					foreach ( array_keys( $psr4 ) as $ns ) {
						if ( strpos( $ns, 'AptowebDeps\\CrowdSec\\CapiClient\\' ) === 0 ) {
							$namespaces[] = rtrim( $ns, '\\' );
						}
					}
				}
			}
			$hint = $namespaces !== [] ? 'Available CrowdSec namespaces: '.implode( ', ', array_unique( $namespaces ) ) : 'No CrowdSec\\CapiClient namespaces found in prefixed autoload_psr4.';
			$this->fail( "CrowdSec prefixed class missing: {$crowdSecClass}. {$hint}" );
		}

		$this->assertTrue( class_exists( $crowdSecClass ) );
	}

	public function testManifestSnapshotIfPresent() :void {
		$fixturePath = $this->packagePath.'/tests/fixtures/packager/expected-manifest.json';
		if ( !file_exists( $fixturePath ) ) {
			$this->markTestSkipped( 'Manifest fixture not present; skip snapshot check.' );
		}

		$fixture = json_decode( (string)file_get_contents( $fixturePath ), true );
		if ( !is_array( $fixture ) || empty( $fixture[ 'files' ] ?? [] ) ) {
			$this->markTestSkipped( 'Manifest fixture empty; skip snapshot check.' );
		}

		$current = $this->buildManifest( $fixture[ 'files' ] );
		$this->assertSame( $fixture[ 'files' ], $current, 'Package manifest does not match expected snapshot.' );
	}

	/**
	 * @param array<string,array<string,mixed>> $fixtureFiles
	 * @return array<string,array<string,mixed>>
	 */
	private function buildManifest( array $fixtureFiles ) :array {
		$result = [];
		foreach ( $fixtureFiles as $rel => $expected ) {
			$path = $this->packagePath.'/'.$rel;
			$this->assertFileExists( $path, "Manifest path missing: {$rel}" );
			$result[ $rel ] = [
				'sha256' => hash_file( 'sha256', $path ),
				'size'   => filesize( $path ),
			];
		}
		return $result;
	}

	/**
	 * @return string[]
	 */
	private function collectPackagePaths( string $baseDir ) :array {
		if ( !is_dir( $baseDir ) ) {
			return [];
		}

		$packages = [];
		foreach ( scandir( $baseDir ) ?: [] as $vendor ) {
			if ( $vendor === '.' || $vendor === '..' ) {
				continue;
			}
			$vendorPath = $baseDir.'/'.$vendor;
			if ( !is_dir( $vendorPath ) ) {
				continue;
			}
			foreach ( scandir( $vendorPath ) ?: [] as $package ) {
				if ( $package === '.' || $package === '..' ) {
					continue;
				}
				$packagePath = $vendorPath.'/'.$package;
				if ( is_dir( $packagePath ) ) {
					$packages[] = "{$vendor}/{$package}";
				}
			}
		}

		sort( $packages );
		return array_values( array_unique( $packages ) );
	}
}

