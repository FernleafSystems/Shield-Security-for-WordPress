<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Brain\Monkey;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test that core plugin files and autoloaders exist.
 * This should be one of the first tests to run to ensure the plugin structure is intact.
 */
class CoreFilesExistTest extends TestCase {

	use PluginPathsTrait;

	protected function setUp() :void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown() :void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that the main plugin autoloader exists
	 */
	public function testPluginAutoloaderExists() {
		$autoloader = $this->getPluginFilePath( 'plugin_autoload.php' );
		$this->assertFileExistsWithDebug( $autoloader, 'Main plugin autoloader file is missing' );
	}

	/**
	 * Test that the main vendor autoloader exists
	 */
	public function testMainVendorAutoloaderExists() {
		$autoloader = $this->getPluginFilePath( 'vendor/autoload.php' );
		$this->assertFileExistsWithDebug( $autoloader, 'Main vendor autoloader is missing - run: composer install' );
	}

	/**
	 * Test that the prefixed vendor autoloader exists
	 * Note: vendor_prefixed/ only exists after Strauss runs during packaging
	 */
	public function testPrefixedVendorAutoloaderExists() {
		if ( !$this->isTestingPackage() ) {
			$this->markTestSkipped( 'vendor_prefixed/ only exists in built packages, not in source' );
		}
		$autoloader = $this->getPluginFilePath( 'vendor_prefixed/autoload.php' );
		$this->assertFileExistsWithDebug( $autoloader, 'Prefixed vendor autoloader is missing - run build process to generate prefixed dependencies' );
	}

	/**
	 * Test that other critical plugin files exist
	 */
	public function testCriticalPluginFilesExist() {
		$criticalFiles = [
			'icwp-wpsf.php' => 'Main plugin file',
			'plugin_init.php' => 'Plugin initialization file',
			'plugin_compatibility.php' => 'Plugin compatibility file',
			'plugin.json' => 'Plugin configuration file',
		];

		foreach ( $criticalFiles as $file => $description ) {
			$path = $this->getPluginFilePath( $file );
			$this->assertFileExistsWithDebug( $path, sprintf( '%s is missing: %s', $description, $file ) );
		}
	}

	/**
	 * Test that the plugin can load DynPropertiesClass from vendor
	 */
	public function testDynPropertiesClassCanBeLoaded() {
		$this->assertTrue( 
			class_exists( 'FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass' ),
			'DynPropertiesClass not found - main vendor autoloader may not be working correctly'
		);
	}

	/**
	 * Test that we understand the dual vendor setup
	 * Note: We cannot test loading prefixed classes in unit tests because
	 * they conflict with the unprefixed versions. This is resolved during packaging.
	 * vendor_prefixed/ only exists after Strauss runs during packaging.
	 */
	public function testDualVendorSetupIsUnderstood() {
		$mainTwig = $this->getPluginFilePath( 'vendor/twig/twig/src/Environment.php' );
		$prefixedTwig = $this->getPluginFilePath( 'vendor_prefixed/twig/twig/src/Environment.php' );

		if ( $this->isTestingPackage() ) {
			// In package, Twig should NOT exist in main vendor (removed during packaging)
			$this->assertFileDoesNotExist( $mainTwig, 'Twig should be removed from main vendor in package' );
			// Verify that the prefixed Twig exists (will be the only one after packaging)
			$this->assertFileExistsWithDebug( $prefixedTwig, 'Prefixed Twig should exist in vendor_prefixed' );
		} else {
			// In development, Twig exists in main vendor
			$this->assertFileExistsWithDebug( $mainTwig, 'Twig should exist in main vendor during development' );
			// In development, vendor_prefixed/ doesn't exist yet (created during packaging)
			$this->assertFileDoesNotExist( $prefixedTwig, 'Prefixed Twig should not exist in source - only created during packaging' );
		}

		// This dual existence is why we CANNOT load both autoloaders in tests
		// The packaging process removes the main vendor version to prevent conflicts
	}
}

