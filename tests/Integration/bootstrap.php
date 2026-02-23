<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\IntegrationBootstrapDecisions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestEnv;

require_once \dirname( __DIR__ ).'/Helpers/TestEnv.php';
require_once \dirname( __DIR__ ).'/Helpers/IntegrationBootstrapDecisions.php';

const SHIELD_TEST_MSG_MAIN_PLUGIN_NOT_FOUND_AT = 'ERROR: Main plugin file not found at: ';
const SHIELD_TEST_MSG_CHECKED_PATH_PREFIX = 'Checked: ';
const SHIELD_TEST_PLUGIN_CONTEXT_GLOBAL_KEY = 'shield_integration_plugin_context';

if ( !\function_exists( 'shield_test_verbose' ) ) {
	function shield_test_verbose() :bool {
		return TestEnv::isVerbose();
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

if ( !\function_exists( 'shield_test_format_path_for_log' ) ) {
	function shield_test_format_path_for_log( string $path ) :string {
		return TestEnv::normalizePathForLog( $path );
	}
}

if ( !\function_exists( 'shield_test_set_plugin_context' ) ) {
	/**
	 * @param array{
	 *   mode:string,
	 *   plugin_dir:string,
	 *   main_plugin_file:string,
	 *   plugin_autoload_file:string,
	 *   wp_plugin_dir:string
	 * } $pluginContext
	 */
	function shield_test_set_plugin_context( array $pluginContext ) :void {
		$GLOBALS[ SHIELD_TEST_PLUGIN_CONTEXT_GLOBAL_KEY ] = $pluginContext;
	}
}

if ( !\function_exists( 'shield_test_get_plugin_context' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function shield_test_get_plugin_context() :array {
		$context = $GLOBALS[ SHIELD_TEST_PLUGIN_CONTEXT_GLOBAL_KEY ] ?? null;
		return \is_array( $context ) ? $context : [];
	}
}

function _manually_load_shield_plugin() {
	$pluginContext = shield_test_get_plugin_context();
	if ( empty( $pluginContext ) ) {
		shield_test_error( 'ERROR: Plugin context unavailable for manual Shield plugin loading.' );
		return;
	}

	$main_plugin_file = $pluginContext[ 'main_plugin_file' ] ?? '';
	$plugin_autoload = $pluginContext[ 'plugin_autoload_file' ] ?? '';

	if ( !\is_string( $main_plugin_file ) || $main_plugin_file === '' ) {
		shield_test_error( 'ERROR: Invalid plugin context: missing main_plugin_file.' );
		return;
	}
	if ( !\is_string( $plugin_autoload ) || $plugin_autoload === '' ) {
		shield_test_error( 'ERROR: Invalid plugin context: missing plugin_autoload_file.' );
		return;
	}

	if ( !\file_exists( $main_plugin_file ) ) {
		shield_test_error( 'ERROR: Main plugin file not found: '.shield_test_format_path_for_log( $main_plugin_file ) );
		return;
	}
	if ( !\file_exists( $plugin_autoload ) ) {
		shield_test_error( 'ERROR: Plugin autoload file not found: '.shield_test_format_path_for_log( $plugin_autoload ) );
		return;
	}

	try {
		require_once $main_plugin_file;
		shield_test_log( 'Shield plugin file loaded.' );
	}
	catch ( \Error $e ) {
		shield_test_error( 'ERROR: Loading Shield plugin failed: '.$e->getMessage() );
		if ( shield_test_verbose() ) {
			shield_test_error( 'File: '.shield_test_format_path_for_log( $e->getFile() ).':'.$e->getLine() );
		}
	}
}

/**
 * @return array{
 *   repo_root:string,
 *   env:array<string,string|false>,
 *   plugin_context:array<string,mixed>,
 *   tests_dir:string,
 *   checked_paths:string[]
 * }
 */
function shield_integration_bootstrap_phase_prepare() :array {
	$_package_path_env = \getenv( 'SHIELD_PACKAGE_PATH' );
	$_is_package_testing = $_package_path_env !== false && !empty( $_package_path_env );
	$repoRoot = \dirname( \dirname( __DIR__ ) );
	$env = [
		'SHIELD_PACKAGE_PATH' => $_package_path_env,
		'WP_TESTS_DIR' => \getenv( 'WP_TESTS_DIR' ),
		'WP_DEVELOP_DIR' => \getenv( 'WP_DEVELOP_DIR' ),
	];

	shield_test_log( '=== SHIELD INTEGRATION TEST BOOTSTRAP ===' );
	shield_test_log( 'Environment: '.( $_is_package_testing ? 'Package Testing' : 'Source Testing' ) );
	shield_test_log( 'PHP Version: '.\PHP_VERSION );
	shield_test_log( 'Working Directory: '.shield_test_format_path_for_log( (string)\getcwd() ) );

	$rootAutoload = $repoRoot.'/vendor/autoload.php';
	if ( \file_exists( $rootAutoload ) ) {
		require_once $rootAutoload;
		shield_test_log( 'Composer dependencies loaded.' );
	}

	$pluginContext = IntegrationBootstrapDecisions::resolvePluginContext(
		$repoRoot,
		$env,
		\is_dir( '/tmp/wordpress/wp-content/plugins/wp-simple-firewall' ),
		TestEnv::isExplicitDockerMode()
	);
	shield_test_set_plugin_context( $pluginContext );

	$plugin_dir = $pluginContext[ 'plugin_dir' ];
	$main_plugin_file = $pluginContext[ 'main_plugin_file' ];

	if ( $pluginContext[ 'mode' ] === 'package' ) {
		if ( !\file_exists( $main_plugin_file ) ) {
			shield_test_error( SHIELD_TEST_MSG_MAIN_PLUGIN_NOT_FOUND_AT.shield_test_format_path_for_log( $main_plugin_file ) );
			exit( 1 );
		}

		$wp_plugin_dir = $pluginContext[ 'wp_plugin_dir' ];
		\putenv( 'WP_PLUGIN_DIR='.$wp_plugin_dir );
		if ( !\defined( 'WP_PLUGIN_DIR' ) ) {
			\define( 'WP_PLUGIN_DIR', $wp_plugin_dir );
		}

		shield_test_log( 'Package mode using plugin path: '.shield_test_format_path_for_log( $plugin_dir ) );
	}
	elseif ( $pluginContext[ 'mode' ] === 'docker_symlink' ) {
		if ( !\file_exists( $main_plugin_file ) ) {
			shield_test_error( 'ERROR: Docker symlinked plugin file missing: '.shield_test_format_path_for_log( $main_plugin_file ) );
			if ( shield_test_verbose() ) {
				$target = \is_link( $plugin_dir ) ? (string)\readlink( $plugin_dir ) : 'NOT A SYMLINK';
				shield_test_error( 'Symlink target: '.shield_test_format_path_for_log( $target ) );
			}
			exit( 1 );
		}
		shield_test_log( 'Docker mode using symlinked plugin path: '.shield_test_format_path_for_log( $plugin_dir ) );
	}
	elseif ( $pluginContext[ 'mode' ] === 'docker_missing_symlink' ) {
		shield_test_error( 'ERROR: Docker environment detected but plugin symlink is missing.' );
		exit( 1 );
	}
	else {
		if ( !\file_exists( $main_plugin_file ) ) {
			shield_test_error( SHIELD_TEST_MSG_MAIN_PLUGIN_NOT_FOUND_AT.shield_test_format_path_for_log( $main_plugin_file ) );
			exit( 1 );
		}
		shield_test_log( 'Source mode using plugin path: '.shield_test_format_path_for_log( $plugin_dir ) );
	}

	$wpTestsDirContext = IntegrationBootstrapDecisions::resolveWpTestsDirContext( $repoRoot, $env );
	$_tests_dir = $wpTestsDirContext[ 'candidate' ];
	$fallback_paths = $wpTestsDirContext[ 'fallback_paths' ];
	$checked_paths = $wpTestsDirContext[ 'checked_paths' ];

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
		if ( TestEnv::shouldFailMissingWordPressEnv() ) {
			shield_test_error( 'ERROR: WordPress test environment not found.' );
			if ( shield_test_verbose() ) {
				foreach ( $checked_paths as $path ) {
					shield_test_error( SHIELD_TEST_MSG_CHECKED_PATH_PREFIX.shield_test_format_path_for_log( $path ) );
				}
			}
			exit( 1 );
		}

		echo 'SKIP: WordPress integration tests skipped (WordPress test environment not found).'.\PHP_EOL;
		if ( shield_test_verbose() ) {
			foreach ( $checked_paths as $path ) {
				shield_test_log( SHIELD_TEST_MSG_CHECKED_PATH_PREFIX.shield_test_format_path_for_log( $path ) );
			}
		}
		exit( 0 );
	}

	return [
		'repo_root' => $repoRoot,
		'env' => $env,
		'plugin_context' => $pluginContext,
		'tests_dir' => $_tests_dir,
		'checked_paths' => $checked_paths,
	];
}

/**
 * @param array{
 *   repo_root:string,
 *   env:array<string,string|false>,
 *   plugin_context:array<string,mixed>,
 *   tests_dir:string,
 *   checked_paths:string[]
 * } $state
 * @return array{
 *   repo_root:string,
 *   env:array<string,string|false>,
 *   plugin_context:array<string,mixed>,
 *   tests_dir:string,
 *   checked_paths:string[]
 * }
 */
function shield_integration_bootstrap_phase_bootstrap_wordpress( array $state ) :array {
	$_tests_dir = $state[ 'tests_dir' ];

	$wp_tests_functions_file = $_tests_dir.'/includes/functions.php';
	if ( !\file_exists( $wp_tests_functions_file ) ) {
		shield_test_error( 'ERROR: WordPress test functions file not found at: '.shield_test_format_path_for_log( $wp_tests_functions_file ) );
		exit( 1 );
	}

	require_once $wp_tests_functions_file;
	if ( !\function_exists( 'tests_add_filter' ) ) {
		shield_test_error( 'ERROR: tests_add_filter() unavailable after loading WP test functions.' );
		exit( 1 );
	}

	tests_add_filter( 'plugins_loaded', '_manually_load_shield_plugin', 0 );
	tests_add_filter( 'shield_security_development_mode', '__return_false' );

	global $table_prefix;
	$table_prefix = 'wptests_';

	$wp_tests_bootstrap_file = $_tests_dir.'/includes/bootstrap.php';
	if ( !\file_exists( $wp_tests_bootstrap_file ) ) {
		shield_test_error( 'ERROR: WordPress test bootstrap file not found at: '.shield_test_format_path_for_log( $wp_tests_bootstrap_file ) );
		exit( 1 );
	}

	require_once $wp_tests_bootstrap_file;

	if ( !\function_exists( 'wp_insert_post' ) ) {
		shield_test_error( 'ERROR: WordPress functions unavailable after bootstrap.' );
		exit( 1 );
	}

	return $state;
}

/**
 * @param array{
 *   repo_root:string,
 *   env:array<string,string|false>,
 *   plugin_context:array<string,mixed>,
 *   tests_dir:string,
 *   checked_paths:string[]
 * } $state
 */
function shield_integration_bootstrap_phase_finalize( array $state ) :void {
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
}

$state = shield_integration_bootstrap_phase_prepare();
$state = shield_integration_bootstrap_phase_bootstrap_wordpress( $state );
shield_integration_bootstrap_phase_finalize( $state );
