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
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version, 'Version should follow semantic versioning format (X.Y.Z)' );
		
		// Verify version in config matches version in main plugin file
		$pluginContent = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		$this->assertStringContainsString( "Version: {$version}", $pluginContent, 'Version in config should match version in main plugin file' );
		
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
		$this->assertEquals( '7.4', $requirements['php'] );

		// Check WordPress version requirement
		$this->assertArrayHasKey( 'wordpress', $requirements );
		$this->assertEquals( '5.7', $requirements['wordpress'] );

		// Check MySQL version requirement
		$this->assertArrayHasKey( 'mysql', $requirements );
		$this->assertEquals( '5.6', $requirements['mysql'] );
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

		// Expected security modules based on actual plugin configuration
		$expectedModules = [
			'admin_access_restriction',
			'firewall',
			'hack_protect',
			'ips',
			'login_protect',
			'audit_trail',
			'comments_filter',
			'integrations',
			'plugin',
			'user_management'
		];

		foreach ( $expectedModules as $moduleKey ) {
			$this->assertArrayHasKey( 
				$moduleKey, 
				$modules, 
				"Security module '{$moduleKey}' should be defined" 
			);

			// Each module should have a slug
			$module = $modules[$moduleKey];
			$this->assertArrayHasKey( 'slug', $module );
		}
	}

	private function getPluginConfigData() :array {
		return $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration file' );
	}
}
