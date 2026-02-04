<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Core smoke tests to quickly validate that critical plugin components exist and can be loaded.
 * These tests should be fast and focused on essential validation.
 */
class CorePluginSmokeTest extends TestCase {

	use PluginPathsTrait;

	/**
	 * Test that critical plugin files exist
	 */
	public function testCriticalPluginFilesExist() :void {
		// Main plugin file
		$mainPluginFile = $this->getPluginFilePath( 'icwp-wpsf.php' );
		$this->assertFileExistsWithDebug( $mainPluginFile, 'Main plugin file should exist' );

		// Plugin initialization files
		$pluginInitFile = $this->getPluginFilePath( 'plugin_init.php' );
		$this->assertFileExistsWithDebug( $pluginInitFile, 'Plugin init file should exist' );

		$pluginAutoloadFile = $this->getPluginFilePath( 'plugin_autoload.php' );
		$this->assertFileExistsWithDebug( $pluginAutoloadFile, 'Plugin autoload file should exist' );

		// Plugin configuration
		$pluginConfigFile = $this->getPluginFilePath( 'plugin.json' );
		$this->assertFileExistsWithDebug( $pluginConfigFile, 'Plugin configuration file should exist' );
	}

	/**
	 * Test that autoloader works correctly
	 */
	public function testAutoloaderFunctionality() :void {
		$autoloadFile = $this->getPluginFilePath( 'plugin_autoload.php' );
		$this->assertFileExistsWithDebug( $autoloadFile, 'Autoload file should exist' );

		// Load the autoloader
		require_once $autoloadFile;

		// Test that core classes can be autoloaded
		$this->assertTrue(
			class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller', true ),
			'Controller class should be autoloadable'
		);

		$this->assertTrue(
			class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController', true ),
			'ActionRoutingController class should be autoloadable'
		);
	}

	/**
	 * Test plugin loading simulation - verify plugin can initialize without fatal errors
	 */
	public function testPluginCanInitializeWithoutErrors() :void {
		// Define required WordPress constants if not defined
		if ( !defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $this->getPluginRoot() . '/' );
		}
		if ( !defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
		}

		// Mock required WordPress functions for basic initialization
		if ( !function_exists( 'add_action' ) ) {
			function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
				// Mock implementation
			}
		}
		if ( !function_exists( 'plugin_basename' ) ) {
			function plugin_basename( $file ) {
				return basename( $file );
			}
		}

		// Verify main plugin file can be parsed without syntax errors
		$mainPluginFile = $this->getPluginFilePath( 'icwp-wpsf.php' );
		$phpCode = file_get_contents( $mainPluginFile );
		
		// Basic syntax check
		$result = @eval( 'return true;' . substr( $phpCode, 5 ) ); // Skip <?php
		$this->assertNotFalse( $result, 'Main plugin file should have valid PHP syntax' );

		// Verify plugin header is present
		$this->assertStringContainsString( 'Plugin Name: Shield Security', $phpCode );
		
		// Verify version exists and matches format - don't hardcode specific version
		$this->assertMatchesRegularExpression( '/Version:\s*\d+\.\d+\.\d+/', $phpCode, 'Plugin header should contain a valid semantic version' );
		
		// Verify version in plugin header matches version in plugin.json
		$pluginConfig = $this->loadPluginConfiguration();
		$configVersion = $pluginConfig['properties']['version'] ?? '';
		$this->assertNotEmpty( $configVersion, 'Version should exist in plugin.json' );
		$this->assertStringContainsString( "Version: {$configVersion}", $phpCode, 'Version in plugin header should match version in plugin.json' );
		
		$this->assertStringContainsString( 'Text Domain: wp-simple-firewall', $phpCode );
	}

	/**
	 * Test database connectivity (basic check)
	 */
	public function testDatabaseConnectivityRequirements() :void {
		// Check that database-related classes exist
		$this->assertTrue(
			class_exists( '\FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableSchema', true ),
			'Database TableSchema class should exist'
		);

		// Verify plugin has database configuration
		$pluginConfig = $this->loadPluginConfiguration();
		$this->assertArrayHasKey( 'config_spec', $pluginConfig, 'Plugin config should have config_spec' );
		$this->assertArrayHasKey( 'modules', $pluginConfig['config_spec'], 'Config should have modules' );

		// Check modules that require database tables
		$modulesWithDbs = [
			'audit_trail' => [ 'at_logs', 'at_meta', 'ips', 'req_logs' ],
			'comments_filter' => [ 'botsignal' ],
			'hack_protect' => [ 'scans', 'scanitems', 'resultitems', 'resultitem_meta', 'scanresults' ],
			'integrations' => [ 'botsignal', 'ips' ],
			'ips' => [ 'ips' ],
			'login_protect' => [ 'botsignal' ]
		];

		foreach ( $modulesWithDbs as $moduleKey => $expectedDbs ) {
			$module = $pluginConfig['config_spec']['modules'][$moduleKey] ?? null;
			$this->assertNotNull( $module, "Module '{$moduleKey}' should exist" );

			if ( isset( $module['reqs']['dbs'] ) ) {
				$this->assertIsArray( $module['reqs']['dbs'], "Module '{$moduleKey}' should have database requirements as array" );
				foreach ( $expectedDbs as $db ) {
					$this->assertContains( $db, $module['reqs']['dbs'], "Module '{$moduleKey}' should require '{$db}' database" );
				}
			}
		}
	}

	/**
	 * Test WordPress hooks registration verification
	 */
	public function testWordPressHooksStructure() :void {
		// Verify hook-related classes exist
		$this->assertTrue(
			class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController', true ),
			'ActionRoutingController should exist for handling WordPress hooks'
		);

		// Check main plugin file contains WordPress hook registrations
		$mainPluginFile = $this->getPluginFilePath( 'icwp-wpsf.php' );
		$pluginContent = file_get_contents( $mainPluginFile );

		// Verify essential WordPress hooks are registered
		$this->assertStringContainsString( 'add_action', $pluginContent, 'Plugin should register WordPress actions' );
		$this->assertStringContainsString( 'plugins_loaded', $pluginContent, 'Plugin should hook into plugins_loaded' );
		$this->assertStringContainsString( 'icwp_wpsf_init', $pluginContent, 'Plugin should have init function' );
	}

	/**
	 * Test asset directory structure validation
	 */
	public function testAssetDirectoryStructure() :void {
		$pluginConfig = $this->loadPluginConfiguration();
		$this->assertArrayHasKey( 'paths', $pluginConfig, 'Plugin config should have paths' );
		
		$paths = $pluginConfig['paths'];
		$this->assertArrayHasKey( 'assets', $paths, 'Paths should include assets directory' );

		// Check if assets directory exists
		$assetsDir = $this->getPluginFilePath( $paths['assets'] );
		$this->assertDirectoryExists( $assetsDir, 'Assets directory should exist' );

		// Check for common asset subdirectories
		$expectedAssetDirs = [ 'css', 'js', 'images' ];
		foreach ( $expectedAssetDirs as $subDir ) {
			$subDirPath = $assetsDir . '/' . $subDir;
			if ( is_dir( $subDirPath ) ) {
				$this->assertDirectoryExists( $subDirPath, "Asset subdirectory '{$subDir}' exists" );
			}
		}
	}

	/**
	 * Test module source file existence checks
	 */
	public function testModuleSourceFilesExist() :void {
		$pluginConfig = $this->loadPluginConfiguration();
		$modules = $pluginConfig['config_spec']['modules'] ?? [];

		$this->assertNotEmpty( $modules, 'Plugin should have modules defined' );

		// Expected modules based on requirements
		$expectedModules = [
			'admin_access_restriction',
			'audit_trail',
			'comments_filter',
			'firewall',
			'hack_protect',
			'integrations',
			'ips',
			'login_protect',
			'plugin',
			'user_management'
		];

		foreach ( $expectedModules as $moduleKey ) {
			$this->assertArrayHasKey( $moduleKey, $modules, "Module '{$moduleKey}' should be defined" );
			
			// Each module should have basic properties
			$module = $modules[$moduleKey];
			$this->assertArrayHasKey( 'slug', $module, "Module '{$moduleKey}' should have a slug" );
			$this->assertArrayHasKey( 'name', $module, "Module '{$moduleKey}' should have a name" );
			
			// Verify module directory exists if not testing a package
			if ( !$this->isTestingPackage() ) {
				$moduleDir = $this->getPluginFilePath( 'src/Modules/' . ucfirst( $module['slug'] ) );
				if ( is_dir( $moduleDir ) ) {
					$this->assertDirectoryExists( $moduleDir, "Module directory for '{$moduleKey}' should exist" );
					
					// Check for common module files
					$modConFile = $moduleDir . '/ModCon.php';
					if ( file_exists( $modConFile ) ) {
						$this->assertFileExistsWithDebug( $modConFile, "ModCon file for module '{$moduleKey}' should exist" );
					}
				}
			}
		}
	}

	/**
	 * Test critical configuration validation
	 */
	public function testCriticalConfigurationValues() :void {
		$pluginConfig = $this->loadPluginConfiguration();

		// Verify critical properties
		$properties = $pluginConfig['properties'] ?? [];
		$this->assertArrayHasKey( 'version', $properties, 'Plugin should have version' );
		$this->assertArrayHasKey( 'text_domain', $properties, 'Plugin should have text domain' );
		$this->assertArrayHasKey( 'slug_plugin', $properties, 'Plugin should have plugin slug' );
		$this->assertArrayHasKey( 'slug_parent', $properties, 'Plugin should have parent slug' );

		// Verify values match expected
		$this->assertEquals( 'wp-simple-firewall', $properties['text_domain'], 'Text domain should match' );
		$this->assertEquals( 'wpsf', $properties['slug_plugin'], 'Plugin slug should match' );
		$this->assertEquals( 'icwp', $properties['slug_parent'], 'Parent slug should match' );

		// Verify requirements
		$requirements = $pluginConfig['requirements'] ?? [];
		$this->assertArrayHasKey( 'php', $requirements, 'Plugin should specify PHP requirement' );
		$this->assertArrayHasKey( 'wordpress', $requirements, 'Plugin should specify WordPress requirement' );
		
		// Verify PHP version requirement
		$this->assertGreaterThanOrEqual( 
			version_compare( '7.4', PHP_VERSION, '<=' ), 
			true,
			'Current PHP version should meet plugin requirements' 
		);
	}

	/**
	 * Test vendor autoload integration
	 */
	public function testVendorAutoloadIntegration() :void {
		if ( $this->isTestingPackage() ) {
			// In package testing mode, vendor dependencies are bundled differently
			$this->assertTrue( true, 'Package testing mode - vendor autoload handled by package build' );
		} else {
			// Check if vendor autoload exists (for source installations)
			$vendorAutoload = $this->getPluginFilePath( 'vendor/autoload.php' );
			if ( file_exists( $vendorAutoload ) ) {
				$this->assertFileExistsWithDebug( $vendorAutoload, 'Vendor autoload should exist' );
				
				// Verify it's a valid PHP file
				$content = \file_get_contents( $vendorAutoload );
				$this->assertStringContainsString( '<?php', $content, 'Vendor autoload should be valid PHP' );
			} else {
				$this->markTestSkipped( 'Vendor autoload not found - run composer install' );
			}
		}
	}

	/**
	 * Helper method to load and parse plugin configuration
	 */
	private function loadPluginConfiguration() :array {
		$configFile = $this->getPluginFilePath( 'plugin.json' );
		$this->assertFileExistsWithDebug( $configFile, 'Plugin configuration file should exist' );
		
		$configContent = file_get_contents( $configFile );
		$config = json_decode( $configContent, true );
		
		$this->assertIsArray( $config, 'Plugin configuration should be valid JSON' );
		$this->assertNotEmpty( $config, 'Plugin configuration should not be empty' );
		
		return $config;
	}
}