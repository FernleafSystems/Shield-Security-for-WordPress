<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PostStraussCleanup;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for PostStraussCleanup.
 * Tests directory removal, file removal, and autoload cleaning.
 */
class PostStraussCleanupTest extends TestCase {

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

	private function createCleanup( ?callable $logger = null ) :PostStraussCleanup {
		$directoryRemover = new SafeDirectoryRemover( $this->tempDir );
		return new PostStraussCleanup( $directoryRemover, $logger ?? function () {} );
	}

	// =========================================================================
	// cleanPackageFiles() tests
	// =========================================================================

	public function testCleanPackageFilesRemovesVendorTwig() :void {
		// Setup: Create vendor/twig directory
		$twigDir = $this->tempDir.'/vendor/twig';
		$this->fs->mkdir( $twigDir );
		$this->fs->dumpFile( $twigDir.'/file.php', '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir );

		$this->assertDirectoryDoesNotExist( $twigDir );
	}

	public function testCleanPackageFilesRemovesVendorMonolog() :void {
		// Setup: Create vendor/monolog directory
		$monologDir = $this->tempDir.'/vendor/monolog';
		$this->fs->mkdir( $monologDir );
		$this->fs->dumpFile( $monologDir.'/file.php', '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir );

		$this->assertDirectoryDoesNotExist( $monologDir );
	}

	public function testCleanPackageFilesRemovesVendorBin() :void {
		// Setup: Create vendor/bin directory
		$binDir = $this->tempDir.'/vendor/bin';
		$this->fs->mkdir( $binDir );
		$this->fs->dumpFile( $binDir.'/phpunit', '#!/bin/bash' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir );

		$this->assertDirectoryDoesNotExist( $binDir );
	}

	public function testCleanPackageFilesRemovesAutoloadFilesPhp() :void {
		// Setup: Create vendor_prefixed/autoload-files.php
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed' );
		$autoloadFile = $this->tempDir.'/vendor_prefixed/autoload-files.php';
		$this->fs->dumpFile( $autoloadFile, '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir );

		$this->assertFileDoesNotExist( $autoloadFile );
	}

	public function testCleanPackageFilesRemovesStraussPhar() :void {
		// Setup: Create strauss.phar
		$straussPhar = $this->tempDir.'/strauss.phar';
		$this->fs->dumpFile( $straussPhar, '<?php' );

		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir );

		$this->assertFileDoesNotExist( $straussPhar );
	}

	public function testCleanPackageFilesHandlesMissingDirectoriesGracefully() :void {
		// No directories exist - should not throw
		$cleanup = $this->createCleanup();
		$cleanup->cleanPackageFiles( $this->tempDir );

		$this->assertTrue( true ); // If we get here, no exception was thrown
	}

	// =========================================================================
	// cleanAutoloadFiles() tests
	// =========================================================================

	public function testCleanAutoloadFilesRemovesTwigReferences() :void {
		// Setup: Create autoload file with twig references
		$composerDir = $this->tempDir.'/vendor/composer';
		$this->fs->mkdir( $composerDir );
		$autoloadFile = $composerDir.'/autoload_files.php';

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
		$cleanup->cleanAutoloadFiles( $this->tempDir );

		$newContent = file_get_contents( $autoloadFile );
		$this->assertStringNotContainsString( '/twig/twig/', $newContent );
		$this->assertStringContainsString( '/monolog/monolog/', $newContent );
	}

	public function testCleanAutoloadFilesPreservesLineEndings() :void {
		// Setup: Create file with CRLF line endings
		$composerDir = $this->tempDir.'/vendor/composer';
		$this->fs->mkdir( $composerDir );
		$autoloadFile = $composerDir.'/autoload_files.php';

		$content = "<?php\r\n\$twig = '/twig/twig/src/file.php';\r\n\$other = 'keep';\r\n";
		$this->fs->dumpFile( $autoloadFile, $content );

		$cleanup = $this->createCleanup();
		$cleanup->cleanAutoloadFiles( $this->tempDir );

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
		$cleanup->cleanAutoloadFiles( $this->tempDir );

		$this->assertTrue( \count( \array_filter(
			$messages,
			fn( $m ) => \strpos( $m, 'Warning' ) !== false
		) ) > 0 );
	}

	public function testCleanAutoloadFilesProcessesMultipleFiles() :void {
		// Setup: Create multiple autoload files
		$composerDir = $this->tempDir.'/vendor/composer';
		$this->fs->mkdir( $composerDir );

		$files = [ 'autoload_files.php', 'autoload_static.php', 'autoload_psr4.php' ];
		foreach ( $files as $file ) {
			$this->fs->dumpFile(
				$composerDir.'/'.$file,
				"<?php\n// twig/twig reference\n"
			);
		}

		$cleanup = $this->createCleanup();
		$cleanup->cleanAutoloadFiles( $this->tempDir );

		foreach ( $files as $file ) {
			$content = file_get_contents( $composerDir.'/'.$file );
			$this->assertStringNotContainsString( '/twig/twig/', $content );
		}
	}
}
