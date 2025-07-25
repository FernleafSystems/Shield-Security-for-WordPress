<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Fixtures\TestCase;

/**
 * Unit tests for plugin configuration validation
 */
class ConfigurationValidationTest extends TestCase {

	private string $configPath;

	protected function setUpTestEnvironment() :void {
		parent::setUpTestEnvironment();
		$this->configPath = dirname( dirname( __DIR__ ) ) . '/plugin.json';
	}

	public function testPluginConfigurationFileExists() :void {
		$this->assertFileExists( $this->configPath, 'Plugin configuration file should exist' );
	}

	public function testPluginConfigurationIsValidJson() :void {
		$configContent = file_get_contents( $this->configPath );
		$this->assertNotFalse( $configContent, 'Should be able to read config file' );

		$configData = json_decode( $configContent, true );
		$this->assertIsArray( $configData, 'Configuration should be valid JSON' );
		$this->assertEquals( JSON_ERROR_NONE, json_last_error(), 'JSON should parse without errors' );
	}

	public function testPluginConfigurationHasRequiredProperties() :void {
		$configContent = file_get_contents( $this->configPath );
		$configData = json_decode( $configContent, true );

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

	public function testPluginVersionIsValid() :void {
		$configContent = file_get_contents( $this->configPath );
		$configData = json_decode( $configContent, true );
		
		$version = $configData['properties']['version'] ?? '';
		
		$this->assertNotEmpty( $version, 'Version should not be empty' );
		$this->assertMatchesRegularExpression( 
			'/^\d+\.\d+\.\d+$/', 
			$version, 
			'Version should follow semantic versioning format (X.Y.Z)' 
		);
		
		// Version should match what's in the main plugin file
		$pluginFile = dirname( dirname( __DIR__ ) ) . '/icwp-wpsf.php';
		$pluginContent = file_get_contents( $pluginFile );
		
		$this->assertStringContainsString( 
			"Version: {$version}", 
			$pluginContent, 
			'Version in config should match version in main plugin file' 
		);
	}

	public function testTextDomainIsValid() :void {
		$configContent = file_get_contents( $this->configPath );
		$configData = json_decode( $configContent, true );
		
		$textDomain = $configData['properties']['text_domain'] ?? '';
		
		$this->assertEquals( 'wp-simple-firewall', $textDomain, 'Text domain should match expected value' );
		
		// Text domain should also exist in the main plugin file
		$pluginFile = dirname( dirname( __DIR__ ) ) . '/icwp-wpsf.php';
		$pluginContent = file_get_contents( $pluginFile );
		
		$this->assertStringContainsString( 
			"Text Domain: {$textDomain}", 
			$pluginContent, 
			'Text domain in config should match text domain in main plugin file' 
		);
	}

	public function testPluginRequirementsAreValid() :void {
		$configContent = file_get_contents( $this->configPath );
		$configData = json_decode( $configContent, true );
		
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
		$configContent = file_get_contents( $this->configPath );
		$configData = json_decode( $configContent, true );
		
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

	public function testConfigurationSizeIsReasonable() :void {
		$configSize = filesize( $this->configPath );
		
		// Config should be substantial (it's documented as 6,673 lines)
		// but not unreasonably large (let's say max 5MB)
		$this->assertGreaterThan( 100000, $configSize, 'Config should be substantial in size' );
		$this->assertLessThan( 5242880, $configSize, 'Config should not be unreasonably large (>5MB)' );
	}
}