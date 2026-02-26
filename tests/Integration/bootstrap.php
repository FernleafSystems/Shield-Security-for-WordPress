<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\IntegrationBootstrapDecisions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestEnv;

// Intentional manual joins: integration bootstrap starts before Composer autoload is guaranteed.
require_once \dirname( __DIR__ ).'/Helpers/TestEnv.php';
// Intentional manual join: integration bootstrap starts before Composer autoload is guaranteed.
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

if ( !\function_exists( 'shield_test_enable_php_error_logging' ) ) {
	function shield_test_enable_php_error_logging() :void {
		@\ini_set( 'log_errors', '1' );
		@\ini_set( 'error_log', 'php://stderr' );
		shield_test_log( 'PHP error logging redirected to stderr.' );
	}
}

if ( !\function_exists( 'shield_test_apply_wp_plugin_dir' ) ) {
	/**
	 * @param array<string,mixed> $pluginContext
	 */
	function shield_test_apply_wp_plugin_dir( array $pluginContext ) :void {
		$wpPluginDir = $pluginContext[ 'wp_plugin_dir' ] ?? '';
		if ( !\is_string( $wpPluginDir ) || $wpPluginDir === '' ) {
			shield_test_error( 'ERROR: Invalid plugin context: missing wp_plugin_dir.' );
			exit( 1 );
		}

		\putenv( 'WP_PLUGIN_DIR='.$wpPluginDir );
		if ( !\defined( 'WP_PLUGIN_DIR' ) ) {
			\define( 'WP_PLUGIN_DIR', $wpPluginDir );
		}

		$effectiveWpPluginDir = \defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : $wpPluginDir;
		shield_test_log( 'WP_PLUGIN_DIR: '.shield_test_format_path_for_log( (string)$effectiveWpPluginDir ) );
	}
}

if ( !\function_exists( 'shield_test_fail_bootstrap' ) ) {
	/**
	 * @param array<string,mixed> $state
	 */
	function shield_test_fail_bootstrap( string $message, array $state = [], ?\Throwable $e = null ) :void {
		shield_test_error( 'ERROR: '.$message );

		$pluginContext = $state[ 'plugin_context' ] ?? shield_test_get_plugin_context();
		if ( \is_array( $pluginContext ) && !empty( $pluginContext ) ) {
			$mode = $pluginContext[ 'mode' ] ?? 'unknown';
			$pluginDir = $pluginContext[ 'plugin_dir' ] ?? '';
			$mainPluginFile = $pluginContext[ 'main_plugin_file' ] ?? '';

			shield_test_error( 'Plugin context mode: '.(string)$mode );
			if ( \is_string( $pluginDir ) && $pluginDir !== '' ) {
				shield_test_error( 'Plugin context dir: '.shield_test_format_path_for_log( $pluginDir ) );
			}
			if ( \is_string( $mainPluginFile ) && $mainPluginFile !== '' ) {
				shield_test_error( 'Main plugin file: '.shield_test_format_path_for_log( $mainPluginFile ) );
			}
		}

		$wpPluginDir = \defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : ( \getenv( 'WP_PLUGIN_DIR' ) ?: 'not set' );
		shield_test_error( 'WP_PLUGIN_DIR: '.shield_test_format_path_for_log( (string)$wpPluginDir ) );

		if ( $e instanceof \Throwable ) {
			shield_test_error( 'Exception: '.\get_class( $e ).': '.$e->getMessage() );
			shield_test_error( 'Exception file: '.shield_test_format_path_for_log( $e->getFile() ).':'.$e->getLine() );
			if ( shield_test_verbose() ) {
				shield_test_error( $e->getTraceAsString() );
			}
		}

		exit( 1 );
	}
}

if ( !\function_exists( 'shield_integration_assert_controller_ready' ) ) {
	/**
	 * @param array<string,mixed> $state
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller
	 */
	function shield_integration_assert_controller_ready( array $state ) {
		if ( !\function_exists( 'shield_security_get_plugin' ) ) {
			shield_test_fail_bootstrap( 'Shield global helper function is unavailable after bootstrap.', $state );
		}

		try {
			$plugin = \shield_security_get_plugin();
		}
		catch ( \Throwable $e ) {
			shield_test_fail_bootstrap( 'Shield plugin instance could not be resolved.', $state, $e );
		}

		if ( !\is_object( $plugin ) || !\method_exists( $plugin, 'getController' ) ) {
			shield_test_fail_bootstrap( 'Shield plugin instance is invalid or missing getController().', $state );
		}

		try {
			$controller = $plugin->getController();
		}
		catch ( \Throwable $e ) {
			shield_test_fail_bootstrap( 'Shield controller could not be resolved.', $state, $e );
		}

		if ( !\is_object( $controller ) ) {
			shield_test_fail_bootstrap( 'Shield controller is not an object.', $state );
		}

		try {
			$cfg = $controller->cfg;
			$properties = $cfg->properties ?? null;
			$slugParent = \is_array( $properties ) ? (string)( $properties[ 'slug_parent' ] ?? '' ) : '';
			$slugPlugin = \is_array( $properties ) ? (string)( $properties[ 'slug_plugin' ] ?? '' ) : '';
		}
		catch ( \Throwable $e ) {
			shield_test_fail_bootstrap( 'Shield controller config is not boot-ready.', $state, $e );
		}

		if ( !\is_object( $cfg ) || !\is_array( $properties ) || $slugParent === '' || $slugPlugin === '' ) {
			shield_test_fail_bootstrap(
				'Shield controller config is incomplete (missing properties.slug_parent/slug_plugin).',
				$state
			);
		}

		return $controller;
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
	$plugin_mode = (string)( $pluginContext[ 'mode' ] ?? '' );

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

	// Manual loading in docker symlink mode can bypass WordPress' normal plugin realpath registration.
	// Registering the plugin realpath ensures plugin_basename() resolves to the expected base file.
	if ( $plugin_mode === 'docker_symlink' && \function_exists( 'wp_register_plugin_realpath' ) ) {
		\wp_register_plugin_realpath( $main_plugin_file );
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
	shield_test_enable_php_error_logging();

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

	// Intentional manual join: this autoload probe occurs before Symfony classes can be assumed.
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

		shield_test_apply_wp_plugin_dir( $pluginContext );
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
		shield_test_apply_wp_plugin_dir( $pluginContext );
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
		shield_test_apply_wp_plugin_dir( $pluginContext );
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

	// Intentional manual join: WordPress test bootstrap paths are resolved before relying on Symfony Path.
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

	// Intentional manual join: WordPress bootstrap include uses raw string composition for pre-bootstrap safety.
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
	$_shield_con = shield_integration_assert_controller_ready( $state );

	try {
		if ( isset( $_shield_con->db_con ) ) {
			$_shield_con->db_con->reset();
			$_shield_con->db_con->loadAll();
			shield_test_log( 'All Shield DB tables created.' );
		}
		else {
			shield_test_log( 'Shield db_con unavailable for eager table creation.' );
		}
	}
	catch ( \Throwable $e ) {
		shield_test_fail_bootstrap( 'Error creating Shield DB tables during bootstrap finalize.', $state, $e );
	}

	// Intentional manual join: helper discovery keeps raw glob path assembly in bootstrap context.
	$helpers_dir = \dirname( __DIR__ ).'/Helpers';
	if ( \is_dir( $helpers_dir ) ) {
		// Intentional manual join: glob pattern is composed directly for bootstrap-time helper loading.
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
