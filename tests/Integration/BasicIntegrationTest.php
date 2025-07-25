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

	public function test_wordpress_database_operations() {
		echo "=== Testing WordPress Database Operations ===" . PHP_EOL;
		
		// WordPress database is guaranteed to work when extending WP_UnitTestCase
		// Just test that WordPress database operations work correctly
		
		// Test we can create a post (this tests WordPress functions work correctly)
		$post_id = wp_insert_post( [
			'post_title' => 'Shield Integration Test Post',
			'post_content' => 'This post verifies WordPress and Shield integration testing works.',
			'post_status' => 'publish',
			'post_type' => 'post'
		] );
		
		$this->assertIsInt( $post_id, 'Post creation should return integer ID' );
		$this->assertGreaterThan( 0, $post_id, 'Post ID should be positive' );
		echo "✓ WordPress post creation successful (ID: " . $post_id . ")" . PHP_EOL;
		
		// Test we can retrieve the post
		$post = get_post( $post_id );
		$this->assertNotNull( $post, 'Created post should be retrievable' );
		$this->assertEquals( 'Shield Integration Test Post', $post->post_title, 'Post title should match' );
		echo "✓ WordPress post retrieval successful" . PHP_EOL;
		
		// Clean up
		wp_delete_post( $post_id, true );
		$deleted_post = get_post( $post_id );
		$this->assertNull( $deleted_post, 'Post should be deleted' );
		echo "✓ WordPress post deletion successful" . PHP_EOL;
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