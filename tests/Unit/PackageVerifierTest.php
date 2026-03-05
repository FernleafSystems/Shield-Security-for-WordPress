<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PackageVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for PackageVerifier.
 * Tests package verification pass/fail scenarios.
 */
class PackageVerifierTest extends TestCase {

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = sys_get_temp_dir().'/shield-test-'.uniqid();
		$this->fs->mkdir( $this->tempDir );
	}

	protected function tearDown() :void {
		if ( is_dir( $this->tempDir ) ) {
			$this->fs->remove( $this->tempDir );
		}
		parent::tearDown();
	}

	private function createVerifier( ?callable $logger = null ) :PackageVerifier {
		return new PackageVerifier( $logger ?? function () {} );
	}

	private function setupValidPackage() :void {
		// Create all required files
		$this->fs->dumpFile( $this->tempDir.'/plugin.json', '{}' );
		$this->fs->dumpFile( $this->tempDir.'/icwp-wpsf.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor/autoload.php', '<?php' );

		// Create all required directories
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed' );
		$this->fs->mkdir( $this->tempDir.'/assets/dist' );
		$this->fs->mkdir( $this->tempDir.'/src/lib/src' );
		$this->fs->mkdir( $this->tempDir.'/src/lib/vendor' );
	}

	private function setupRequiredPrefixedPackages() :void {
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed/monolog/monolog' );
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed/twig/twig' );
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed/crowdsec/capi-client' );
		$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/monolog/monolog/Logger.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/twig/twig/Environment.php', '<?php' );
		$this->fs->dumpFile( $this->tempDir.'/vendor_prefixed/crowdsec/capi-client/Watcher.php', '<?php' );
	}

	// =========================================================================
	// verify() - Pass scenarios
	// =========================================================================

	public function testVerifyPassesWithAllRequiredFilesAndDirectories() :void {
		$this->setupValidPackage();

		$this->expectNotToPerformAssertions();
		$verifier = $this->createVerifier();
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyLogsSuccessMessage() :void {
		$this->setupValidPackage();

		$messages = [];
		$verifier = $this->createVerifier( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );

		$verifier->verify( $this->tempDir );

		$hasSuccessMessage = \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'Package built successfully' ) !== false
		) ) > 0;
		$this->assertTrue( $hasSuccessMessage );
	}

	public function testVerifyPassesWhenRequiredPrefixedPackagesExist() :void {
		$this->setupValidPackage();
		$this->setupRequiredPrefixedPackages();

		$messages = [];
		$verifier = $this->createVerifier( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );
		$verifier->verify( $this->tempDir, [
			'monolog/monolog',
			'twig/twig',
			'crowdsec/capi-client',
		] );

		$passLogs = \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'PASS vendor_prefixed package exists:' ) !== false
		);
		$this->assertCount( 3, $passLogs );
	}

	// =========================================================================
	// verify() - Fail scenarios: Missing files
	// =========================================================================

	public function testVerifyFailsWhenPluginJsonMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempDir.'/plugin.json' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'plugin.json' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenMainPluginFileMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempDir.'/icwp-wpsf.php' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'icwp-wpsf.php' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenVendorAutoloadMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempDir.'/vendor/autoload.php' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor/autoload.php' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenRequiredPrefixedPackageMissing() :void {
		$this->setupValidPackage();
		$this->setupRequiredPrefixedPackages();
		$this->fs->remove( $this->tempDir.'/vendor_prefixed/twig/twig' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor_prefixed/twig/twig' );
		$verifier->verify( $this->tempDir, [
			'monolog/monolog',
			'twig/twig',
			'crowdsec/capi-client',
		] );
	}

	public function testVerifyFailsWhenRequiredPrefixedPackageDirectoryIsEmpty() :void {
		$this->setupValidPackage();
		$this->setupRequiredPrefixedPackages();
		$this->fs->remove( $this->tempDir.'/vendor_prefixed/twig/twig/Environment.php' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor_prefixed/twig/twig' );
		$verifier->verify( $this->tempDir, [
			'twig/twig',
		] );
	}

	public function testVerifyNormalizesRequiredPackageNamesToLowercase() :void {
		$this->setupValidPackage();
		$this->setupRequiredPrefixedPackages();

		$messages = [];
		$verifier = $this->createVerifier( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );
		$verifier->verify( $this->tempDir, [
			'Monolog/Monolog',
			'TWIG/TWIG',
		] );

		$this->assertTrue( \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'PASS vendor_prefixed package exists: monolog/monolog' ) !== false
		) ) > 0 );
		$this->assertTrue( \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'PASS vendor_prefixed package exists: twig/twig' ) !== false
		) ) > 0 );
	}

	public function testVerifyIgnoresInvalidRequiredPrefixedPackageEntries() :void {
		$this->setupValidPackage();
		$this->setupRequiredPrefixedPackages();

		$messages = [];
		$verifier = $this->createVerifier( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );
		$verifier->verify( $this->tempDir, [
			'monolog/monolog',
			'',
			null,
			123,
		] );

		$passLogs = \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'PASS vendor_prefixed package exists:' ) !== false
		);
		$this->assertCount( 1, $passLogs );
	}

	public function testVerifyFailsWhenMultipleRequiredPackagesMissingListsAll() :void {
		$this->setupValidPackage();
		$this->setupRequiredPrefixedPackages();
		$this->fs->remove( $this->tempDir.'/vendor_prefixed/twig/twig' );
		$this->fs->remove( $this->tempDir.'/vendor_prefixed/crowdsec/capi-client' );

		$verifier = $this->createVerifier();

		try {
			$verifier->verify( $this->tempDir, [
				'monolog/monolog',
				'twig/twig',
				'crowdsec/capi-client',
			] );
			$this->fail( 'Expected RuntimeException' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'vendor_prefixed/twig/twig', $e->getMessage() );
			$this->assertStringContainsString( 'vendor_prefixed/crowdsec/capi-client', $e->getMessage() );
		}
	}

	// =========================================================================
	// verify() - Fail scenarios: Missing directories
	// =========================================================================

	public function testVerifyFailsWhenVendorPrefixedMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempDir.'/vendor_prefixed' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor_prefixed' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenAssetsDistMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempDir.'/assets/dist' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'assets/dist' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenLegacySrcMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempDir.'/src/lib/src' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'legacy compat' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenLegacyVendorMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempDir.'/src/lib/vendor' );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'legacy compat' );
		$verifier->verify( $this->tempDir );
	}

	// =========================================================================
	// verify() - Error message quality
	// =========================================================================

	public function testVerifyErrorMessageListsAllMissingItems() :void {
		// Create an empty package directory
		$verifier = $this->createVerifier();

		try {
			$verifier->verify( $this->tempDir );
			$this->fail( 'Expected RuntimeException' );
		}
		catch ( \RuntimeException $e ) {
			$message = $e->getMessage();
			// Should mention multiple missing items
			$this->assertStringContainsString( 'plugin.json', $message );
			$this->assertStringContainsString( 'HOW TO FIX', $message );
		}
	}

	public function testVerifyLogsCheckMarksForFiles() :void {
		$this->setupValidPackage();

		$messages = [];
		$verifier = $this->createVerifier( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );

		$verifier->verify( $this->tempDir );

		// Should have logged checkmarks for files
		$hasCheckmarks = \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'exists' ) !== false
		) ) > 0;
		$this->assertTrue( $hasCheckmarks );
	}
}
