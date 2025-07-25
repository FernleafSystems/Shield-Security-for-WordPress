<?php declare( strict_types=1 );

// Load composer autoloader for test dependencies
require_once dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';

// Support for testing packaged plugin
if ( getenv( 'SHIELD_PACKAGE_PATH' ) !== false ) {
	// Use the package path from environment
	$plugin_dir = getenv( 'SHIELD_PACKAGE_PATH' );
	echo "[UNIT TEST BOOTSTRAP] Using SHIELD_PACKAGE_PATH: $plugin_dir\n";
} else {
	// Local testing: use the current repository
	$plugin_dir = dirname( dirname( __DIR__ ) );
	echo "[UNIT TEST BOOTSTRAP] Using source directory: $plugin_dir\n";
}

// Debug: Show what we're trying to load
$autoload_path = $plugin_dir . '/plugin_autoload.php';
echo "[UNIT TEST BOOTSTRAP] Looking for autoloader at: $autoload_path\n";
echo "[UNIT TEST BOOTSTRAP] File exists: " . (file_exists($autoload_path) ? 'YES' : 'NO') . "\n";

if ( !file_exists($autoload_path) ) {
	echo "[UNIT TEST BOOTSTRAP] ERROR: Plugin autoloader not found!\n";
	echo "[UNIT TEST BOOTSTRAP] Plugin directory contents:\n";
	if ( is_dir($plugin_dir) ) {
		$files = scandir($plugin_dir);
		foreach ( $files as $file ) {
			if ( $file !== '.' && $file !== '..' ) {
				echo "[UNIT TEST BOOTSTRAP]   - $file\n";
			}
		}
	} else {
		echo "[UNIT TEST BOOTSTRAP] ERROR: Plugin directory does not exist: $plugin_dir\n";
	}
	die("Bootstrap failed: Cannot find plugin_autoload.php\n");
}

// Load Shield's plugin autoloader which loads main vendor dependencies
require_once $autoload_path;

// DO NOT load the prefixed vendor autoloader here!
// It contains duplicate libraries (Twig, Monolog) that cause fatal errors
// In production, these duplicates are removed during packaging
// For unit tests, we rely on the main vendor autoloader only

// Load Brain Monkey
require_once dirname( dirname( __DIR__ ) ) . '/vendor/brain/monkey/inc/patchwork-loader.php';

// Load the Yoast WP Test Utils Brain Monkey bootstrap
require_once dirname( dirname( __DIR__ ) ) . '/vendor/yoast/wp-test-utils/src/BrainMonkey/bootstrap.php';

// Load any test helpers
$helpers_dir = dirname( __DIR__ ) . '/Helpers';
if ( is_dir( $helpers_dir ) ) {
	foreach ( glob( $helpers_dir . '/*.php' ) as $helper ) {
		require_once $helper;
	}
}