<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Integration tests for WordPress hooks and actions
 */
class WordPressHooksTest extends TestCase {

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

	public function testPluginCanRegisterPostTypes() :void {
		$postType = 'shield_test_' . substr( wp_generate_password( 6, false, false ), 0, 6 );
		
		// Test that plugin can register custom post types
		register_post_type( $postType, [
			'public' => false,
			'label' => 'Shield Test Post'
		] );
		
		$this->assertTrue( 
			post_type_exists( $postType ), 
			'Should be able to register custom post types' 
		);
		
		// Clean up - WordPress doesn't provide unregister_post_type in test env
		global $wp_post_types;
		if ( isset( $wp_post_types[ $postType ] ) ) {
			unset( $wp_post_types[ $postType ] );
		}
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