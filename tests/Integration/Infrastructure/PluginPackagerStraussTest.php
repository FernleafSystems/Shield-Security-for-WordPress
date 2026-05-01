<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Infrastructure;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PackagerConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PluginPackagerStraussTest extends TestCase {

	use PluginPathsTrait;

	private const STRAUSS_NAMESPACE_PREFIX = 'AptowebDeps\\';

	private const EXPECTED_STRAUSS_PACKAGES = [
		'monolog/monolog',
		'twig/twig',
		'crowdsec/capi-client',
		'thecodingmachine/safe',
		'web-auth/webauthn-lib',
		'web-auth/cose-lib',
		'web-auth/metadata-service',
		'spomky-labs/base64url',
		'spomky-labs/cbor-php',
		'fgrosse/phpasn1',
		'beberlei/assert',
		'league/uri',
		'league/uri-interfaces',
		'nyholm/psr7',
		'nyholm/psr7-server',
		'symfony/process',
		'dolondro/google-authenticator',
	];

	private const EXPECTED_EXCLUDED_PACKAGES = [
		'psr/log',
		'psr/cache',
		'psr/http-client',
		'psr/http-factory',
		'psr/http-message',
		'ramsey/uuid',
		'ramsey/collection',
		'brick/math',
		'paragonie/random_compat',
		'christian-riesen/base32',
		'symfony/deprecation-contracts',
		'symfony/polyfill-ctype',
		'symfony/polyfill-mbstring',
		'symfony/polyfill-php81',
		'symfony/polyfill-php80',
		'symfony/polyfill-uuid',
	];

	private const REQUIRED_UNPREFIXED_PACKAGES = [
		'psr/log',
		'psr/cache',
		'psr/http-client',
		'psr/http-factory',
		'psr/http-message',
		'ramsey/uuid',
		'ramsey/collection',
		'brick/math',
		'paragonie/random_compat',
		'christian-riesen/base32',
	];

	private const EXPECTED_NAMESPACE_REWRITES = [
		'Monolog\\'                       => 'AptowebDeps\\Monolog\\',
		'Twig\\'                          => 'AptowebDeps\\Twig\\',
		'CrowdSec\\CapiClient\\'          => 'AptowebDeps\\CrowdSec\\CapiClient\\',
		'Safe\\'                          => 'AptowebDeps\\Safe\\',
		'Webauthn\\'                      => 'AptowebDeps\\Webauthn\\',
		'Cose\\'                          => 'AptowebDeps\\Cose\\',
		'Base64Url\\'                     => 'AptowebDeps\\Base64Url\\',
		'CBOR\\'                          => 'AptowebDeps\\CBOR\\',
		'FG\\'                            => 'AptowebDeps\\FG\\',
		'Assert\\'                        => 'AptowebDeps\\Assert\\',
		'League\\Uri\\'                   => 'AptowebDeps\\League\\Uri\\',
		'Nyholm\\Psr7\\'                  => 'AptowebDeps\\Nyholm\\Psr7\\',
		'Nyholm\\Psr7Server\\'            => 'AptowebDeps\\Nyholm\\Psr7Server\\',
		'Symfony\\Component\\Process\\'   => 'AptowebDeps\\Symfony\\Component\\Process\\',
		'Dolondro\\GoogleAuthenticator\\' => 'AptowebDeps\\Dolondro\\GoogleAuthenticator\\',
	];

	private string $packagePath;

	private function packagePathJoin( string ...$parts ) :string {
		return Path::join( $this->packagePath, ...$parts );
	}

	/**
	 * @return string[]
	 */
	private function getRequiredPrefixedPackages() :array {
		return self::EXPECTED_STRAUSS_PACKAGES;
	}

	/**
	 * @return string[]
	 */
	private function getRequiredUnprefixedPackages() :array {
		return self::REQUIRED_UNPREFIXED_PACKAGES;
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
	public function testSourceStraussConfigMatchesPackageContract() :void {
		$strauss = $this->getSourceComposerStraussConfig();

		$this->assertSame( 'vendor_prefixed', $strauss[ 'target_directory' ] ?? null );
		$this->assertSame( self::STRAUSS_NAMESPACE_PREFIX, $strauss[ 'namespace_prefix' ] ?? null );
		$this->assertSame( self::EXPECTED_STRAUSS_PACKAGES, $strauss[ 'packages' ] ?? null );
		$this->assertSame( [ 'src' ], $strauss[ 'update_call_sites' ] ?? null );
		$this->assertSame(
			self::EXPECTED_EXCLUDED_PACKAGES,
			$strauss[ 'exclude_from_copy' ][ 'packages' ] ?? null
		);
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

		foreach ( $this->getRequiredUnprefixedPackages() as $package ) {
			$this->assertContains(
				$package,
				$vendorPackages,
				"Required unprefixed package missing: {$package}"
			);
			$this->assertNotContains(
				$package,
				$prefixedPackages,
				"Required unprefixed package should not exist in vendor_prefixed: {$package}"
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
		foreach ( $this->getExpectedPrefixedPackageVendors() as $dir ) {
			$this->assertDirectoryExists(
				Path::join( $prefixed, $dir ),
				"Prefixed directory missing: {$dir}"
			);
		}
	}

	/** @group package-targeted */
	public function testPackageVendorBinRemoved() :void {
		$this->assertDirectoryDoesNotExist(
			$this->packagePathJoin( 'vendor/bin' ),
			'Packaged vendor/bin should be removed'
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
			foreach ( self::EXPECTED_STRAUSS_PACKAGES as $package ) {
				$this->assertStringNotContainsString(
					$package,
					(string)$content,
					"Main vendor autoload file should not contain prefixed package references: {$file} -> {$package}"
				);
			}
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
		foreach ( $this->getExpectedPrefixedAutoloadNamespaces() as $namespace ) {
			$found = false;
			foreach ( $autoloadContents as $content ) {
				if ( strpos( $content, str_replace( '\\', '\\\\', $namespace ) ) !== false ) {
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

		$attestationManager = new \AptowebDeps\Webauthn\AttestationStatement\AttestationStatementSupportManager();
		$attestationLoader = new \AptowebDeps\Webauthn\AttestationStatement\AttestationObjectLoader( $attestationManager );
		$credentialLoader = new \AptowebDeps\Webauthn\PublicKeyCredentialLoader( $attestationLoader );
		$this->assertInstanceOf( \AptowebDeps\Webauthn\PublicKeyCredentialLoader::class, $credentialLoader );

		$googleAuthenticator = new \AptowebDeps\Dolondro\GoogleAuthenticator\GoogleAuthenticator();
		$this->assertInstanceOf(
			\AptowebDeps\Dolondro\GoogleAuthenticator\GoogleAuthenticator::class,
			$googleAuthenticator
		);

		$psr17Factory = new \AptowebDeps\Nyholm\Psr7\Factory\Psr17Factory();
		$this->assertInstanceOf( \AptowebDeps\Nyholm\Psr7\Factory\Psr17Factory::class, $psr17Factory );

		$base64Url = new \AptowebDeps\Base64Url\Base64Url();
		$this->assertInstanceOf( \AptowebDeps\Base64Url\Base64Url::class, $base64Url );

		$process = new \AptowebDeps\Symfony\Component\Process\Process( [ 'php', '-v' ] );
		$this->assertInstanceOf( \AptowebDeps\Symfony\Component\Process\Process::class, $process );
	}

	/** @group package-targeted */
	public function testPackagedSourceHasNoUnprefixedReferencesToPrefixedNamespaces() :void {
		$violations = $this->findForbiddenNamespaceReferences(
			$this->collectPhpFiles( $this->packagePathJoin( 'src' ) ),
			array_keys( self::EXPECTED_NAMESPACE_REWRITES ),
			true
		);

		$this->assertTrue( $violations === [], $this->formatNamespaceViolations( $violations ) );
	}

	/** @group package-targeted */
	public function testPrefixedVendorHasNoUnprefixedReferencesToGeneratedPrefixedNamespaces() :void {
		$violations = $this->findForbiddenNamespaceReferences(
			$this->collectPhpFiles( $this->packagePathJoin( 'vendor_prefixed' ) ),
			array_keys( self::EXPECTED_NAMESPACE_REWRITES ),
			false
		);

		$this->assertTrue( $violations === [], $this->formatNamespaceViolations( $violations ) );
	}

	/** @group package-targeted */
	public function testLegacyCompatibilityOutputIsAbsentWhenNoPlanIsActive() :void {
		$this->assertDirectoryDoesNotExist( $this->packagePathJoin( 'src/lib' ) );
	}

	/**
	 * @return string[]
	 */
	private function getExpectedPrefixedPackageVendors() :array {
		$vendors = [];
		foreach ( self::EXPECTED_STRAUSS_PACKAGES as $package ) {
			$vendors[] = explode( '/', $package )[ 0 ];
		}
		sort( $vendors );
		return array_values( array_unique( $vendors ) );
	}

	/**
	 * @return string[]
	 */
	private function getExpectedPrefixedAutoloadNamespaces() :array {
		$namespaces = array_values( self::EXPECTED_NAMESPACE_REWRITES );
		sort( $namespaces );
		return $namespaces;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getSourceComposerStraussConfig() :array {
		$composerJson = Path::join( dirname( __DIR__, 3 ), 'composer.json' );
		$this->assertFileExists( $composerJson );

		$content = file_get_contents( $composerJson );
		$this->assertNotFalse( $content, "Failed reading {$composerJson}" );

		$decoded = json_decode( (string)$content, true );
		$this->assertIsArray( $decoded, 'Source composer.json must decode to an array.' );
		$this->assertIsArray( $decoded[ 'extra' ][ 'strauss' ] ?? null, 'Source composer.json missing extra.strauss config.' );

		return $decoded[ 'extra' ][ 'strauss' ];
	}

	/**
	 * @return string[]
	 */
	private function collectPhpFiles( string $baseDir ) :array {
		if ( !is_dir( $baseDir ) ) {
			return [];
		}

		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $baseDir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file instanceof \SplFileInfo
				 && $file->isFile()
				 && strtolower( $file->getExtension() ) === 'php' ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );
		return $files;
	}

	/**
	 * @param string[] $files
	 * @param string[] $forbiddenRoots
	 * @return array<string, array<string, string[]>>
	 */
	private function findForbiddenNamespaceReferences( array $files, array $forbiddenRoots, bool $inspectStringLiterals ) :array {
		$violations = [];

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );
			$this->assertNotFalse( $content, "Failed reading {$file}" );

			$references = $this->collectNamespaceTokenReferences( (string)$content );
			if ( $inspectStringLiterals ) {
				$references = array_merge(
					$references,
					$this->collectNamespaceStringReferences( (string)$content )
				);
			}

			$references = array_values( array_unique( $references ) );
			sort( $references );

			foreach ( $references as $reference ) {
				foreach ( $forbiddenRoots as $root ) {
					if ( $this->namespaceReferenceMatchesRoot( $reference, $root ) ) {
						$relativePath = $this->formatPackageRelativePath( $file );
						$violations[ $relativePath ][ $root ][] = $reference;
					}
				}
			}
		}

		foreach ( $violations as $file => $roots ) {
			foreach ( $roots as $root => $references ) {
				$references = array_values( array_unique( $references ) );
				sort( $references );
				$violations[ $file ][ $root ] = $references;
			}
			ksort( $violations[ $file ] );
		}
		ksort( $violations );

		return $violations;
	}

	/**
	 * @return string[]
	 */
	private function collectNamespaceTokenReferences( string $content ) :array {
		$tokens = token_get_all( $content );
		$references = [];
		$qualifiedTokenIds = $this->getQualifiedNameTokenIds();
		$namespaceSeparatorId = defined( 'T_NS_SEPARATOR' ) ? constant( 'T_NS_SEPARATOR' ) : null;

		$count = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( !is_array( $token ) ) {
				continue;
			}

			if ( in_array( $token[0], $qualifiedTokenIds, true ) ) {
				$references[] = $this->normaliseNamespaceReference( $token[1] );
				continue;
			}

			if ( $token[0] !== T_STRING ) {
				continue;
			}

			$parts = [ $token[1] ];
			$nextIndex = $i + 1;
			$hasSeparator = false;

			while ( $nextIndex < $count ) {
				$nextToken = $tokens[ $nextIndex ];
				$nextTokenText = is_array( $nextToken ) ? $nextToken[1] : $nextToken;
				$nextTokenId = is_array( $nextToken ) ? $nextToken[0] : null;

				if ( $nextTokenText !== '\\' && $nextTokenId !== $namespaceSeparatorId ) {
					break;
				}

				$hasSeparator = true;
				$parts[] = '\\';
				$nextIndex++;

				if ( $nextIndex >= $count
					 || !is_array( $tokens[ $nextIndex ] )
					 || $tokens[ $nextIndex ][0] !== T_STRING ) {
					break;
				}

				$parts[] = $tokens[ $nextIndex ][1];
				$nextIndex++;
			}

			if ( $hasSeparator ) {
				$references[] = $this->normaliseNamespaceReference( implode( '', $parts ) );
				$i = $nextIndex - 1;
			}
		}

		return array_values( array_unique( array_filter( $references ) ) );
	}

	/**
	 * @return string[]
	 */
	private function collectNamespaceStringReferences( string $content ) :array {
		$tokens = token_get_all( $content );
		$stringTokenIds = [ T_CONSTANT_ENCAPSED_STRING ];
		if ( defined( 'T_ENCAPSED_AND_WHITESPACE' ) ) {
			$stringTokenIds[] = constant( 'T_ENCAPSED_AND_WHITESPACE' );
		}

		$references = [];
		foreach ( $tokens as $token ) {
			if ( !is_array( $token ) || !in_array( $token[0], $stringTokenIds, true ) ) {
				continue;
			}

			$value = trim( $token[1], "'\"" );
			while ( strpos( $value, '\\\\' ) !== false ) {
				$value = str_replace( '\\\\', '\\', $value );
			}

			if ( preg_match_all(
				'#\\\\?([A-Z][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+)#',
				$value,
				$matches
			) ) {
				foreach ( $matches[1] as $match ) {
					$references[] = $this->normaliseNamespaceReference( $match );
				}
			}
		}

		return array_values( array_unique( array_filter( $references ) ) );
	}

	/**
	 * @return int[]
	 */
	private function getQualifiedNameTokenIds() :array {
		$tokenNames = [
			'T_NAME_QUALIFIED',
			'T_NAME_FULLY_QUALIFIED',
			'T_NAME_RELATIVE',
		];

		$ids = [];
		foreach ( $tokenNames as $tokenName ) {
			if ( defined( $tokenName ) ) {
				$ids[] = constant( $tokenName );
			}
		}

		return $ids;
	}

	private function normaliseNamespaceReference( string $reference ) :string {
		while ( strpos( $reference, '\\\\' ) !== false ) {
			$reference = str_replace( '\\\\', '\\', $reference );
		}

		return trim( trim( $reference ), '\\' );
	}

	private function namespaceReferenceMatchesRoot( string $reference, string $root ) :bool {
		$reference = $this->normaliseNamespaceReference( $reference );
		$root = $this->normaliseNamespaceReference( $root );

		return $reference === $root || strpos( $reference, $root.'\\' ) === 0;
	}

	private function formatPackageRelativePath( string $file ) :string {
		$packagePath = rtrim( str_replace( '\\', '/', $this->packagePath ), '/' );
		$file = str_replace( '\\', '/', $file );

		if ( strpos( $file, $packagePath.'/' ) === 0 ) {
			return substr( $file, strlen( $packagePath ) + 1 );
		}

		return $file;
	}

	/**
	 * @param array<string, array<string, string[]>> $violations
	 */
	private function formatNamespaceViolations( array $violations ) :string {
		if ( $violations === [] ) {
			return 'No forbidden unprefixed namespace references found.';
		}

		$lines = [ 'Forbidden unprefixed namespace references found:' ];
		foreach ( $violations as $file => $roots ) {
			foreach ( $roots as $root => $references ) {
				$lines[] = sprintf(
					'%s: %s -> %s',
					$file,
					$root,
					implode( ', ', $references )
				);
			}
		}

		return implode( "\n", $lines );
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
