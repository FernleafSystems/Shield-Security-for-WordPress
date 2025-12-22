<?php declare( strict_types=1 );

echo "=== SHIELD INTEGRATION TEST BOOTSTRAP (WordPress Core Pattern) ===" . PHP_EOL;
$_package_path_env = getenv( 'SHIELD_PACKAGE_PATH' );
$_is_package_testing = $_package_path_env !== false && !empty( $_package_path_env );
echo "Environment: " . ( $_is_package_testing ? 'Package Testing' : 'Source Testing' ) . PHP_EOL;
echo "PHP Version: " . PHP_VERSION . PHP_EOL;
echo "Working Directory: " . getcwd() . PHP_EOL;

// Task 2.1.1: Load Composer Dependencies  
echo "Loading composer dependencies..." . PHP_EOL;

// Conditional autoloader loading to prevent conflicts
if ( $_is_package_testing ) {
	// Package testing mode: Plugin will handle its own autoloader
	// Only load test framework dependencies, avoid plugin dependencies
	$test_autoload = dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
	if ( file_exists( $test_autoload ) ) {
		require_once $test_autoload;
		echo "✓ Test framework dependencies loaded (package mode)" . PHP_EOL;
	}
	echo "📦 Plugin will load its own autoloader to prevent conflicts" . PHP_EOL;
} else {
	// Source testing mode: Load development environment
	$dev_autoload = dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
	if ( file_exists( $dev_autoload ) ) {
		require_once $dev_autoload;
		echo "✓ Development autoloader loaded (source mode)" . PHP_EOL;
	}
}

// Task 2.1.2: Detect and Setup Package vs Docker vs Source Testing  
// Preserve existing SHIELD_PACKAGE_PATH logic for package testing
$shield_package_path = getenv( 'SHIELD_PACKAGE_PATH' );
if ( $shield_package_path !== false && !empty( $shield_package_path ) ) {
	// Package testing mode - use the built package
	$plugin_dir = $shield_package_path;
	echo "📦 Package testing mode detected" . PHP_EOL;
	echo "Package path: " . $plugin_dir . PHP_EOL;
	
	// Verify package structure exists
	$main_plugin_file = $plugin_dir . '/icwp-wpsf.php';
	if ( ! file_exists( $main_plugin_file ) ) {
		echo "❌ ERROR: Main plugin file not found at: " . $main_plugin_file . PHP_EOL;
		exit( 1 );
	}
	echo "✓ Main plugin file verified: " . $main_plugin_file . PHP_EOL;
	
	// Set WP_PLUGIN_DIR to the parent of our package for WordPress plugin discovery
	$wp_plugin_dir = dirname( $plugin_dir );
	putenv( 'WP_PLUGIN_DIR=' . $wp_plugin_dir );
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
		define( 'WP_PLUGIN_DIR', $wp_plugin_dir );
	}
	echo "✓ WP_PLUGIN_DIR set to: " . $wp_plugin_dir . PHP_EOL;
} elseif ( is_dir( '/tmp/wordpress/wp-content/plugins/wp-simple-firewall' ) ) {
	// Docker testing mode - plugin is symlinked into WordPress plugins directory
	// The symlink is created by bin/run-tests-docker.sh to ensure plugins_url() works correctly
	$plugin_dir = '/tmp/wordpress/wp-content/plugins/wp-simple-firewall';
	echo "🐳 Docker testing mode detected (via symlink)" . PHP_EOL;
	echo "Plugin directory (symlinked): " . $plugin_dir . PHP_EOL;
	
	// Show the symlink target for debugging
	if ( is_link( $plugin_dir ) ) {
		echo "Symlink target: " . readlink( $plugin_dir ) . PHP_EOL;
	}
	
	// Verify plugin structure exists
	$main_plugin_file = $plugin_dir . '/icwp-wpsf.php';
	if ( ! file_exists( $main_plugin_file ) ) {
		echo "" . PHP_EOL;
		echo "❌ FATAL: Main plugin file not found at: " . $main_plugin_file . PHP_EOL;
		echo "" . PHP_EOL;
		echo "   The symlink exists but the plugin file is not accessible." . PHP_EOL;
		echo "   This indicates a broken symlink." . PHP_EOL;
		echo "" . PHP_EOL;
		echo "   Symlink target: " . ( is_link( $plugin_dir ) ? readlink( $plugin_dir ) : 'NOT A SYMLINK' ) . PHP_EOL;
		echo "   Check that bin/run-tests-docker.sh completed successfully." . PHP_EOL;
		echo "" . PHP_EOL;
		exit( 1 );
	}
	echo "✓ Main plugin file verified: " . $main_plugin_file . PHP_EOL;
} elseif ( getenv( 'SHIELD_TEST_MODE' ) === 'docker' || is_dir( '/tmp/wordpress' ) ) {
	// We're in Docker but the symlink wasn't created - this is an error
	echo "" . PHP_EOL;
	echo "❌ FATAL: Docker environment detected but plugin symlink is missing" . PHP_EOL;
	echo "" . PHP_EOL;
	echo "   Expected symlink at: /tmp/wordpress/wp-content/plugins/wp-simple-firewall" . PHP_EOL;
	echo "   WordPress directory exists: " . ( is_dir( '/tmp/wordpress' ) ? 'yes' : 'no' ) . PHP_EOL;
	echo "   Plugins directory exists: " . ( is_dir( '/tmp/wordpress/wp-content/plugins' ) ? 'yes' : 'no' ) . PHP_EOL;
	echo "" . PHP_EOL;
	echo "   This means bin/run-tests-docker.sh did not create the symlink." . PHP_EOL;
	echo "   Check the Docker test runner logs for errors." . PHP_EOL;
	echo "" . PHP_EOL;
	exit( 1 );
} else {
	// Source testing mode - use the current repository
	$plugin_dir = dirname( dirname( __DIR__ ) );
	echo "🔧 Source testing mode detected" . PHP_EOL;
	echo "Source directory: " . $plugin_dir . PHP_EOL;
	
	// Verify source structure exists
	$main_plugin_file = $plugin_dir . '/icwp-wpsf.php';
	if ( ! file_exists( $main_plugin_file ) ) {
		echo "❌ ERROR: Main plugin file not found at: " . $main_plugin_file . PHP_EOL;
		exit( 1 );
	}
	echo "✓ Main plugin file verified: " . $main_plugin_file . PHP_EOL;
}

// Task 2.1.3: Locate WordPress Test Directory (WordPress Core Pattern)
echo "Locating WordPress test directory..." . PHP_EOL;

// Standard WordPress test directory detection (following WordPress core pattern)
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = getenv( 'WP_DEVELOP_DIR' );
	if ( $_tests_dir ) {
		$_tests_dir .= '/tests/phpunit';
	}
}
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Add fallback paths for different environments
$fallback_paths = [
	'/tmp/wordpress-develop/tests/phpunit',
	dirname( dirname( dirname( __DIR__ ) ) ) . '/wordpress-tests-lib',
];

$found_tests_dir = false;
if ( $_tests_dir && is_dir( $_tests_dir ) ) {
	echo "✓ WordPress test directory found: " . $_tests_dir . PHP_EOL;
	$found_tests_dir = true;
} else {
	echo "⚠ Primary test directory not found: " . ( $_tests_dir ?: 'null' ) . PHP_EOL;
	
	// Try fallback paths
	foreach ( $fallback_paths as $path ) {
		if ( is_dir( $path ) ) {
			$_tests_dir = $path;
			echo "✓ WordPress test directory found (fallback): " . $_tests_dir . PHP_EOL;
			$found_tests_dir = true;
			break;
		} else {
			echo "⚠ Fallback path not found: " . $path . PHP_EOL;
		}
	}
}

if ( ! $found_tests_dir ) {
	echo "❌ ERROR: WordPress test directory not found!" . PHP_EOL;
	echo "   Checked paths:" . PHP_EOL;
	echo "   - " . ( getenv( 'WP_TESTS_DIR' ) ?: 'WP_TESTS_DIR not set' ) . PHP_EOL;
	echo "   - " . ( getenv( 'WP_DEVELOP_DIR' ) ? getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit' : 'WP_DEVELOP_DIR not set' ) . PHP_EOL;
	echo "   - /tmp/wordpress-tests-lib" . PHP_EOL;
	foreach ( $fallback_paths as $path ) {
		echo "   - " . $path . PHP_EOL;
	}
	echo "   Please ensure WordPress test environment is installed." . PHP_EOL;
	exit( 1 );
}

// Task 2.1.4: Load WordPress Test Functions FIRST (Critical Fix)
echo "Loading WordPress test functions..." . PHP_EOL;
$wp_tests_functions_file = $_tests_dir . '/includes/functions.php';

if ( ! file_exists( $wp_tests_functions_file ) ) {
	echo "❌ ERROR: WordPress test functions file not found at: " . $wp_tests_functions_file . PHP_EOL;
	exit( 1 );
}

require_once $wp_tests_functions_file;
echo "✓ WordPress test functions loaded from: " . $wp_tests_functions_file . PHP_EOL;

// Verify tests_add_filter function is now available
if ( ! function_exists( 'tests_add_filter' ) ) {
	echo "❌ ERROR: tests_add_filter() function not available after loading functions.php" . PHP_EOL;
	exit( 1 );
}
echo "✓ tests_add_filter() function is available" . PHP_EOL;

// Task 2.1.5: Register Plugin Loading Hook
echo "Registering Shield plugin loading hook..." . PHP_EOL;

// Function to manually load Shield plugin (following WordPress core pattern)
function _manually_load_shield_plugin() {
	// Get plugin directory from environment or global
	$plugin_dir = getenv( 'SHIELD_PACKAGE_PATH' );
	if ( ! $plugin_dir ) {
		// Fallback to source directory
		$plugin_dir = dirname( dirname( __DIR__ ) );
	}
	
	echo "🔌 Loading Shield plugin from: " . $plugin_dir . PHP_EOL;
	
	// Verify plugin structure before loading
	$main_plugin_file = $plugin_dir . '/icwp-wpsf.php';
	$plugin_autoload = $plugin_dir . '/plugin_autoload.php';
	
	if ( ! file_exists( $main_plugin_file ) ) {
		echo "❌ ERROR: Main plugin file not found: " . $main_plugin_file . PHP_EOL;
		return;
	}
	
	if ( ! file_exists( $plugin_autoload ) ) {
		echo "❌ ERROR: Plugin autoload file not found: " . $plugin_autoload . PHP_EOL;
		return;
	}
	
	// Load plugin (which will handle its own autoloader and initialization)
	try {
		require_once $main_plugin_file;
		echo "✓ Shield plugin file loaded successfully!" . PHP_EOL;
		
		// Note: Shield classes/constants are created during plugins_loaded action
		// Since we're also on plugins_loaded (priority 0), Shield init (priority 1) hasn't run yet
		// This is expected - classes will be available when tests actually run
		echo "ℹ Shield initialization will complete when plugins_loaded priority 1 runs" . PHP_EOL;
		
	} catch ( Error $e ) {
		echo "❌ ERROR loading Shield plugin: " . $e->getMessage() . PHP_EOL;
		echo "   File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
	}
}

// Register plugin loading on plugins_loaded hook (same as Shield's initialization)
// This ensures plugin loads AND initializes before tests run
tests_add_filter( 'plugins_loaded', '_manually_load_shield_plugin', 0 );
echo "✓ Shield plugin loading registered on plugins_loaded hook (priority 0 - before Shield init)" . PHP_EOL;

// Task 2.1.6: Add WordPress Environment Configuration
echo "Configuring WordPress test environment..." . PHP_EOL;

// Plugin URL handling - No filter needed
// 
// plugins_url() now works correctly because:
// 1. bin/run-tests-docker.sh creates a symlink from WordPress's plugins directory to our plugin
// 2. WordPress sees the plugin at /tmp/wordpress/wp-content/plugins/wp-simple-firewall
// 3. plugins_url() correctly calculates URLs relative to WP_PLUGIN_DIR
// 4. This is the standard WordPress pattern used by core and major plugins

// Set Shield-specific constants for test mode
tests_add_filter( 'shield_security_development_mode', '__return_false' );
echo "✓ Shield development mode disabled for testing" . PHP_EOL;

// Set up WordPress test database table prefix
$table_prefix = 'wptests_';

echo "✓ WordPress test environment configured" . PHP_EOL;

// Task 2.1.7: Bootstrap WordPress Environment LAST
echo "Bootstrapping WordPress environment..." . PHP_EOL;

$wp_tests_bootstrap_file = $_tests_dir . '/includes/bootstrap.php';
if ( ! file_exists( $wp_tests_bootstrap_file ) ) {
	echo "❌ ERROR: WordPress test bootstrap file not found at: " . $wp_tests_bootstrap_file . PHP_EOL;
	exit( 1 );
}

// This loads WordPress and processes all registered hooks including our plugin loading
require_once $wp_tests_bootstrap_file;

echo "✓ WordPress environment bootstrapped successfully!" . PHP_EOL;
echo "✓ WordPress version: " . ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : 'Unknown' ) . PHP_EOL;

// Verify WordPress is loaded and Shield plugin is available
if ( ! function_exists( 'wp_insert_post' ) ) {
	echo "❌ ERROR: WordPress functions not available after bootstrap" . PHP_EOL;
	exit( 1 );
}
echo "✓ WordPress functions are available" . PHP_EOL;

// Verify Shield plugin loaded and initialized correctly
// Check for evidence-based indicators that Shield actually creates
$shield_loaded = false;
$status_details = [];

// Check 1: Global variable (most reliable - created during successful initialization)
if ( isset( $GLOBALS['oICWP_Wpsf'] ) && $GLOBALS['oICWP_Wpsf'] instanceof ICWP_WPSF_Shield_Security ) {
	$shield_loaded = true;
	$status_details[] = "✓ Global \$oICWP_Wpsf instance: Available";
} else {
	$status_details[] = "✗ Global \$oICWP_Wpsf instance: " . ( isset( $GLOBALS['oICWP_Wpsf'] ) ? 'Wrong type' : 'Missing' );
}

// Check 2: Public API function (created when plugin loads)
if ( function_exists( 'shield_security_get_plugin' ) ) {
	$shield_loaded = true;
	$status_details[] = "✓ shield_security_get_plugin() function: Available";
} else {
	$status_details[] = "✗ shield_security_get_plugin() function: Missing";
}

// Check 3: Core classes (should exist after initialization)
if ( class_exists( 'ICWP_WPSF_Shield_Security' ) && class_exists( 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Controller\\Controller' ) ) {
	$shield_loaded = true;
	$status_details[] = "✓ Core Shield classes: Available";
} else {
	$status_details[] = "✗ Core Shield classes: Missing";
}

if ( $shield_loaded ) {
	echo "✓ Shield plugin loaded and initialized successfully" . PHP_EOL;
} else {
	echo "⚠ WARNING: Shield plugin may not have initialized correctly" . PHP_EOL;
}

foreach ( $status_details as $detail ) {
	echo "   " . $detail . PHP_EOL;
}

// Load test helpers if they exist
$helpers_dir = dirname( __DIR__ ) . '/Helpers';
if ( is_dir( $helpers_dir ) ) {
	echo "Loading test helpers from: " . $helpers_dir . PHP_EOL;
	foreach ( glob( $helpers_dir . '/*.php' ) as $helper ) {
		require_once $helper;
		echo "✓ Loaded helper: " . basename( $helper ) . PHP_EOL;
	}
}

echo "=== SHIELD INTEGRATION TEST BOOTSTRAP COMPLETE ===" . PHP_EOL;