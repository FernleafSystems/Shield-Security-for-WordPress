<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for plugin configuration and initialization
 */
class PluginConfigurationTest extends TestCase {

	/**
	 * Test that plugin configuration can be loaded and parsed
	 */
	public function testPluginConfigurationLoadsCorrectly() :void {
		$configPath = dirname( dirname( __DIR__ ) ) . '/plugin.json';
		$this->assertFileExists( $configPath );

		$config = json_decode( file_get_contents( $configPath ), true );
		$this->assertIsArray( $config );

		// Test properties
		$this->assertArrayHasKey( 'properties', $config );
		$properties = $config['properties'];

		$this->assertEquals( '21.0.7', $properties['version'] );
		$this->assertEquals( 'wp-simple-firewall', $properties['text_domain'] );
		$this->assertEquals( 'wpsf', $properties['slug_plugin'] );
		$this->assertEquals( 'icwp', $properties['slug_parent'] );
		$this->assertTrue( $properties['enable_premium'] );
	}

	/**
	 * Test that plugin requirements are properly defined
	 */
	public function testPluginRequirementsAreDefined() :void {
		$configPath = dirname( dirname( __DIR__ ) ) . '/plugin.json';
		$config = json_decode( file_get_contents( $configPath ), true );

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
		$configPath = dirname( dirname( __DIR__ ) ) . '/plugin.json';
		$config = json_decode( file_get_contents( $configPath ), true );

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
		$baseDir = dirname( dirname( __DIR__ ) );
		$autoloadPath = $baseDir . '/lib/vendor/autoload.php';
		
		// The main plugin autoloader should exist
		$pluginAutoloadPath = $baseDir . '/plugin_autoload.php';
		$this->assertFileExists( $pluginAutoloadPath, 'Plugin autoload file should exist' );
	}

	/**
	 * Test plugin directory structure
	 */
	public function testPluginDirectoryStructure() :void {
		$baseDir = dirname( dirname( __DIR__ ) );

		// Critical directories that should exist
		$criticalDirs = [
			'src/lib/src',
			'assets',
			'languages',
			'templates'
		];

		foreach ( $criticalDirs as $dir ) {
			$this->assertDirectoryExists( 
				$baseDir . '/' . $dir, 
				"Directory {$dir} should exist" 
			);
		}
	}

	/**
	 * Test that security modules are defined in configuration
	 */
	public function testSecurityModulesAreDefined() :void {
		$configPath = dirname( dirname( __DIR__ ) ) . '/plugin.json';
		$config = json_decode( file_get_contents( $configPath ), true );

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
}
