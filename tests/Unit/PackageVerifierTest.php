<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PackageVerifier;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Unit tests for PackageVerifier.
 * Tests package verification pass/fail scenarios.
 */
class PackageVerifierTest extends TestCase {

	use TempPathJoinTrait;

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = Path::join( sys_get_temp_dir(), 'shield-test-'.uniqid() );
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
		$this->fs->dumpFile( $this->tempPath( 'plugin.json' ), '{}' );
		$this->fs->dumpFile( $this->tempPath( 'icwp-wpsf.php' ), '<?php' );
		$this->fs->dumpFile( $this->tempPath( 'vendor/autoload.php' ), '<?php' );

		// Create all required directories
		$this->fs->mkdir( $this->tempPath( 'vendor_prefixed' ) );
		$this->fs->mkdir( $this->tempPath( 'assets/dist' ) );
		$this->fs->mkdir( $this->tempPath( 'src/lib/src' ) );
		$this->fs->mkdir( $this->tempPath( 'src/lib/vendor' ) );
	}

	// =========================================================================
	// verify() - Pass scenarios
	// =========================================================================

	public function testVerifyPassesWithAllRequiredFilesAndDirectories() :void {
		$this->setupValidPackage();

		$verifier = $this->createVerifier();

		// Should not throw
		$verifier->verify( $this->tempDir );
		$this->assertTrue( true );
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

	// =========================================================================
	// verify() - Fail scenarios: Missing files
	// =========================================================================

	public function testVerifyFailsWhenPluginJsonMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'plugin.json' ) );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'plugin.json' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenMainPluginFileMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'icwp-wpsf.php' ) );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'icwp-wpsf.php' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenVendorAutoloadMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'vendor/autoload.php' ) );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor/autoload.php' );
		$verifier->verify( $this->tempDir );
	}

	// =========================================================================
	// verify() - Fail scenarios: Missing directories
	// =========================================================================

	public function testVerifyFailsWhenVendorPrefixedMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'vendor_prefixed' ) );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor_prefixed' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenAssetsDistMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'assets/dist' ) );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'assets/dist' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenLegacySrcMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'src/lib/src' ) );

		$verifier = $this->createVerifier();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'legacy compat' );
		$verifier->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenLegacyVendorMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'src/lib/vendor' ) );

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

