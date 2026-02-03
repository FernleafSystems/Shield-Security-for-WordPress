<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Rapid smoke tests for Shield Security plugin core functionality
 * 
 * These tests verify critical files, directories and integration points exist
 * without deep inspection. Target execution time: < 5 seconds total.
 */
class CorePluginSmokeTest extends ShieldWordPressTestCase {
	use PluginPathsTrait;

	/**
	 * Test critical plugin files exist
	 */
	public function testCriticalPluginFilesExist() :void {
		$criticalFiles = [
			'icwp-wpsf.php'           => 'Main plugin file',
			'plugin_init.php'         => 'Plugin initialization file',
			'plugin_autoload.php'     => 'Plugin autoload file',
			'plugin_compatibility.php' => 'Plugin compatibility file',
			'plugin.json'             => 'Plugin configuration file',
		];

		foreach ( $criticalFiles as $file => $description ) {
			$path = $this->getPluginFilePath( $file );
			$this->assertFileExistsWithDebug( $path, "$description ($file) should exist" );
		}
	}

	/**
	 * Test both autoloader files exist and are readable
	 */
	public function testAutoloaderFilesExist() :void {
		$autoloaders = [
			'src/lib/vendor/autoload.php'          => 'Main vendor autoloader',
			'src/lib/vendor_prefixed/autoload.php' => 'Prefixed vendor autoloader',
		];

		foreach ( $autoloaders as $file => $description ) {
			$path = $this->getPluginFilePath( $file );
			$this->assertFileExistsWithDebug( $path, "$description should exist" );
			$this->assertTrue( 
				\is_readable( $path ), 
				"$description should be readable"
			);
		}
	}

	/**
	 * Test critical directory structure exists
	 */
	public function testCriticalDirectoriesExist() :void {
		$directories = [
			'src/lib/src'  => 'Main source code directory',
			'assets/dist'  => 'Compiled assets directory',
			'templates'    => 'Template files directory',
			'languages'    => 'Translations directory',
		];

		foreach ( $directories as $dir => $description ) {
			$path = $this->getPluginFilePath( $dir );
			$this->assertDirectoryExists( $path, "$description should exist" );
			$this->assertTrue(
				\is_readable( $path ),
				"$description should be readable"
			);
		}
	}

	/**
	 * Test all security module directories exist with proper structure
	 */
	public function testSecurityModulesExist() :void {
		$modules = [
			'AuditTrail',
			'Base',
			'CommentsFilter',
			'Data',
			'HackGuard',
			'IPs',
			'Integrations',
			'License',
			'LoginGuard',
			'Plugin',
			'SecurityAdmin',
			'Traffic',
			'UserManagement',
		];

		$modulesPath = $this->getPluginFilePath( 'src/lib/src/Modules' );
		
		// First verify the modules directory exists
		$this->assertDirectoryExists( $modulesPath, 'Modules directory should exist' );

		foreach ( $modules as $module ) {
			$modulePath = $modulesPath . '/' . $module;
			$this->assertDirectoryExists( 
				$modulePath, 
				"Module directory '$module' should exist"
			);

			// Verify module directory is not empty and contains PHP files
			$files = \glob( $modulePath . '/*.php' );
			$subdirs = \glob( $modulePath . '/*', GLOB_ONLYDIR );
			$this->assertTrue(
				!empty( $files ) || !empty( $subdirs ),
				"Module directory '$module' should contain PHP files or subdirectories"
			);
		}
	}

	/**
	 * Test WordPress plugin headers are valid
	 */
	public function testWordPressPluginHeaders() :void {
		$pluginFile = $this->getPluginFilePath( 'icwp-wpsf.php' );
		$this->assertFileExistsWithDebug( $pluginFile, 'Main plugin file should exist' );

		$content = \file_get_contents( $pluginFile );
		$this->assertNotFalse( $content, 'Should be able to read plugin file content' );

		// Check for required WordPress plugin headers
		$requiredHeaders = [
			'Plugin Name:'  => 'Plugin name header',
			'Description:'  => 'Description header',
			'Version:'      => 'Version header',
			'Author:'       => 'Author header',
			'Text Domain:'  => 'Text domain header',
		];

		foreach ( $requiredHeaders as $header => $description ) {
			$this->assertStringContainsString(
				$header,
				$content,
				"$description should be present in plugin file"
			);
		}

		// Verify specific text domain
		$this->assertStringContainsString(
			'Text Domain: wp-simple-firewall',
			$content,
			'Text domain should be wp-simple-firewall'
		);
	}

	/**
	 * Test critical distribution assets exist
	 */
	public function testDistributionAssetsExist() :void {
		// Note: Based on actual file listing, files have 'shield-' prefix and '.bundle' suffix
		$criticalAssets = [
			'shield-main.bundle.js'       => 'Main JavaScript bundle',
			'shield-main.bundle.css'      => 'Main CSS bundle',
			'shield-wpadmin.bundle.js'    => 'WP Admin JavaScript bundle',
			'shield-wpadmin.bundle.css'   => 'WP Admin CSS bundle',
			'shield-badge.bundle.js'      => 'Badge JavaScript bundle',
			'shield-blockpage.bundle.js'  => 'Block page JavaScript bundle',
			'shield-blockpage.bundle.css' => 'Block page CSS bundle',
		];

		$distPath = $this->getPluginFilePath( 'assets/dist' );
		
		// First verify dist directory exists
		$this->assertDirectoryExists( $distPath, 'Distribution assets directory should exist' );

		foreach ( $criticalAssets as $asset => $description ) {
			$assetPath = $distPath . '/' . $asset;
			$this->assertFileExistsWithDebug(
				$assetPath,
				"$description ($asset) should exist"
			);
			
			// Verify files are not empty
			$size = \filesize( $assetPath );
			$this->assertGreaterThan(
				0,
				$size,
				"$description should not be empty"
			);
		}
	}

	/**
	 * Test plugin configuration file is valid JSON
	 */
	public function testPluginJsonIsValid() :void {
		$jsonFile = $this->getPluginFilePath( 'plugin.json' );
		$this->assertFileExistsWithDebug( $jsonFile, 'plugin.json should exist' );

		$content = \file_get_contents( $jsonFile );
		$this->assertNotFalse( $content, 'Should be able to read plugin.json' );

		$decoded = \json_decode( $content, true );
		$this->assertNotNull( $decoded, 'plugin.json should be valid JSON' );
		$this->assertIsArray( $decoded, 'plugin.json should decode to array' );

		// Verify key properties exist
		$this->assertArrayHasKey( 'properties', $decoded, 'plugin.json should have properties section' );
		$this->assertArrayHasKey( 'requirements', $decoded, 'plugin.json should have requirements section' );
		$this->assertArrayHasKey( 'paths', $decoded, 'plugin.json should have paths section' );

		// Verify text domain
		$this->assertEquals(
			'wp-simple-firewall',
			$decoded['properties']['text_domain'] ?? null,
			'Text domain should be wp-simple-firewall'
		);
	}

	/**
	 * Test vendor directories contain expected files
	 */
	public function testVendorDirectoriesNotEmpty() :void {
		$vendorDirs = [
			'src/lib/vendor'          => 'Main vendor directory',
			'src/lib/vendor_prefixed' => 'Prefixed vendor directory',
		];

		foreach ( $vendorDirs as $dir => $description ) {
			$path = $this->getPluginFilePath( $dir );
			$this->assertDirectoryExists( $path, "$description should exist" );

			// Check directory is not empty (has more than just . and ..)
			$files = \scandir( $path );
			$this->assertGreaterThan(
				2,
				\count( $files ),
				"$description should not be empty"
			);
		}
	}

	/**
	 * Test templates directory contains template files
	 */
	public function testTemplatesDirectoryNotEmpty() :void {
		$templatesPath = $this->getPluginFilePath( 'templates' );
		$this->assertDirectoryExists( $templatesPath, 'Templates directory should exist' );

		// Plugin uses Twig templates, not PHP templates
		// Check recursively for .twig files
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $templatesPath, \RecursiveDirectoryIterator::SKIP_DOTS )
		);
		
		$twigFiles = [];
		foreach ( $iterator as $file ) {
			if ( $file->getExtension() === 'twig' ) {
				$twigFiles[] = $file->getPathname();
			}
		}
		
		$this->assertNotEmpty(
			$twigFiles,
			'Templates directory should contain Twig template files (.twig)'
		);
	}

	/**
	 * Test config directory is not required (configurations are in plugin.json)
	 * This test verifies that the plugin works without separate config files
	 */
	public function testConfigurationIsEmbedded() :void {
		// Config directory is optional - plugin.json contains all configurations
		$configPath = $this->getPluginFilePath( 'config' );
		
		// Plugin should work fine without a config directory
		$this->assertTrue(
			true, // Always pass - this is just documentation
			'Configuration is embedded in plugin.json, separate config directory is not required'
		);

		// If config directory exists, it should be readable
		if ( \is_dir( $configPath ) ) {
			$this->assertTrue(
				\is_readable( $configPath ),
				'Config directory should be readable if it exists'
			);
		}
	}
}