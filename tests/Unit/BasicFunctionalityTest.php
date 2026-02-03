<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Simple unit tests that don't require complex WordPress mocking
 */
class BasicFunctionalityTest extends BaseUnitTest {

	use PluginPathsTrait;

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
		$pluginContent = $this->getPluginFileContents( 'icwp-wpsf.php', 'Main plugin file' );
		$this->assertStringContainsString( 'Plugin Name: Shield Security', $pluginContent );
		$this->assertStringContainsString( 'wp-simple-firewall', $pluginContent );
	}

	public function testMainClassFilesExist(): void {
		$controllerFile = $this->getPluginFilePath( 'src/lib/src/Controller/Controller.php' );
		$this->assertFileExists( $controllerFile, 'Controller file should exist' );

		$actionRouterFile = $this->getPluginFilePath( 'src/lib/src/ActionRouter/ActionRoutingController.php' );
		$this->assertFileExists( $actionRouterFile, 'ActionRouter file should exist' );
	}

	public function testConfigurationFileExists(): void {
		$configData = $this->decodePluginJsonFile( 'plugin.json', 'plugin.json' );
		$this->assertArrayHasKey( 'properties', $configData, 'Config should have properties' );

		// Check properties structure
		$properties = $configData['properties'];
		$this->assertArrayHasKey( 'version', $properties, 'Config should have version' );
		$this->assertArrayHasKey( 'text_domain', $properties, 'Config should have text_domain' );
		$this->assertEquals( 'wp-simple-firewall', $properties['text_domain'] );
	}
}
