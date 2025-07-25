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
		// Use the correct plugin path for testing mode
		$pluginDir = $this->getPluginRoot();
		$pluginFile = $pluginDir . '/icwp-wpsf.php';
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
		$autoloadFile = dirname( dirname( __DIR__ ) ) . '/plugin_autoload.php';
		$this->assertFileExists( $autoloadFile, 'Plugin autoload file should exist' );
	}

	public function testCompatibilityFileExists(): void {
		$compatFile = dirname( dirname( __DIR__ ) ) . '/plugin_compatibility.php';
		$this->assertFileExists( $compatFile, 'Plugin compatibility file should exist' );
	}

	public function testInitFileExists(): void {
		$initFile = dirname( dirname( __DIR__ ) ) . '/plugin_init.php';
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

	/**
	 * Test plugin can create tables without errors
	 */
	public function testPluginDatabaseOperations(): void {
		global $wpdb;

		// Test that we can interact with the database
		$this->assertInstanceOf( 'wpdb', $wpdb, 'WordPress database should be available' );

		// Test basic query functionality
		$result = $wpdb->get_var( 'SELECT 1' );
		$this->assertEquals( '1', $result, 'Database should be functional' );
	}

	/**
	 * Test WordPress environment is properly set up
	 */
	public function testWordPressEnvironment(): void {
		$this->assertTrue( function_exists( 'wp_insert_post' ), 'WordPress functions should be available' );
		$this->assertTrue( function_exists( 'add_action' ), 'WordPress hook functions should be available' );
		$this->assertTrue( function_exists( 'get_option' ), 'WordPress option functions should be available' );
		$this->assertTrue( defined( 'ABSPATH' ), 'WordPress constants should be defined' );
		$this->assertTrue( defined( 'WP_CONTENT_DIR' ), 'WordPress content directory should be defined' );
	}

}
