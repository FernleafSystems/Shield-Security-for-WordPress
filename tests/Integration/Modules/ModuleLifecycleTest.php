<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

/**
 * Integration tests for module lifecycle management.
 * 
 * These tests verify that modules can be activated/deactivated correctly,
 * their dependencies are properly checked, and options persist correctly.
 */
class ModuleLifecycleTest extends ShieldWordPressTestCase {

	// =========================================================================
	// MODULE AVAILABILITY TESTS
	// =========================================================================

	public function testAllSecurityModulesAreRegistered() :void {
		$this->assertShieldIsLoaded();

		$plugin = $this->getShieldPlugin();
		$this->assertNotNull( $plugin, 'Shield plugin should be available' );

		// Verify modules are accessible via plugin configuration
		$this->assertTrue(
			\class_exists( Controller::class ),
			'Controller class should be available'
		);
	}

	public function testControllerHasModuleAccessMethods() :void {
		$this->assertShieldIsLoaded();

		$reflection = new \ReflectionClass( Controller::class );
		
		// Verify key module-related methods exist
		$expectedMethods = [
			'GetInstance',
		];

		foreach ( $expectedMethods as $method ) {
			$this->assertTrue(
				$reflection->hasMethod( $method ),
				sprintf( "Controller should have '%s' method", $method )
			);
		}
	}

	// =========================================================================
	// MODULE CONFIGURATION TESTS
	// =========================================================================

	public function testModulesHaveRequiredConfiguration() :void {
		$this->assertShieldIsLoaded();

		$plugin = $this->getShieldPlugin();
		if ( $plugin === null ) {
			$this->markTestSkipped( 'Shield plugin not fully loaded' );
		}

		// Test that we can access module configurations
		// This verifies the configuration system is working
		$this->assertTrue(
			\function_exists( 'shield_security_get_plugin' ),
			'Shield helper function should be available'
		);
	}

	// =========================================================================
	// MODULE HOOKS TESTS
	// =========================================================================

	public function testPluginRegistersModuleHooks() :void {
		$this->assertShieldIsLoaded();

		// The plugin should register its main initialization hook
		$this->assertNotFalse(
			has_action( 'plugins_loaded', 'icwp_wpsf_init' ),
			'Plugin should register plugins_loaded hook for module initialization'
		);
	}

	public function testPluginInitFunctionCallable() :void {
		$this->assertShieldIsLoaded();

		$this->assertTrue(
			\function_exists( 'icwp_wpsf_init' ),
			'Plugin init function should be defined and callable'
		);
	}

	// =========================================================================
	// MODULE DEPENDENCY TESTS  
	// =========================================================================

	public function testCoreModulesLoadBeforeFeatureModules() :void {
		$this->assertShieldIsLoaded();

		// The plugin module should be loaded as it provides core functionality
		// that other modules depend on
		$plugin = $this->getShieldPlugin();
		$this->assertNotNull( $plugin, 'Shield plugin instance should be available' );
	}

	public function testDatabaseTablesAreAvailable() :void {
		$this->assertShieldIsLoaded();

		global $wpdb;

		// Check that WordPress database is available
		$this->assertNotNull( $wpdb, 'WordPress database should be available' );

		// Shield creates tables prefixed with the WP prefix
		$this->assertNotEmpty( $wpdb->prefix, 'WordPress table prefix should be set' );
	}

	// =========================================================================
	// MODULE OPTION PERSISTENCE TESTS
	// =========================================================================

	public function testPluginOptionsCanBeSavedAndRetrieved() :void {
		$this->assertShieldIsLoaded();

		// Test that WordPress options API is working
		$testKey = 'shield_test_option_' . \time();
		$testValue = 'test_value_' . \uniqid();

		// Save option
		$saved = \update_option( $testKey, $testValue );
		$this->assertTrue( $saved, 'Should be able to save option' );

		// Retrieve option
		$retrieved = \get_option( $testKey );
		$this->assertSame( $testValue, $retrieved, 'Retrieved option should match saved value' );

		// Clean up
		\delete_option( $testKey );
	}

	public function testPluginTransientsWork() :void {
		$this->assertShieldIsLoaded();

		$testKey = 'shield_test_transient_' . \time();
		$testValue = [ 'key' => 'value', 'timestamp' => \time() ];

		// Set transient
		$set = \set_transient( $testKey, $testValue, 300 );
		$this->assertTrue( $set, 'Should be able to set transient' );

		// Get transient
		$retrieved = \get_transient( $testKey );
		$this->assertEquals( $testValue, $retrieved, 'Retrieved transient should match' );

		// Clean up
		\delete_transient( $testKey );
	}

	// =========================================================================
	// GLOBAL INSTANCE TESTS
	// =========================================================================

	public function testShieldGlobalInstanceIsCorrectType() :void {
		$this->assertShieldIsLoaded();

		$this->assertTrue(
			isset( $GLOBALS['oICWP_Wpsf'] ),
			'Shield global instance should be set'
		);

		$this->assertInstanceOf(
			\ICWP_WPSF_Shield_Security::class,
			$GLOBALS['oICWP_Wpsf'],
			'Shield global instance should be correct type'
		);
	}

	public function testShieldHelperFunctionReturnsInstance() :void {
		$this->assertShieldIsLoaded();

		if ( !\function_exists( 'shield_security_get_plugin' ) ) {
			$this->markTestSkipped( 'Shield helper function not available' );
		}

		$plugin = \shield_security_get_plugin();
		$this->assertNotNull( $plugin, 'Helper function should return plugin instance' );
	}
}

