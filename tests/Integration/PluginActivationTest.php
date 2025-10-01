<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Integration tests for plugin activation and basic WordPress integration
 */
class PluginActivationTest extends ShieldWordPressTestCase {
	use PluginPathsTrait;

	public function testPluginMainFileExists(): void {
		$pluginFile = $this->getPluginFilePath( 'icwp-wpsf.php' );
		$this->assertFileExists( $pluginFile, 'Main plugin file should exist' );
	}

	public function testPluginCanBeLoaded(): void {
		// Plugin should already be loaded by the bootstrap
		// Verify that main classes are available (indicates successful loading)
		$this->assertTrue( 
			class_exists( 'ICWP_WPSF_Shield_Security' ), 
			'Shield main class should be loaded by bootstrap'
		);
		
		$this->assertTrue( 
			class_exists( Controller::class ), 
			'Controller class should be loaded by bootstrap'
		);
	}

	public function testControllerClassExists(): void {
		$this->assertTrue(
			class_exists( Controller::class ),
			'Controller class should exist after plugin loading'
		);
	}

	public function testPluginInitFunctionExists(): void {
		$this->assertTrue(
			function_exists( 'icwp_wpsf_init' ),
			'Plugin init function should be defined'
		);
	}

	public function testPluginActivationHookExists(): void {
		$this->assertTrue(
			function_exists( 'icwp_wpsf_onactivate' ),
			'Plugin activation function should be defined'
		);
	}

	public function testAutoloadFileExists(): void {
		$autoloadFile = $this->getPluginFilePath( 'plugin_autoload.php' );
		$this->assertFileExists( $autoloadFile, 'Plugin autoload file should exist' );
	}

	public function testCompatibilityFileExists(): void {
		$compatFile = $this->getPluginFilePath( 'plugin_compatibility.php' );
		$this->assertFileExists( $compatFile, 'Plugin compatibility file should exist' );
	}

	public function testInitFileExists(): void {
		$initFile = $this->getPluginFilePath( 'plugin_init.php' );
		$this->assertFileExists( $initFile, 'Plugin init file should exist' );
	}

	/**
	 * Test basic WordPress hooks are properly registered
	 */
	public function testPluginHooksRegistration(): void {
		// This test verifies that the plugin registers its hooks correctly
		$this->assertTrue(
			has_action( 'plugins_loaded', 'icwp_wpsf_init' ) !== false,
			'Plugin should register plugins_loaded hook'
		);
	}


}
