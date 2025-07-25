<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

/**
 * Base test case for Shield WordPress integration tests
 * 
 * Extends WordPress core WP_UnitTestCase to provide WordPress integration testing capabilities
 * Provides Shield-specific setup, teardown, and helper methods
 */
abstract class ShieldWordPressTestCase extends \WP_UnitTestCase {

	/**
	 * Set up test environment
	 * Called before each test method
	 */
	public function set_up() {
		parent::set_up();
		
		echo "=== Shield WordPress TestCase Setup ===" . PHP_EOL;
		
		// WordPress is guaranteed to be loaded when extending WP_UnitTestCase - no need to check
		echo "✓ WordPress environment ready (guaranteed by WP_UnitTestCase)" . PHP_EOL;
		
		// Verify Shield plugin is loaded
		$this->assertTrue( class_exists( 'ICWP_WPSF_Shield_Security' ), 'Shield main class should be loaded' );
		echo "✓ Shield main class loaded" . PHP_EOL;
		
		// Verify Shield controller is available
		$this->assertTrue( 
			class_exists( 'FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller' ), 
			'Shield Controller class should be loaded' 
		);
		echo "✓ Shield Controller class loaded" . PHP_EOL;
		
		// Verify package path handling if testing package
		if ( getenv( 'SHIELD_PACKAGE_PATH' ) !== false ) {
			echo "✓ Package testing mode - SHIELD_PACKAGE_PATH: " . getenv( 'SHIELD_PACKAGE_PATH' ) . PHP_EOL;
			$this->assertDirectoryExists( getenv( 'SHIELD_PACKAGE_PATH' ), 'Package directory should exist' );
		} else {
			echo "✓ Source testing mode" . PHP_EOL;
		}
		
		echo "=== Shield WordPress TestCase Setup Complete ===" . PHP_EOL;
	}

	/**
	 * Tear down test environment
	 * Called after each test method
	 */
	public function tear_down() {
		echo "=== Shield WordPress TestCase Teardown ===" . PHP_EOL;
		
		// Clean up any Shield-specific test data
		$this->cleanUpShieldTestData();
		
		echo "✓ Shield test data cleaned up" . PHP_EOL;
		
		parent::tear_down();
		
		echo "=== Shield WordPress TestCase Teardown Complete ===" . PHP_EOL;
	}

	/**
	 * Clean up Shield-specific test data
	 */
	protected function cleanUpShieldTestData() {
		// Clean up any Shield options that may have been set during testing
		global $wpdb;
		
		if ( isset( $wpdb ) && $wpdb instanceof \wpdb ) {
			// Remove any Shield-specific options that start with our prefix
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'icwp_%'" );
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'shield_%'" );
		}
	}


	/**
	 * Assert that Shield plugin is properly loaded and functional
	 * Note: WordPress loading is guaranteed by extending WP_UnitTestCase
	 */
	protected function assertShieldIsLoaded() {
		// Check Shield main classes are available
		$this->assertTrue( class_exists( 'ICWP_WPSF_Shield_Security' ), 'Shield main class should be loaded' );
		$this->assertTrue( 
			class_exists( 'FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller' ), 
			'Shield Controller class should be loaded' 
		);
		
		echo "✓ Shield plugin verified as loaded" . PHP_EOL;
	}

	/**
	 * Get Shield plugin instance if available
	 */
	protected function getShieldPlugin() {
		if ( function_exists( 'shield_security_get_plugin' ) ) {
			return shield_security_get_plugin();
		}
		
		return null;
	}

	/**
	 * Output debug information about the test environment
	 */
	protected function debugTestEnvironment() {
		echo "=== Test Environment Debug Info ===" . PHP_EOL;
		echo "PHP Version: " . PHP_VERSION . PHP_EOL;
		echo "WordPress Version: " . ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : 'Unknown' ) . PHP_EOL;
		echo "Package Path: " . ( getenv( 'SHIELD_PACKAGE_PATH' ) ?: 'Not set (source testing)' ) . PHP_EOL;
		echo "Shield Plugin Dir: " . ( defined( 'ICWP_WPSF_FULL_PATH' ) ? ICWP_WPSF_FULL_PATH : 'Not defined' ) . PHP_EOL;
		echo "=== End Debug Info ===" . PHP_EOL;
	}
}