<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PostStraussCleanup;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Unit tests for PostStraussCleanup.
 * Tests directory removal, file removal, and autoload cleaning.
 */
class PostStraussCleanupTest extends TestCase {

	use TempPathJoinTrait;
	use TempDirLifecycleTrait;

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = $this->createTrackedTempDir( 'shield-post-strauss-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	private function createCleanup( ?callable $logger = null ) :PostStraussCleanup {
		$directoryRemover = new SafeDirectoryRemover( $this->tempDir );
		return new PostStraussCleanup( $directoryRemover, $logger ?? function () {} );
	}

	/**
	 * @return string[]
	 */
	private function prefixedPackages() :array {
		return [
			'twig/twig',
			'monolog/monolog',
		];
	}

	// =========================================================================
	// cleanPackageFiles() tests
	// =========================================================================

	public function testCleanPackageFilesRemovesVendorTwigPackageAndEmptyNamespace() :void {
		$twigNamespaceDir = $this->tempPath( 'vendor/twig' );
		$twigPackageDir = $this->tempPath( 'vendor/twig/twig' );
		$this->fs->mkdir( $twigPackageDir );
		$this->fs->dumpFile( Path::join( $twigPackageDir, 'file.php' ), '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir, null, $this->prefixedPackages() );

		$this->assertDirectoryDoesNotExist( $twigPackageDir );
		$this->assertDirectoryDoesNotExist( $twigNamespaceDir );
	}

	public function testCleanPackageFilesRemovesVendorMonologPackageAndEmptyNamespace() :void {
		$monologNamespaceDir = $this->tempPath( 'vendor/monolog' );
		$monologPackageDir = $this->tempPath( 'vendor/monolog/monolog' );
		$this->fs->mkdir( $monologPackageDir );
		$this->fs->dumpFile( Path::join( $monologPackageDir, 'file.php' ), '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir, null, $this->prefixedPackages() );

		$this->assertDirectoryDoesNotExist( $monologPackageDir );
		$this->assertDirectoryDoesNotExist( $monologNamespaceDir );
	}

	public function testCleanPackageFilesKeepsNamespaceWithUnprefixedSibling() :void {
		$serializerDir = $this->tempPath( 'vendor/symfony/serializer' );
		$stringDir = $this->tempPath( 'vendor/symfony/string' );
		$this->fs->mkdir( [ $serializerDir, $stringDir ] );
		$this->fs->dumpFile( Path::join( $serializerDir, 'Serializer.php' ), '<?php' );
		$this->fs->dumpFile( Path::join( $stringDir, 'UnicodeString.php' ), '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir, null, [ 'symfony/serializer' ] );

		$this->assertDirectoryDoesNotExist( $serializerDir );
		$this->assertDirectoryExists( $stringDir );
		$this->assertDirectoryExists( $this->tempPath( 'vendor/symfony' ) );
	}

	public function testCleanPackageFilesRemovesVendorBin() :void {
		// Setup: Create vendor/bin directory
		$binDir = $this->tempPath( 'vendor/bin' );
		$this->fs->mkdir( $binDir );
		$this->fs->dumpFile( Path::join( $binDir, 'phpunit' ), '#!/bin/bash' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir, null, $this->prefixedPackages() );

		$this->assertDirectoryDoesNotExist( $binDir );
	}

	public function testCleanPackageFilesRemovesAutoloadFilesPhp() :void {
		// Setup: Create vendor_prefixed/autoload-files.php
		$this->fs->mkdir( $this->tempPath( 'vendor_prefixed' ) );
		$autoloadFile = $this->tempPath( 'vendor_prefixed/autoload-files.php' );
		$this->fs->dumpFile( $autoloadFile, '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir, null, $this->prefixedPackages() );

		$this->assertFileDoesNotExist( $autoloadFile );
	}

	public function testCleanPackageFilesRemovesStraussPhar() :void {
		// Setup: Create strauss.phar
		$straussPhar = $this->tempPath( 'strauss.phar' );
		$this->fs->dumpFile( $straussPhar, '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir, null, $this->prefixedPackages() );

		$this->assertFileDoesNotExist( $straussPhar );
	}

	public function testCleanPackageFilesHandlesMissingDirectoriesGracefully() :void {
		// No directories exist - should not throw
		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir, null, $this->prefixedPackages() );

		$this->assertTrue( true ); // If we get here, no exception was thrown
	}

	// =========================================================================
	// cleanAutoloadFiles() tests
	// =========================================================================

	public function testCleanAutoloadFilesRemovesPrefixedPackageReferences() :void {
		// Setup: Create autoload file with prefixed package references.
		$composerDir = $this->tempPath( 'vendor/composer' );
		$this->fs->mkdir( $composerDir );
		$autoloadFile = Path::join( $composerDir, 'autoload_files.php' );

		// Intentional manual joins in fixture text: this string mimics Composer autoload file internals.
		$content = <<<'PHP'
<?php
$vendorDir = dirname(__DIR__);
return array(
    'abc123' => $vendorDir . '/twig/twig/src/Extension/ExtensionInterface.php',
    'def456' => $vendorDir . '/monolog/monolog/src/Logger.php',
);
PHP;
		$this->fs->dumpFile( $autoloadFile, $content );

		$cleanup = $this->createCleanup();
		$cleanup->cleanAutoloadFiles( $this->tempDir, $this->prefixedPackages() );

		$newContent = file_get_contents( $autoloadFile );
		$this->assertStringNotContainsString( '/twig/twig/', $newContent );
		$this->assertStringNotContainsString( '/monolog/monolog/', $newContent );
	}

	public function testCleanAutoloadFilesPreservesLineEndings() :void {
		// Setup: Create file with CRLF line endings
		$composerDir = $this->tempPath( 'vendor/composer' );
		$this->fs->mkdir( $composerDir );
		$autoloadFile = Path::join( $composerDir, 'autoload_files.php' );

		$content = "<?php\r\n\$twig = '/twig/twig/src/file.php';\r\n\$other = 'keep';\r\n";
		$this->fs->dumpFile( $autoloadFile, $content );

		$cleanup = $this->createCleanup();
		$cleanup->cleanAutoloadFiles( $this->tempDir, $this->prefixedPackages() );

		$newContent = file_get_contents( $autoloadFile );
		// Should still have CRLF line endings (minus the removed line)
		$this->assertStringContainsString( "\r\n", $newContent );
	}

	public function testCleanAutoloadFilesSkipsMissingComposerDirectory() :void {
		// No composer directory exists
		$messages = [];
		$cleanup = $this->createCleanup( function ( string $msg ) use ( &$messages ) {
			$messages[] = $msg;
		} );
		$cleanup->cleanAutoloadFiles( $this->tempDir, $this->prefixedPackages() );

		$this->assertTrue( \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'Warning' ) !== false
		) ) > 0 );
	}

	public function testCleanAutoloadFilesProcessesMultipleFiles() :void {
		// Setup: Create multiple autoload files
		$composerDir = $this->tempPath( 'vendor/composer' );
		$this->fs->mkdir( $composerDir );

		$files = [ 'autoload_files.php', 'autoload_static.php', 'autoload_psr4.php' ];
		foreach ( $files as $file ) {
			$this->fs->dumpFile(
				Path::join( $composerDir, $file ),
				"<?php\n// /twig/twig/ reference\n"
			);
		}

		$cleanup = $this->createCleanup();
		$cleanup->cleanAutoloadFiles( $this->tempDir, $this->prefixedPackages() );

		foreach ( $files as $file ) {
			$content = file_get_contents( Path::join( $composerDir, $file ) );
			$this->assertStringNotContainsString( '/twig/twig/', $content );
		}
	}
}
