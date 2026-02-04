<?php declare( strict_types=1 );

// Load composer autoloader for test dependencies AND plugin classes
// Since the composer.json PSR-4 includes "src/" for Shield namespace,
// the main vendor autoloader handles everything.
// Note: plugin_autoload.php has an ABSPATH check that exits outside WordPress,
// so we don't use it for unit tests.
require_once dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';

$plugin_dir = dirname( dirname( __DIR__ ) );
echo "[UNIT TEST BOOTSTRAP] Using source directory: $plugin_dir\n";

// For package testing, we need the package's autoloader
$shield_package_path = getenv( 'SHIELD_PACKAGE_PATH' );
if ( $shield_package_path !== false && !empty( $shield_package_path ) ) {
	$plugin_dir = $shield_package_path;
	echo "[UNIT TEST BOOTSTRAP] Package testing mode: $plugin_dir\n";
	// Load the package's vendor autoloader (different from source autoloader)
	$package_autoload = $plugin_dir . '/vendor/autoload.php';
	if ( file_exists( $package_autoload ) ) {
		require_once $package_autoload;
	}
}

// Load Brain Monkey
require_once dirname( dirname( __DIR__ ) ) . '/vendor/brain/monkey/inc/patchwork-loader.php';

// Load our custom BrainMonkey bootstrap (replaces wp-test-utils)
require_once dirname( __DIR__ ) . '/bootstrap/brain-monkey.php';

// Load any test helpers
$helpers_dir = dirname( __DIR__ ) . '/Helpers';
if ( is_dir( $helpers_dir ) ) {
	foreach ( glob( $helpers_dir . '/*.php' ) as $helper ) {
		require_once $helper;
	}
}