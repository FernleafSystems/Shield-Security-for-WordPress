<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

class BasicIntegrationTest extends ShieldWordPressTestCase {

	public function test_wordpress_and_shield_integration() {
		// WordPress is guaranteed to be loaded when extending WP_UnitTestCase
		// Verify Shield is properly loaded and integrated
		$this->assertShieldIsLoaded();
		
		// Test WordPress and Shield work together
		$this->assertNotEmpty( get_bloginfo( 'version' ), 'WordPress version should be available' );
		
		// Test Shield specific integration
		$plugin = $this->getShieldPlugin();
		$this->assertNotNull( $plugin, 'Shield plugin instance should be available via helper function' );
	}

	public function test_shield_plugin_functionality() {
		// Use base class method to verify Shield is loaded
		$this->assertShieldIsLoaded();
		
		// Test Shield global instance is available
		$this->assertTrue( 
			isset( $GLOBALS['oICWP_Wpsf'] ) && $GLOBALS['oICWP_Wpsf'] instanceof \ICWP_WPSF_Shield_Security,
			'Shield global instance should be available'
		);
		
		// Test Shield public API function
		$this->assertTrue( 
			function_exists( 'shield_security_get_plugin' ),
			'Shield public API should be available'
		);
		
		// Test Shield plugin can be instantiated
		$plugin = $this->getShieldPlugin();
		$this->assertNotNull( $plugin, 'Shield plugin instance should be retrievable' );
	}
}