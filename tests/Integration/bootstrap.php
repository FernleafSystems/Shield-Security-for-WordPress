<?php declare( strict_types=1 );

if ( !\function_exists( 'shield_test_env_truthy' ) ) {
	function shield_test_env_truthy( string $name ) :bool {
		$value = \getenv( $name );
		if ( !\is_string( $value ) || $value === '' ) {
			return false;
		}
		return \in_array( \strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true );
	}
}

if ( !\function_exists( 'shield_test_verbose' ) ) {
	function shield_test_verbose() :bool {
		return shield_test_env_truthy( 'SHIELD_TEST_VERBOSE' )
			|| shield_test_env_truthy( 'SHIELD_DEBUG' )
			|| shield_test_env_truthy( 'SHIELD_DEBUG_PATHS' );
	}
}

if ( !\function_exists( 'shield_test_log' ) ) {
	function shield_test_log( string $message ) :void {
		if ( shield_test_verbose() ) {
			echo $message.\PHP_EOL;
		}
	}
}

if ( !\function_exists( 'shield_test_error' ) ) {
	function shield_test_error( string $message ) :void {
		\fwrite( \STDERR, $message.\PHP_EOL );
	}
}

if ( !\function_exists( 'shield_test_should_fail_missing_wp_env' ) ) {
	function shield_test_should_fail_missing_wp_env() :bool {
		return shield_test_env_truthy( 'CI' )
			|| shield_test_env_truthy( 'GITHUB_ACTIONS' )
			|| \getenv( 'SHIELD_TEST_MODE' ) === 'docker'
			|| \is_dir( '/tmp/wordpress' );
	}
}

$_package_path_env = \getenv( 'SHIELD_PACKAGE_PATH' );
$_is_package_testing = $_package_path_env !== false && !empty( $_package_path_env );

shield_test_log( '=== SHIELD INTEGRATION TEST BOOTSTRAP ===' );
shield_test_log( 'Environment: '.( $_is_package_testing ? 'Package Testing' : 'Source Testing' ) );
shield_test_log( 'PHP Version: '.\PHP_VERSION );
shield_test_log( 'Working Directory: '.\getcwd() );

$rootAutoload = \dirname( \dirname( __DIR__ ) ).'/vendor/autoload.php';
if ( \file_exists( $rootAutoload ) ) {
	require_once $rootAutoload;
	shield_test_log( 'Composer dependencies loaded.' );
}

$shield_package_path = \getenv( 'SHIELD_PACKAGE_PATH' );
if ( $shield_package_path !== false && !empty( $shield_package_path ) ) {
	$plugin_dir = $shield_package_path;
	$main_plugin_file = $plugin_dir.'/icwp-wpsf.php';
	if ( !\file_exists( $main_plugin_file ) ) {
		shield_test_error( 'ERROR: Main plugin file not found at: '.$main_plugin_file );
		exit( 1 );
	}

	$wp_plugin_dir = \dirname( $plugin_dir );
	\putenv( 'WP_PLUGIN_DIR='.$wp_plugin_dir );
	if ( !\defined( 'WP_PLUGIN_DIR' ) ) {
		\define( 'WP_PLUGIN_DIR', $wp_plugin_dir );
	}

	shield_test_log( 'Package mode using plugin path: '.$plugin_dir );
}
elseif ( \is_dir( '/tmp/wordpress/wp-content/plugins/wp-simple-firewall' ) ) {
	$plugin_dir = '/tmp/wordpress/wp-content/plugins/wp-simple-firewall';
	$main_plugin_file = $plugin_dir.'/icwp-wpsf.php';
	if ( !\file_exists( $main_plugin_file ) ) {
		shield_test_error( 'ERROR: Docker symlinked plugin file missing: '.$main_plugin_file );
		if ( shield_test_verbose() ) {
			$target = \is_link( $plugin_dir ) ? (string)\readlink( $plugin_dir ) : 'NOT A SYMLINK';
			shield_test_error( 'Symlink target: '.$target );
		}
		exit( 1 );
	}
	shield_test_log( 'Docker mode using symlinked plugin path: '.$plugin_dir );
}
elseif ( \getenv( 'SHIELD_TEST_MODE' ) === 'docker' || \is_dir( '/tmp/wordpress' ) ) {
	shield_test_error( 'ERROR: Docker environment detected but plugin symlink is missing.' );
	exit( 1 );
}
else {
	$plugin_dir = \dirname( \dirname( __DIR__ ) );
	$main_plugin_file = $plugin_dir.'/icwp-wpsf.php';
	if ( !\file_exists( $main_plugin_file ) ) {
		shield_test_error( 'ERROR: Main plugin file not found at: '.$main_plugin_file );
		exit( 1 );
	}
	shield_test_log( 'Source mode using plugin path: '.$plugin_dir );
}

$_tests_dir = \getenv( 'WP_TESTS_DIR' );
if ( !$_tests_dir ) {
	$_tests_dir = \getenv( 'WP_DEVELOP_DIR' );
	if ( $_tests_dir ) {
		$_tests_dir .= '/tests/phpunit';
	}
}
if ( !$_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

$fallback_paths = [
	'/tmp/wordpress-develop/tests/phpunit',
	\dirname( \dirname( \dirname( __DIR__ ) ) ).'/wordpress-tests-lib',
];

$checked_paths = [
	\getenv( 'WP_TESTS_DIR' ) ?: 'WP_TESTS_DIR not set',
	\getenv( 'WP_DEVELOP_DIR' ) ? \getenv( 'WP_DEVELOP_DIR' ).'/tests/phpunit' : 'WP_DEVELOP_DIR not set',
	'/tmp/wordpress-tests-lib',
];

$found_tests_dir = false;
if ( $_tests_dir && \is_dir( $_tests_dir ) ) {
	$found_tests_dir = true;
}
else {
	foreach ( $fallback_paths as $path ) {
		$checked_paths[] = $path;
		if ( \is_dir( $path ) ) {
			$_tests_dir = $path;
			$found_tests_dir = true;
			break;
		}
	}
}

if ( !$found_tests_dir ) {
	if ( shield_test_should_fail_missing_wp_env() ) {
		shield_test_error( 'ERROR: WordPress test environment not found.' );
		if ( shield_test_verbose() ) {
			foreach ( $checked_paths as $path ) {
				shield_test_error( 'Checked: '.$path );
			}
		}
		exit( 1 );
	}

	echo 'SKIP: WordPress integration tests skipped (WordPress test environment not found).'.\PHP_EOL;
	if ( shield_test_verbose() ) {
		foreach ( $checked_paths as $path ) {
			shield_test_log( 'Checked: '.$path );
		}
	}
	exit( 0 );
}

$wp_tests_functions_file = $_tests_dir.'/includes/functions.php';
if ( !\file_exists( $wp_tests_functions_file ) ) {
	shield_test_error( 'ERROR: WordPress test functions file not found at: '.$wp_tests_functions_file );
	exit( 1 );
}

require_once $wp_tests_functions_file;
if ( !\function_exists( 'tests_add_filter' ) ) {
	shield_test_error( 'ERROR: tests_add_filter() unavailable after loading WP test functions.' );
	exit( 1 );
}

function _manually_load_shield_plugin() {
	$plugin_dir = \getenv( 'SHIELD_PACKAGE_PATH' );
	if ( !$plugin_dir ) {
		$plugin_dir = \dirname( \dirname( __DIR__ ) );
	}

	$main_plugin_file = $plugin_dir.'/icwp-wpsf.php';
	$plugin_autoload = $plugin_dir.'/plugin_autoload.php';

	if ( !\file_exists( $main_plugin_file ) ) {
		shield_test_error( 'ERROR: Main plugin file not found: '.$main_plugin_file );
		return;
	}
	if ( !\file_exists( $plugin_autoload ) ) {
		shield_test_error( 'ERROR: Plugin autoload file not found: '.$plugin_autoload );
		return;
	}

	try {
		require_once $main_plugin_file;
		shield_test_log( 'Shield plugin file loaded.' );
	}
	catch ( \Error $e ) {
		shield_test_error( 'ERROR: Loading Shield plugin failed: '.$e->getMessage() );
		if ( shield_test_verbose() ) {
			shield_test_error( 'File: '.$e->getFile().':'.$e->getLine() );
		}
	}
}

tests_add_filter( 'plugins_loaded', '_manually_load_shield_plugin', 0 );
tests_add_filter( 'shield_security_development_mode', '__return_false' );

$table_prefix = 'wptests_';

$wp_tests_bootstrap_file = $_tests_dir.'/includes/bootstrap.php';
if ( !\file_exists( $wp_tests_bootstrap_file ) ) {
	shield_test_error( 'ERROR: WordPress test bootstrap file not found at: '.$wp_tests_bootstrap_file );
	exit( 1 );
}

require_once $wp_tests_bootstrap_file;

if ( !\function_exists( 'wp_insert_post' ) ) {
	shield_test_error( 'ERROR: WordPress functions unavailable after bootstrap.' );
	exit( 1 );
}

$shield_loaded = false;
$status_details = [];

if ( isset( $GLOBALS[ 'oICWP_Wpsf' ] ) && $GLOBALS[ 'oICWP_Wpsf' ] instanceof \ICWP_WPSF_Shield_Security ) {
	$shield_loaded = true;
	$status_details[] = 'Global $oICWP_Wpsf instance: available';
}
else {
	$status_details[] = 'Global $oICWP_Wpsf instance: missing';
}

if ( \function_exists( 'shield_security_get_plugin' ) ) {
	$shield_loaded = true;
	$status_details[] = 'shield_security_get_plugin(): available';
}
else {
	$status_details[] = 'shield_security_get_plugin(): missing';
}

if ( \class_exists( 'ICWP_WPSF_Shield_Security' ) && \class_exists( 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Controller\\Controller' ) ) {
	$shield_loaded = true;
	$status_details[] = 'Core Shield classes: available';
}
else {
	$status_details[] = 'Core Shield classes: missing';
}

if ( !$shield_loaded ) {
	shield_test_log( 'WARNING: Shield plugin may not have initialized correctly.' );
}
if ( shield_test_verbose() ) {
	foreach ( $status_details as $detail ) {
		shield_test_log( $detail );
	}
}

if ( $shield_loaded ) {
	try {
		$_shield_con = null;
		if ( \function_exists( 'shield_security_get_plugin' ) ) {
			$_shield_plugin = \shield_security_get_plugin();
			if ( $_shield_plugin && \method_exists( $_shield_plugin, 'getController' ) ) {
				$_shield_con = $_shield_plugin->getController();
			}
		}

		if ( $_shield_con !== null && isset( $_shield_con->db_con ) ) {
			$_shield_con->db_con->reset();
			$_shield_con->db_con->loadAll();
			shield_test_log( 'All Shield DB tables created.' );
		}
		else {
			shield_test_log( 'Shield db_con unavailable for eager table creation.' );
		}
	}
	catch ( \Throwable $e ) {
		shield_test_log( 'Error creating Shield tables: '.$e->getMessage() );
	}
}

$helpers_dir = \dirname( __DIR__ ).'/Helpers';
if ( \is_dir( $helpers_dir ) ) {
	foreach ( \glob( $helpers_dir.'/*.php' ) as $helper ) {
		require_once $helper;
		shield_test_log( 'Loaded helper: '.\basename( $helper ) );
	}
}

shield_test_log( '=== SHIELD INTEGRATION TEST BOOTSTRAP COMPLETE ===' );
