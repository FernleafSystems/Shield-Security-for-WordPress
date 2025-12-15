<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Fixtures\TestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Unit tests for plugin configuration validation
 * 
 * These tests validate cross-file consistency and configuration integrity,
 * catching issues that schema validation alone cannot detect.
 */
class ConfigurationValidationTest extends TestCase {

	use PluginPathsTrait;

	private string $configPath;

	protected function setUpTestEnvironment() :void {
		parent::setUpTestEnvironment();
		$this->configPath = $this->getPluginFilePath( 'plugin.json' );
	}

	public function testPluginConfigurationFileExists() :void {
		$this->assertFileExists( $this->configPath, 'Plugin configuration file should exist' );
	}

	public function testPluginConfigurationIsValidJson() :void {
		$configData = $this->getPluginConfigData();
		$this->assertIsArray( $configData, 'Configuration should be valid JSON' );
	}

	public function testPluginConfigurationHasRequiredProperties() :void {
		$configData = $this->getPluginConfigData();

		// Test top-level structure
		$this->assertArrayHasKey( 'properties', $configData, 'Config should have properties section' );
		$this->assertArrayHasKey( 'requirements', $configData, 'Config should have requirements section' );

		$properties = $configData['properties'];

		// Test essential properties
		$requiredProps = [
			'version',
			'text_domain',
			'slug_plugin',
			'base_permissions'
		];

		foreach ( $requiredProps as $prop ) {
			$this->assertArrayHasKey( 
				$prop, 
				$properties, 
				"Configuration should have required property: {$prop}" 
			);
		}
	}

	/**
	 * Cross-validates version between plugin.json and main plugin file header
	 */
	public function testPluginVersionIsValid() :void {
		$configData = $this->getPluginConfigData();
		
		$version = $configData['properties']['version'] ?? '';
		
		$this->assertNotEmpty( $version, 'Version should not be empty' );
		$this->assertMatchesRegularExpression( 
			'/^\d+\.\d+\.\d+$/', 
			$version, 
			'Version should follow semantic versioning format (X.Y.Z)' 
		);
		
		// Version should match what's in the main plugin file
		$pluginContent = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		
		$this->assertStringContainsString( 
			"Version: {$version}", 
			$pluginContent, 
			'Version in config should match version in main plugin file' 
		);
	}

	/**
	 * Cross-validates text domain between plugin.json and main plugin file header
	 */
	public function testTextDomainIsValid() :void {
		$configData = $this->getPluginConfigData();
		
		$textDomain = $configData['properties']['text_domain'] ?? '';
		
		$this->assertEquals( 'wp-simple-firewall', $textDomain, 'Text domain should match expected value' );
		
		// Text domain should also exist in the main plugin file
		$pluginContent = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		
		$this->assertStringContainsString( 
			"Text Domain: {$textDomain}", 
			$pluginContent, 
			'Text domain in config should match text domain in main plugin file' 
		);
	}

	public function testPluginRequirementsAreValid() :void {
		$configData = $this->getPluginConfigData();
		
		$requirements = $configData['requirements'] ?? [];
		
		$this->assertIsArray( $requirements, 'Requirements should be an array' );
		
		// Check for essential requirement keys
		$essentialRequirements = [ 'php', 'wordpress', 'mysql' ];
		
		foreach ( $essentialRequirements as $req ) {
			$this->assertArrayHasKey( 
				$req, 
				$requirements, 
				"Requirements should specify minimum {$req} version" 
			);
		}
		
		// Validate PHP version format
		if ( isset( $requirements['php'] ) ) {
			$this->assertMatchesRegularExpression( 
				'/^\d+\.\d+$/', 
				$requirements['php'], 
				'PHP requirement should be in X.Y format' 
			);
		}
		
		// Validate WordPress version format  
		if ( isset( $requirements['wordpress'] ) ) {
			$this->assertMatchesRegularExpression( 
				'/^\d+\.\d+$/', 
				$requirements['wordpress'], 
				'WordPress requirement should be in X.Y format' 
			);
		}
	}

	public function testPluginModulesStructure() :void {
		$configData = $this->getPluginConfigData();
		
		// The config should have some structure - test what actually exists
		$this->assertIsArray( $configData, 'Config should be a valid array structure' );
		
		// Test for some expected sections
		$expectedSections = [ 'properties', 'requirements', 'paths' ];
		foreach ( $expectedSections as $section ) {
			$this->assertArrayHasKey( 
				$section, 
				$configData, 
				"Config should have {$section} section" 
			);
		}
	}

	/**
	 * Guards against accidental truncation or corruption of config file
	 */
	public function testConfigurationSizeIsReasonable() :void {
		$configSize = filesize( $this->configPath );
		
		// Config should be substantial (it's documented as 6,673 lines)
		// but not unreasonably large (let's say max 5MB)
		$this->assertGreaterThan( 100000, $configSize, 'Config should be substantial in size' );
		$this->assertLessThan( 5242880, $configSize, 'Config should not be unreasonably large (>5MB)' );
	}

	private function getPluginConfigData() :array {
		return $this->decodePluginJsonFile( 'plugin.json', 'Plugin configuration file' );
	}
}

