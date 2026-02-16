<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for plugin configuration and initialization
 */
class PluginConfigurationTest extends TestCase {

	use PluginPathsTrait;

	/**
	 * Test that plugin configuration can be loaded and parsed
	 */
	public function testPluginConfigurationLoadsCorrectly() :void {
		$config = $this->getPluginConfigData();
		$this->assertIsArray( $config );

		// Test properties
		$this->assertArrayHasKey( 'properties', $config );
		$properties = $config['properties'];

		// Test version exists and follows semantic versioning - don't hardcode specific version
		$this->assertArrayHasKey( 'version', $properties, 'Version property should exist' );
		$version = $properties['version'];
		$this->assertNotEmpty( $version, 'Version should not be empty' );
		$this->assertMatchesRegularExpression( '/^\d+(\.\d+)+$/', $version, 'Version should use numeric dot-separated segments (e.g. 21.1.9)' );
		
		$this->assertEquals( 'wp-simple-firewall', $properties['text_domain'] );
		$this->assertEquals( 'wpsf', $properties['slug_plugin'] );
		$this->assertEquals( 'icwp', $properties['slug_parent'] );
		$this->assertTrue( $properties['enable_premium'] );
	}

	/**
	 * Test that plugin requirements are properly defined
	 */
	public function testPluginRequirementsAreDefined() :void {
		$config = $this->getPluginConfigData();

		$this->assertArrayHasKey( 'requirements', $config );
		$requirements = $config['requirements'];

		// Check PHP version requirement
		$this->assertArrayHasKey( 'php', $requirements );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+$/', $requirements['php'], 'PHP version should follow X.Y format' );

		// Check WordPress version requirement
		$this->assertArrayHasKey( 'wordpress', $requirements );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+$/', $requirements['wordpress'], 'WordPress version should follow X.Y format' );

		// Check MySQL version requirement
		$this->assertArrayHasKey( 'mysql', $requirements );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+$/', $requirements['mysql'], 'MySQL version should follow X.Y format' );
	}

	/**
	 * Test that plugin paths are correctly configured
	 */
	public function testPluginPathsConfiguration() :void {
		$config = $this->getPluginConfigData();

		$this->assertArrayHasKey( 'paths', $config );
		$paths = $config['paths'];

		// Verify critical paths
		$expectedPaths = [
			'source' => 'src',
			'assets' => 'assets',
			'languages' => 'languages',
			'templates' => 'templates',
			'cache' => 'shield'
		];

		foreach ( $expectedPaths as $key => $expectedValue ) {
			$this->assertArrayHasKey( $key, $paths );
			$this->assertEquals( $expectedValue, $paths[$key] );
		}
	}

	/**
	 * Test that autoloader configuration is correct
	 */
	public function testAutoloaderPathExists() :void {
		$pluginAutoloadPath = $this->getPluginFilePath( 'plugin_autoload.php' );
		$this->assertFileExists( $pluginAutoloadPath, 'Plugin autoload file should exist' );
		
		$mainVendorAutoload = $this->getPluginFilePath( 'vendor/autoload.php' );
		$this->assertFileExists( $mainVendorAutoload, 'Main vendor autoload file should exist' );
	}

	/**
	 * Test plugin directory structure
	 */
	public function testPluginDirectoryStructure() :void {
		// Critical directories that should exist
		$criticalDirs = [
			'src',
			'assets',
			'languages',
			'templates'
		];

		foreach ( $criticalDirs as $dir ) {
			$this->assertDirectoryExists( 
				$this->getPluginFilePath( $dir ), 
				"Directory {$dir} should exist" 
			);
		}
	}

	/**
	 * Test that security modules are defined in configuration
	 */
	public function testSecurityModulesAreDefined() :void {
		$config = $this->getPluginConfigData();

		// Plugin should have modules section under config_spec
		$this->assertArrayHasKey( 'config_spec', $config );
		$this->assertArrayHasKey( 'modules', $config['config_spec'] );
		$modules = $config['config_spec']['modules'];

		$this->assertNotEmpty( $modules, 'At least one module should be defined' );

		foreach ( $modules as $moduleKey => $module ) {
			$this->assertArrayHasKey(
				'slug',
				$module,
				"Module '{$moduleKey}' should have a slug"
			);
			$this->assertEquals( $moduleKey, $module['slug'], "Module slug should match its key" );
		}
	}

	private function getPluginConfigData() :array {
		return $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration file' );
	}
}
