<?php declare( strict_types=1 );
/**
 * Custom BrainMonkey bootstrap for Shield Security tests
 * Replaces functionality previously provided by yoast/wp-test-utils
 */

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

// Define WordPress time constants if not already defined
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
}
if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );
}

// Define WordPress database constants if not already defined (for testing)
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', 'dummy_host' );
}
if ( ! defined( 'DB_NAME' ) ) {
	define( 'DB_NAME', 'dummy_db' );
}
if ( ! defined( 'DB_USER' ) ) {
	define( 'DB_USER', 'dummy_user' );
}
if ( ! defined( 'DB_PASSWORD' ) ) {
	define( 'DB_PASSWORD', 'dummy_password' );
}

// Set a minimal WordPress filesystem root if not already defined.
$shieldBrainMonkeyTempRoot = null;
if ( ! defined( 'ABSPATH' ) ) {
	$shieldBrainMonkeyTempRoot = rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' )
								 .'/shield-brain-monkey-wp-'.uniqid();
	define( 'ABSPATH', $shieldBrainMonkeyTempRoot.'/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', rtrim( str_replace( '\\', '/', ABSPATH ), '/' ).'/wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', rtrim( str_replace( '\\', '/', WP_CONTENT_DIR ), '/' ).'/plugins' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

foreach ( [
	ABSPATH,
	WP_CONTENT_DIR,
	WP_PLUGIN_DIR,
	rtrim( str_replace( '\\', '/', ABSPATH ), '/' ).'/'.WPINC,
	rtrim( str_replace( '\\', '/', ABSPATH ), '/' ).'/wp-admin',
] as $shieldBrainMonkeyDir ) {
	if ( is_string( $shieldBrainMonkeyDir ) && $shieldBrainMonkeyDir !== '' ) {
		@mkdir( $shieldBrainMonkeyDir, 0777, true );
	}
}

if ( $shieldBrainMonkeyTempRoot !== null ) {
	register_shutdown_function( static function () use ( $shieldBrainMonkeyTempRoot ) :void {
		$shieldBrainMonkeyTempRoot = rtrim( str_replace( '\\', '/', $shieldBrainMonkeyTempRoot ), '/' );
		$expectedPrefix = rtrim( str_replace( '\\', '/', sys_get_temp_dir() ), '/' ).'/shield-brain-monkey-wp-';
		if ( strpos( $shieldBrainMonkeyTempRoot, $expectedPrefix ) !== 0 || !is_dir( $shieldBrainMonkeyTempRoot ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $shieldBrainMonkeyTempRoot, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}
		@rmdir( $shieldBrainMonkeyTempRoot );
	} );
}

// Reset opcache if available (useful for testing)
if ( extension_loaded( 'Zend OPcache' ) && ini_get( 'opcache.enable_cli' ) === '1' ) {
	opcache_reset();
}

/**
 * Helper function to create test doubles for unavailable classes
 * This allows creating "fake" classes for testing purposes
 *
 * @param string $className The fully qualified class name to create
 * @return void
 */
function makeDoublesForUnavailableClasses( string $className ) {
	if ( ! empty( $className ) && ! class_exists( $className ) && ! interface_exists( $className ) ) {
		$parts = explode( '\\', $className );
		$shortName = array_pop( $parts );
		$namespace = implode( '\\', $parts );
		
		if ( ! empty( $namespace ) ) {
			eval( "namespace $namespace { class $shortName {} }" );
		} else {
			eval( "class $shortName {}" );
		}
	}
}
