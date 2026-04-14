<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Infrastructure;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PackagerConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PluginPackagerStraussTest extends TestCase {

	use PluginPathsTrait;

	private string $packagePath;

	private function packagePathJoin( string ...$parts ) :string {
		return Path::join( $this->packagePath, ...$parts );
	}

	/**
	 * @return string[]
	 */
	private function getRequiredPrefixedPackages() :array {
		return [
			'monolog/monolog',
			'twig/twig',
			'crowdsec/capi-client',
			'thecodingmachine/safe',
		];
	}

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
	}

	/** @group package-targeted */
	public function testVendorPrefixedExists() :void {
		$prefixed = $this->packagePathJoin( 'vendor_prefixed' );
		$this->assertDirectoryExists( $prefixed, 'vendor_prefixed directory missing' );
		$this->assertFileExists( Path::join( $prefixed, 'autoload.php' ) );
	}

	/** @group package-targeted */
	public function testPackagePathParity() :void {
		$vendorPackages = $this->collectPackagePaths( $this->packagePathJoin( 'vendor' ) );
		$prefixedPackages = $this->collectPackagePaths( $this->packagePathJoin( 'vendor_prefixed' ) );

		$overlap = array_values( array_intersect( $vendorPackages, $prefixedPackages ) );
		$this->assertSame( [], $overlap, 'Packages duplicated between vendor and vendor_prefixed: '.implode( ', ', $overlap ) );

		$requiredPrefixedOnly = $this->getRequiredPrefixedPackages();

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

	/** @group package-targeted */
	public function testRequiredPrefixedPackageDirectoriesAreNonEmpty() :void {
		$requiredPrefixedOnly = $this->getRequiredPrefixedPackages();

		foreach ( $requiredPrefixedOnly as $package ) {
			$packageDir = Path::join( $this->packagePath, 'vendor_prefixed', $package );
			$this->assertDirectoryExists( $packageDir, "Required prefixed package missing: {$package}" );

			$entries = \scandir( $packageDir ) ?: [];
			$entries = \array_values( \array_diff( $entries, [ '.', '..' ] ) );
			$this->assertNotSame( [], $entries, "Required prefixed package directory is empty: {$package}" );
		}
	}

	/** @group package-targeted */
	public function testPrefixedLibrariesPresent() :void {
		$prefixed = $this->packagePathJoin( 'vendor_prefixed' );
		foreach ( [ 'monolog', 'twig', 'crowdsec', 'thecodingmachine' ] as $dir ) {
			$this->assertDirectoryExists(
				Path::join( $prefixed, $dir ),
				"Prefixed directory missing: {$dir}"
			);
		}
	}

	/** @group package-targeted */
	public function testUnprefixedRemoved() :void {
		$vendor = $this->packagePathJoin( 'vendor' );
		foreach ( [ 'monolog', 'twig', 'bin' ] as $dir ) {
			$this->assertDirectoryDoesNotExist(
				Path::join( $vendor, $dir ),
				"Unprefixed directory should be removed: {$dir}"
			);
		}
		$this->assertDirectoryDoesNotExist(
			$this->packagePathJoin( 'vendor/thecodingmachine/safe' ),
			'Unprefixed Safe package should be removed from vendor'
		);
	}

	/** @group package-targeted */
	public function testStraussPharRemoved() :void {
		$this->assertFileDoesNotExist( $this->packagePathJoin( 'strauss.phar' ) );
	}

	/** @group package-targeted */
	public function testAutoloaderSuffixApplied() :void {
		$autoloadReal = $this->packagePathJoin( 'vendor/composer/autoload_real.php' );
		$this->assertFileExists( $autoloadReal );

		$content = file_get_contents( $autoloadReal );
		$this->assertNotFalse( $content );
		$this->assertStringContainsString(
			'ComposerAutoloaderInitShieldPackage',
			(string)$content,
			'Package autoloader must use unique suffix to prevent class name conflicts with source autoloader'
		);
	}

	/** @group package-targeted */
	public function testAutoloadsPruned() :void {
		$composerDir = $this->packagePathJoin( 'vendor/composer' );
		$files = [
			'autoload_files.php',
			'autoload_psr4.php',
			'autoload_static.php',
		];

		foreach ( $files as $file ) {
			$path = Path::join( $composerDir, $file );
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
			$this->assertStringNotContainsString(
				'/thecodingmachine/safe/',
				(string)$content,
				"Autoload file should not contain Safe references: {$file}"
			);
		}
	}

	/** @group package-targeted */
	public function testPrefixedAutoloadsHaveNoVendorLeaks() :void {
		$autoloadFiles = glob( $this->packagePathJoin( 'vendor_prefixed/autoload*.php' ) ) ?: [];
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

	/** @group package-targeted */
	public function testPrefixedAutoloadContainsKeyNamespaces() :void {
		$composerDir = Path::join( $this->packagePath, 'vendor_prefixed', 'composer' );
		$autoloadFiles = [
			'autoload_classmap.php',
			'autoload_psr4.php',
			'autoload_static.php',
		];

		$autoloadContents = [];
		foreach ( $autoloadFiles as $file ) {
			$path = Path::join( $composerDir, $file );
			if ( !file_exists( $path ) ) {
				continue;
			}
			$content = file_get_contents( $path );
			$this->assertNotFalse( $content, "Failed reading {$path}" );
			$autoloadContents[ $file ] = (string)$content;
		}

		$this->assertNotSame( [], $autoloadContents, 'No prefixed composer autoload files found to inspect.' );

		// Note: We search for double-backslashes because the autoload files are PHP source
		// where namespace backslashes are escaped (e.g., 'AptowebDeps\\Monolog\\').
		foreach ( [ 'AptowebDeps\\\\Monolog\\\\', 'AptowebDeps\\\\Twig\\\\', 'AptowebDeps\\\\CrowdSec\\\\', 'AptowebDeps\\\\Safe\\\\' ] as $namespace ) {
			$found = false;
			foreach ( $autoloadContents as $content ) {
				if ( strpos( $content, $namespace ) !== false ) {
					$found = true;
					break;
				}
			}

			$this->assertTrue(
				$found,
				sprintf(
					'Prefixed namespace missing from composer autoload files: %s (checked: %s)',
					$namespace,
					implode( ', ', array_keys( $autoloadContents ) )
				)
			);
		}
	}

	/** @group package-targeted */
	public function testPrefixedAutoloadSmoke() :void {
		$prefixedAutoload = $this->packagePathJoin( 'vendor_prefixed/autoload.php' );

		$this->assertFileExists( $prefixedAutoload );

		require_once $prefixedAutoload;

		$logger = new \AptowebDeps\Monolog\Logger( 'test' );
		$this->assertInstanceOf( \AptowebDeps\Monolog\Logger::class, $logger );

		$loader = new \AptowebDeps\Twig\Loader\ArrayLoader( [] );
		$env = new \AptowebDeps\Twig\Environment( $loader );
		$this->assertInstanceOf( \AptowebDeps\Twig\Environment::class, $env );

		$crowdSecClass = 'AptowebDeps\\CrowdSec\\CapiClient\\Watcher';
		if ( !class_exists( $crowdSecClass ) ) {
			$psr4Path = $this->packagePathJoin( 'vendor_prefixed/composer/autoload_psr4.php' );
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

		$this->assertTrue( \function_exists( 'AptowebDeps\\Safe\\json_encode' ) );
		$this->assertSame( '{"ok":true}', \AptowebDeps\Safe\json_encode( [ 'ok' => true ] ) );

		$dateTime = new \AptowebDeps\Safe\DateTimeImmutable( 'now' );
		$this->assertInstanceOf( \AptowebDeps\Safe\DateTimeImmutable::class, $dateTime->setTimestamp( 123 ) );
	}

	public function testPackageRuntimeCallSitesArePrefixed() :void {
		$checks = [
			'src/Modules/Traffic/Lib/RequestLogger.php'          => [ 'AptowebDeps\\Monolog\\Logger', 'Monolog\\Logger' ],
			'src/Render/RenderService.php'                       => [ 'AptowebDeps\\Twig\\Environment', 'Twig\\Environment' ],
			'src/Modules/IPs/Lib/CrowdSec/CrowdSecController.php' => [ 'AptowebDeps\\CrowdSec\\CapiClient\\Watcher', 'CrowdSec\\CapiClient\\Watcher' ],
		];

		foreach ( $checks as $relativePath => [ $prefixedNamespace, $sourceNamespace ] ) {
			$content = $this->getPluginFileContents( $relativePath, "Packaged runtime file: {$relativePath}" );
			$this->assertStringContainsString(
				$prefixedNamespace,
				$content,
				"Expected rewritten prefixed call site in package output: {$relativePath}"
			);
			$this->assertStringNotContainsString(
				'use '.$sourceNamespace.';',
				$content,
				"Unprefixed source namespace should not remain as a use-import in package output: {$relativePath}"
			);
		}
	}

	public function testPackageVendorSafeCallSitesArePrefixed() :void {
		$checks = [
			'vendor/web-auth/webauthn-lib/src/StringStream.php'                                    => [ 'use function AptowebDeps\\Safe\\fopen;', 'use function Safe\\fopen;' ],
			'vendor/web-auth/webauthn-lib/src/AttestationStatement/TPMAttestationStatementSupport.php' => [ 'use AptowebDeps\\Safe\\DateTimeImmutable;', 'use Safe\\DateTimeImmutable;' ],
			'vendor/web-auth/metadata-service/src/MetadataService.php'                             => [ 'use function AptowebDeps\\Safe\\json_decode;', 'use function Safe\\json_decode;' ],
		];

		foreach ( $checks as $relativePath => [ $prefixedImport, $sourceImport ] ) {
			$content = $this->getPluginFileContents( $relativePath, "Packaged vendor file: {$relativePath}" );
			$this->assertStringContainsString(
				$prefixedImport,
				$content,
				"Expected rewritten prefixed Safe import in package output: {$relativePath}"
			);
			$this->assertStringNotContainsString(
				$sourceImport,
				$content,
				"Unprefixed Safe import should not remain in package output: {$relativePath}"
			);
		}
	}

	/** @group package-targeted */
	public function testLegacyCompatibilityOutputIsAbsentWhenNoPlanIsActive() :void {
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib' ) );
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
			$vendorPath = Path::join( $baseDir, $vendor );
			if ( !is_dir( $vendorPath ) ) {
				continue;
			}
			foreach ( scandir( $vendorPath ) ?: [] as $package ) {
				if ( $package === '.' || $package === '..' ) {
					continue;
				}
				$packagePath = Path::join( $vendorPath, $package );
				if ( is_dir( $packagePath ) ) {
					$packages[] = "{$vendor}/{$package}";
				}
			}
		}

		sort( $packages );
		return array_values( array_unique( $packages ) );
	}
}
