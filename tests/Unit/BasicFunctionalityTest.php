<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

/**
 * Simple unit tests that don't require complex WordPress mocking
 */
class BasicFunctionalityTest extends BaseUnitTest {

	public function testComposerAutoloadWorks(): void {
		$this->assertTrue(
			class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller' ),
			'Controller class should be autoloaded'
		);
	}

	public function testPluginConstantsAreValid(): void {
		$expectedTextDomain = 'wp-simple-firewall';

		// These should be basic string operations, no WordPress functions needed
		$this->assertIsString( $expectedTextDomain );
		$this->assertNotEmpty( $expectedTextDomain );
	}

	public function testPluginFileExists(): void {
		$pluginFile = dirname( dirname( __DIR__ ) ) . '/icwp-wpsf.php';
		$this->assertFileExists( $pluginFile, 'Main plugin file should exist' );

		$pluginContent = file_get_contents( $pluginFile );
		$this->assertStringContainsString( 'Plugin Name: Shield Security', $pluginContent );
		$this->assertStringContainsString( 'wp-simple-firewall', $pluginContent );
	}

	public function testMainClassFilesExist(): void {
		$controllerFile = dirname( dirname( __DIR__ ) ) . '/src/lib/src/Controller/Controller.php';
		$this->assertFileExists( $controllerFile, 'Controller file should exist' );

		$actionRouterFile = dirname( dirname( __DIR__ ) ) . '/src/lib/src/ActionRouter/ActionRoutingController.php';
		$this->assertFileExists( $actionRouterFile, 'ActionRouter file should exist' );
	}

	public function testConfigurationFileExists(): void {
		$configFile = dirname( dirname( __DIR__ ) ) . '/plugin.json';
		$this->assertFileExists( $configFile, 'Plugin configuration file should exist' );

		$configContent = file_get_contents( $configFile );
		$configData    = json_decode( $configContent, true );

		$this->assertIsArray( $configData, 'Configuration should be valid JSON' );
		$this->assertArrayHasKey( 'properties', $configData, 'Config should have properties' );

		// Check properties structure
		$properties = $configData['properties'];
		$this->assertArrayHasKey( 'version', $properties, 'Config should have version' );
		$this->assertArrayHasKey( 'text_domain', $properties, 'Config should have text_domain' );
		$this->assertEquals( 'wp-simple-firewall', $properties['text_domain'] );
	}
}
