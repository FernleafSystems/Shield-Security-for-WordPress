<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use PHPUnit\Framework\TestCase;

/**
 * Tests to validate the plugin package structure after build
 */
class PluginPackageValidationTest extends TestCase {

	use PluginPathsTrait;

	/**
	 * @var string The path to the packaged plugin (set via environment variable)
	 */
	private string $packagePath;

	protected function setUp() :void {
		parent::setUp();
		
		$packageMode = $this->isTestingPackage();
		$this->packagePath = $this->getPluginRoot();
		
		if ( !$packageMode ) {
			$this->markTestSkipped( 'Package validation runs only when SHIELD_PACKAGE_PATH points to a built package.' );
		}
		
		if ( !is_dir( $this->packagePath ) ) {
			$this->markTestSkipped( 'Package directory not found: ' . $this->packagePath );
		}
	}

	/**
	 * Test that all required files are present in the package
	 */
	public function testRequiredFilesExist() :void {
		$requiredFiles = [
			'icwp-wpsf.php',
			'plugin.json',
			'readme.txt',
			'plugin_init.php',
			'plugin_autoload.php',
			'vendor/autoload.php',
			'vendor_prefixed/autoload.php',
		];

		foreach ( $requiredFiles as $file ) {
			$this->assertFileExists(
				$this->packagePath . '/' . $file,
				"Required file missing from package: $file"
			);
		}
	}

	/**
	 * Test that all required directories are present
	 */
	public function testRequiredDirectoriesExist() :void {
		$requiredDirs = [
			'assets/dist',
			'assets/images',
			'src',
			'vendor',
			'vendor_prefixed',
			'templates',
		];

		foreach ( $requiredDirs as $dir ) {
			$this->assertDirectoryExists(
				$this->packagePath . '/' . $dir,
				"Required directory missing from package: $dir"
			);
		}
	}

	/**
	 * Test that development files are NOT included in the package
	 */
	public function testDevelopmentFilesExcluded() :void {
		$excludedItems = [
			'.github',
			'tests',
			'bin/install-wp-tests.sh',
			'phpunit.xml',
			'composer.json',
			'composer.lock',
			'vendor/bin',
			'vendor/monolog',
			'vendor/twig',
			'vendor_prefixed/autoload-files.php',
		];

		foreach ( $excludedItems as $item ) {
			$path = $this->packagePath . '/' . $item;
			$this->assertFileDoesNotExist(
				$path,
				"Development file/directory should not be in package: $item"
			);
		}
	}

	/**
	 * Test that Strauss prefixing was applied
	 */
	public function testStraussPrefixingApplied() :void {
		// Check that vendor_prefixed directory has content
		$vendorPrefixedPath = $this->packagePath . '/vendor_prefixed';
		$this->assertDirectoryExists( $vendorPrefixedPath );
		
		// Check for specific prefixed directories
		$prefixedDirs = [
			'monolog',
			'crowdsec',
			'symfony',
			'twig',
		];
		
		foreach ( $prefixedDirs as $dir ) {
			$this->assertDirectoryExists(
				$vendorPrefixedPath . '/' . $dir,
				"Prefixed directory missing: $dir"
			);
		}
		
		// Check that autoload files exist
		$this->assertFileExists( $vendorPrefixedPath . '/autoload.php' );
	}

	/**
	 * Test that the main plugin file has correct header
	 */
	public function testPluginHeaderValid() :void {
		$pluginFile = $this->packagePath . '/icwp-wpsf.php';
		$this->assertFileExists( $pluginFile );
		
		$content = file_get_contents( $pluginFile );
		
		// Check for required plugin headers
		$this->assertStringContainsString( 'Plugin Name:', $content );
		$this->assertStringContainsString( 'Version:', $content );
		$this->assertStringContainsString( 'Description:', $content );
		$this->assertStringContainsString( 'Author:', $content );
	}

	/**
	 * Test that autoload files don't contain references to pruned dependencies
	 */
	public function testAutoloadFilesPruned() :void {
		$composerDir = $this->packagePath . '/vendor/composer';
		
		if ( !is_dir( $composerDir ) ) {
			$this->markTestSkipped( 'Composer directory not found' );
		}
		
		$autoloadFiles = [
			'autoload_files.php',
			'autoload_static.php',
			'autoload_psr4.php',
		];
		
		foreach ( $autoloadFiles as $file ) {
			$filePath = $composerDir . '/' . $file;
			if ( file_exists( $filePath ) ) {
				$content = file_get_contents( $filePath );
				$this->assertStringNotContainsString(
					'/twig/twig/',
					$content,
					"Autoload file should not contain twig references: $file"
				);
			}
		}
	}

	/**
	 * Test package file permissions (when applicable)
	 */
	public function testFilePermissions() :void {
		// Skip on Windows
		if ( PHP_OS_FAMILY === 'Windows' ) {
			$this->markTestSkipped( 'File permission tests not applicable on Windows' );
		}
		
		$mainPluginFile = $this->packagePath . '/icwp-wpsf.php';
		if ( file_exists( $mainPluginFile ) ) {
			$perms = fileperms( $mainPluginFile );
			$this->assertTrue(
				($perms & 0644) === 0644,
				'Main plugin file should be readable'
			);
		}
	}
}
