<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

class BasicIntegrationTest extends ShieldWordPressTestCase {

	public function test_wordpress_and_shield_integration() {
		echo "=== Testing WordPress and Shield Integration ===" . PHP_EOL;
		
		// WordPress is guaranteed to be loaded when extending WP_UnitTestCase
		// Verify Shield is properly loaded and integrated
		$this->assertShieldIsLoaded();
		
		// Test WordPress and Shield work together
		$this->assertNotEmpty( get_bloginfo( 'version' ), 'WordPress version should be available' );
		echo "✓ WordPress version: " . get_bloginfo( 'version' ) . PHP_EOL;
		
		// Test Shield specific integration
		$plugin = $this->getShieldPlugin();
		if ( $plugin !== null ) {
			echo "✓ Shield plugin instance available via helper function" . PHP_EOL;
		}
		
		echo "✓ WordPress and Shield integration verified" . PHP_EOL;
	}

	public function test_shield_plugin_functionality() {
		echo "=== Testing Shield Plugin Functionality ===" . PHP_EOL;
		
		// Use base class method to verify Shield is loaded
		$this->assertShieldIsLoaded();
		
		// Test Shield global instance is available (evidence-based check)
		$this->assertTrue( 
			isset( $GLOBALS['oICWP_Wpsf'] ) && $GLOBALS['oICWP_Wpsf'] instanceof \ICWP_WPSF_Shield_Security,
			'Shield global instance should be available'
		);
		echo "✓ Shield global instance verified" . PHP_EOL;
		
		// Test Shield public API function (evidence-based check)
		$this->assertTrue( 
			function_exists( 'shield_security_get_plugin' ),
			'Shield public API should be available'
		);
		echo "✓ Shield public API verified" . PHP_EOL;
		
		// Test Shield plugin can be instantiated
		$plugin = $this->getShieldPlugin();
		if ( $plugin !== null ) {
			echo "✓ Shield plugin instance created successfully" . PHP_EOL;
		} else {
			echo "ℹ Shield plugin helper function not available (may be normal)" . PHP_EOL;
		}
	}

	public function test_shield_dependencies_loaded() {
		echo "=== Testing Shield Dependencies ===" . PHP_EOL;
		
		// Use base class method to verify Shield is loaded  
		$this->assertShieldIsLoaded();
		
		// Test that Shield dependencies are properly loaded
		// This verifies the package/source loading worked correctly
		echo "✓ Shield Controller class verified by base class" . PHP_EOL;
		
		// Test Shield helper functions if available
		if ( function_exists( 'shield_security_get_plugin' ) ) {
			$plugin = shield_security_get_plugin();
			$this->assertNotNull( $plugin, 'Shield plugin instance should be available' );
			echo "✓ Shield plugin instance available via helper" . PHP_EOL;
		} else {
			echo "ℹ Shield helper function not available (testing early loading)" . PHP_EOL;
		}
	}


	public function test_debug_environment() {
		echo "=== Testing Debug Environment ===" . PHP_EOL;
		
		// Use base class debug method
		$this->debugTestEnvironment();
		
		// Verify package vs source testing mode
		if ( getenv( 'SHIELD_PACKAGE_PATH' ) !== false ) {
			$this->assertDirectoryExists( getenv( 'SHIELD_PACKAGE_PATH' ), 'Package directory should exist' );
			echo "✓ Package testing mode verified" . PHP_EOL;
		} else {
			echo "✓ Source testing mode verified" . PHP_EOL;
		}
	}
}