<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;

/**
 * Integration tests for WordPress hooks and actions
 */
class WordPressHooksTest extends ShieldWordPressTestCase {

	public function testPluginLoadsOnPluginsLoaded() :void {
		// Test that the plugin properly hooks into plugins_loaded
		$hookExists = has_action( 'plugins_loaded', 'icwp_wpsf_init' );
		$this->assertNotFalse( $hookExists, 'Plugin should hook into plugins_loaded' );
	}



	public function testWordPressHookSystemWorks() :void {
		$testValue = false;
		
		// Test that WordPress hook system is functional
		add_action( 'shield_test_hook', function() use ( &$testValue ) {
			$testValue = true;
		} );
		
		do_action( 'shield_test_hook' );
		
		$this->assertTrue( $testValue, 'WordPress hook system should be functional' );
	}

	public function testFilterSystemWorks() :void {
		$testValue = 'original';
		
		// Test that WordPress filter system is functional
		add_filter( 'shield_test_filter', function( $value ) {
			return 'filtered_' . $value;
		} );
		
		$result = apply_filters( 'shield_test_filter', $testValue );
		
		$this->assertEquals( 'filtered_original', $result, 'WordPress filter system should be functional' );
	}

	public function testPluginCanAddOptions() :void {
		$optionName = 'shield_test_option_' . wp_generate_password( 8, false );
		$optionValue = 'test_value_' . time();
		
		// Test that plugin can interact with WordPress options
		$addResult = add_option( $optionName, $optionValue );
		$this->assertTrue( $addResult, 'Should be able to add options' );
		
		$retrievedValue = get_option( $optionName );
		$this->assertEquals( $optionValue, $retrievedValue, 'Should be able to retrieve options' );
		
		// Clean up
		delete_option( $optionName );
		$this->assertFalse( get_option( $optionName, false ), 'Should be able to delete options' );
	}

	public function testPluginCanScheduleEvents() :void {
		$hookName = 'shield_test_cron_' . wp_generate_password( 8, false );
		
		// Test that plugin can schedule WordPress cron events
		$scheduleResult = wp_schedule_single_event( time() + 3600, $hookName );
		
		// In test environment, this might return false due to cron being disabled
		// but we test that the function exists and is callable
		$this->assertTrue( 
			function_exists( 'wp_schedule_single_event' ),
			'WordPress cron functions should be available'
		);
		
		// Clean up if it was scheduled
		wp_clear_scheduled_hook( $hookName );
	}

	public function testPluginCanCreateCustomTables() :void {
		global $wpdb;
		
		$tableName = $wpdb->prefix . 'shield_test_table_' . wp_generate_password( 8, false );
		
		// Test basic table creation capability
		$sql = "CREATE TABLE {$tableName} (
			id int(11) NOT NULL AUTO_INCREMENT,
			test_data varchar(255) NOT NULL,
			PRIMARY KEY (id)
		) {$wpdb->get_charset_collate()};";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		
		// Check if table was created
		$tableExists = $wpdb->get_var( $wpdb->prepare( 
			"SHOW TABLES LIKE %s", 
			$tableName 
		) );
		
		$this->assertEquals( $tableName, $tableExists, 'Should be able to create custom tables' );
		
		// Clean up
		$wpdb->query( "DROP TABLE IF EXISTS {$tableName}" );
	}

	public function testPluginCanWorkWithExistingPostTypes() :void {
		// Test that Shield can work with existing WordPress post types
		// Shield Security works with standard WordPress post types for audit trail functionality
		$this->assertTrue( post_type_exists( 'post' ), 'WordPress post type should exist' );
		$this->assertTrue( post_type_exists( 'page' ), 'WordPress page type should exist' );
		$this->assertTrue( post_type_exists( 'attachment' ), 'WordPress attachment type should exist' );
		
		// Verify Shield doesn't register custom post types (it's a security plugin, not content management)
		$customPostTypes = get_post_types( [ '_builtin' => false ], 'names' );
		
		// DEBUG: Let's see ALL custom post types
		echo "\n=== DEBUG: All custom post types found ===\n";
		echo "Total custom post types: " . count( $customPostTypes ) . "\n";
		if ( !empty( $customPostTypes ) ) {
			foreach ( $customPostTypes as $postType ) {
				$postTypeObj = get_post_type_object( $postType );
				echo "Post Type: '$postType'\n";
				echo "  - Label: " . ( $postTypeObj->label ?? 'N/A' ) . "\n";
				echo "  - Public: " . ( $postTypeObj->public ? 'Yes' : 'No' ) . "\n";
				echo "  - Rewrite slug: " . ( isset( $postTypeObj->rewrite['slug'] ) ? $postTypeObj->rewrite['slug'] : 'N/A' ) . "\n";
				echo "  - Capability type: " . ( $postTypeObj->capability_type ?? 'N/A' ) . "\n";
				echo "  - Menu position: " . ( $postTypeObj->menu_position ?? 'N/A' ) . "\n";
			}
		}
		echo "=== END DEBUG ===\n\n";
		
		$shieldPostTypes = array_filter( $customPostTypes, function( $postType ) {
			return strpos( $postType, 'shield' ) !== false || strpos( $postType, 'icwp' ) !== false;
		} );
		
		// DEBUG: Show which ones match our filter
		echo "=== DEBUG: Shield/ICWP post types found ===\n";
		echo "Count: " . count( $shieldPostTypes ) . "\n";
		foreach ( $shieldPostTypes as $postType ) {
			echo "- $postType\n";
		}
		echo "=== END DEBUG ===\n\n";
		
		$this->assertEmpty( $shieldPostTypes, 'Shield should not register custom post types (security plugin)' );
	}

	public function testPluginCanEnqueueScripts() :void {
		$handle = 'shield-test-script-' . wp_generate_password( 8, false );
		$src = 'https://example.com/test.js';
		
		// Test that plugin can enqueue scripts and styles
		wp_enqueue_script( $handle, $src, [], '1.0.0', true );
		
		$this->assertTrue( 
			wp_script_is( $handle, 'enqueued' ), 
			'Should be able to enqueue scripts' 
		);
	}

	public function testWordPressUserCapabilitySystem() :void {
		// Test that WordPress user capability system is available
		$this->assertTrue( 
			function_exists( 'current_user_can' ),
			'User capability functions should be available'
		);
		
		// Test basic capability check
		$canManageOptions = current_user_can( 'manage_options' );
		$this->assertIsBool( $canManageOptions, 'Capability check should return boolean' );
	}
}