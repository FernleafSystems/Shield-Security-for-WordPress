<?php declare( strict_types=1 );

// IMPORTANT: plugin.json is gitignored and must be generated before running tests.
// Run: composer build:config (or use composer test:unit which does it automatically).
// CI workflows that call phpunit directly MUST include this step.

// Unit tests always use the source autoloader.
// Package testing is handled by integration tests which run in a full WordPress environment.
$plugin_dir = dirname( dirname( __DIR__ ) );
echo "[UNIT TEST BOOTSTRAP] Using source directory: $plugin_dir\n";

require_once $plugin_dir . '/vendor/autoload.php';

// Load Brain Monkey
// WARNING: Patchwork + the echo above make @runInSeparateProcess INCOMPATIBLE with this
// test suite. PHPUnit subprocesses communicate via stdout serialization—the echo corrupts
// that stream, and Patchwork's state is non-serializable. If you need per-test isolation,
// solve it by removing static/cached state, NOT by adding @runInSeparateProcess.
require_once $plugin_dir . '/vendor/brain/monkey/inc/patchwork-loader.php';

// Load our custom BrainMonkey bootstrap (replaces wp-test-utils)
require_once dirname( __DIR__ ) . '/bootstrap/brain-monkey.php';

// Load any test helpers
$helpers_dir = dirname( __DIR__ ) . '/Helpers';
if ( is_dir( $helpers_dir ) ) {
	foreach ( glob( $helpers_dir . '/*.php' ) as $helper ) {
		require_once $helper;
	}
}
