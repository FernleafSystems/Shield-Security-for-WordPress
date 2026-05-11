<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\BrowserFixtureRegistry;

\add_filter( 'pre_http_request', 'shield_browser_fixture_filelocker_api_response', 10, 3 );
\add_action( 'after_setup_theme', 'shield_browser_fixture_force_restrictions', \PHP_INT_MIN );

function shield_browser_fixture_force_restrictions() :void {
	if ( !\in_array( 'Y', [
			\get_option( 'shield_browser_fixture_security_admin_force_restrictions' ),
			\get_option( 'shield_browser_fixture_force_restrictions' ),
		], true )
		 || !\function_exists( 'shield_security_get_plugin' )
	) {
		return;
	}

	$plugin = \shield_security_get_plugin();
	$controller = \is_object( $plugin ) && \method_exists( $plugin, 'getController' )
		? $plugin->getController()
		: null;
	if ( \is_object( $controller ) && isset( $controller->this_req ) ) {
		$controller->this_req->request_bypasses_all_restrictions = false;
		$controller->this_req->request_subject_to_shield_restrictions = true;
	}
}

\add_action( 'rest_api_init', static function () :void {
	\register_rest_route( 'shield-browser-test/v1', '/fixture', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => static function ( \WP_REST_Request $request ) :\WP_REST_Response {
			try {
				$token = (string)$request->get_header( 'X-Shield-Browser-Fixture-Token' );
				$storedToken = \is_file( WP_CONTENT_DIR.'/.shield-browser-fixture-token' )
					? (string)\file_get_contents( WP_CONTENT_DIR.'/.shield-browser-fixture-token' )
					: '';
				if ( $token === '' || $storedToken === '' || !\hash_equals( \trim( $storedToken ), $token ) ) {
					return shield_browser_fixture_error_response( 'forbidden', 'Invalid browser fixture token.', 403 );
				}

				$params = $request->get_json_params();
				$params = \is_array( $params ) ? $params : [];
				$fixture = \trim( (string)( $params[ 'fixture' ] ?? '' ) );
				$action = \trim( (string)( $params[ 'action' ] ?? '' ) );
				$args = $params[ 'args' ] ?? [];
				$args = \is_array( $args ) ? \array_values( \array_filter(
					$args,
					static fn( $value ) :bool => \is_string( $value ) && $value !== ''
				) ) : [];

				if ( $fixture === '' || $action === '' ) {
					return shield_browser_fixture_error_response( 'invalid_request', 'Fixture and action are required.', 400 );
				}
				$allowedActions = shield_browser_fixture_allowed_actions();
				if ( !isset( $allowedActions[ $fixture ] ) ) {
					return shield_browser_fixture_error_response( 'invalid_fixture', 'Unknown browser fixture.', 400 );
				}
				if ( !\in_array( $action, $allowedActions[ $fixture ], true ) ) {
					return shield_browser_fixture_error_response( 'invalid_action', 'Unknown browser fixture action.', 400 );
				}

				shield_browser_fixture_require_helpers();
				return shield_browser_fixture_success_response(
					$fixture,
					$action,
					BrowserFixtureRegistry::run( $fixture, $action, $args )
				);
			}
			catch ( \Throwable $throwable ) {
				return shield_browser_fixture_error_response(
					'fixture_error',
					$throwable->getMessage(),
					500
				);
			}
		},
	] );
} );

/**
 * @param mixed                $preempt
 * @param array<string,mixed>  $args
 * @return mixed
 */
function shield_browser_fixture_filelocker_api_response( $preempt, array $args, string $url ) {
	if ( \strpos( $url, '/filelocker/' ) === false ) {
		return $preempt;
	}

	$mock = \get_option( 'shield_browser_fixture_filelocker_api', [] );
	if ( !\is_array( $mock ) || ( $mock[ 'enabled' ] ?? false ) !== true ) {
		return $preempt;
	}

	if ( \strpos( $url, '/filelocker/ciphers' ) !== false ) {
		$ciphers = \is_array( $mock[ 'ciphers' ] ?? null ) ? \array_values( \array_filter(
			$mock[ 'ciphers' ],
			static fn( $cipher ) :bool => \is_string( $cipher ) && $cipher !== ''
		) ) : [];
		if ( $ciphers === [] ) {
			return $preempt;
		}
		return shield_browser_fixture_http_response( [
			'ciphers' => $ciphers,
		] );
	}

	if ( \strpos( $url, '/filelocker/public_key' ) !== false ) {
		$publicKey = (string)( $mock[ 'public_key' ] ?? '' );
		if ( $publicKey === '' ) {
			return $preempt;
		}
		return shield_browser_fixture_http_response( [
			'key_id'  => (int)( $mock[ 'key_id' ] ?? 9001 ),
			'pub_key' => $publicKey,
		] );
	}

	if ( \strpos( $url, '/filelocker/decrypt' ) !== false ) {
		$originalContent = $mock[ 'original_content' ] ?? null;
		if ( !\is_string( $originalContent ) || $originalContent === '' ) {
			return $preempt;
		}
		return shield_browser_fixture_http_response( [
			'error_code'  => 0,
			'opened_data' => \base64_encode( $originalContent ),
		] );
	}

	return $preempt;
}

/**
 * @param array<string,mixed> $body
 * @return array<string,mixed>
 */
function shield_browser_fixture_http_response( array $body ) :array {
	return [
		'headers'  => [],
		'body'     => \wp_json_encode( $body ),
		'response' => [
			'code'    => 200,
			'message' => 'OK',
		],
		'cookies'  => [],
		'filename' => null,
	];
}

/**
 * @return array<string,list<string>>
 */
function shield_browser_fixture_allowed_actions() :array {
	return [
		'__all__' => [ 'cleanup' ],
		'actions-queue' => [ 'seed', 'cleanup', 'inspect' ],
		'import-export-file' => [ 'seed', 'cleanup' ],
		'ip-analysis-activity-meta' => [ 'seed', 'cleanup', 'inspect' ],
		'ip-rules-table' => [ 'seed', 'cleanup', 'inspect' ],
		'mainwp-sites' => [ 'seed', 'cleanup' ],
		'merlin-welcome' => [ 'seed', 'cleanup' ],
		'mfa-profile' => [ 'seed', 'cleanup' ],
		'notbot-altcha' => [ 'seed', 'cleanup', 'inspect' ],
		'public-block-recovery' => [ 'seed', 'cleanup' ],
		'security-admin' => [ 'seed', 'cleanup' ],
		'security-headers' => [ 'seed', 'cleanup' ],
	];
}

function shield_browser_fixture_require_helpers() :void {
	$pluginRoot = WP_PLUGIN_DIR.'/wp-simple-firewall';
	foreach ( [
		'tests/Helpers/RuntimeTestState.php',
		'tests/Helpers/TestDataFactory.php',
		'tests/Helpers/BrowserFixtureRegistry.php',
		'tests/Helpers/ActionRouter/PluginAdminRouteRuntime.php',
		'tests/Helpers/ActionRouter/ActionsQueueRuntimeProbe.php',
		'tests/Helpers/ActionRouter/ActionsQueueFixtureBuilder.php',
		'tests/Helpers/ActionRouter/ImportExportFileFixtureBuilder.php',
		'tests/Helpers/ActionRouter/IpAnalysisActivityMetaFixtureBuilder.php',
		'tests/Helpers/ActionRouter/IpRulesTableFixtureBuilder.php',
		'tests/Helpers/ActionRouter/MainwpSitesFixtureBuilder.php',
		'tests/Helpers/ActionRouter/MerlinWelcomeFixtureBuilder.php',
		'tests/Helpers/ActionRouter/MfaProfileFixtureBuilder.php',
		'tests/Helpers/ActionRouter/NotBotAltchaFixtureBuilder.php',
		'tests/Helpers/ActionRouter/PublicBlockRecoveryFixtureBuilder.php',
		'tests/Helpers/ActionRouter/SecurityAdminFixtureBuilder.php',
		'tests/Helpers/ActionRouter/SecurityHeadersFixtureBuilder.php',
	] as $relativePath ) {
		require_once $pluginRoot.'/'.$relativePath;
	}
}

/**
 * @param array<string,mixed> $data
 */
function shield_browser_fixture_success_response(
	string $fixture,
	string $action,
	array $data
) :\WP_REST_Response {
	return new \WP_REST_Response( [
		'ok'      => true,
		'fixture' => $fixture,
		'action'  => $action,
		'data'    => $data,
	] );
}

function shield_browser_fixture_error_response( string $errorCode, string $errorMessage, int $status ) :\WP_REST_Response {
	return new \WP_REST_Response( [
		'ok'    => false,
		'error' => [
			'code'    => $errorCode,
			'message' => $errorMessage,
		],
	], $status );
}

function shield_browser_fixture_mainwp_option_key() :string {
	return 'shield_browser_fixture_mainwp_sites';
}

function shield_browser_fixture_mainwp_runtime() :array {
	$state = \get_option( shield_browser_fixture_mainwp_option_key(), [] );
	if ( !\is_array( $state ) ) {
		return [];
	}

	$runtime = $state[ 'runtime' ] ?? [];
	return \is_array( $runtime ) ? $runtime : [];
}

function shield_browser_fixture_mainwp_active() :bool {
	$runtime = shield_browser_fixture_mainwp_runtime();
	return !empty( $runtime[ 'child_key' ] ) && !empty( $runtime[ 'sites' ] );
}

function shield_browser_fixture_mainwp_page() :string {
	$runtime = shield_browser_fixture_mainwp_runtime();
	return \is_string( $runtime[ 'page' ] ?? null ) ? $runtime[ 'page' ] : 'Extensions-Wp-Simple-Firewall';
}

function shield_browser_fixture_mainwp_child_file() :string {
	$runtime = shield_browser_fixture_mainwp_runtime();
	return \is_string( $runtime[ 'child_file' ] ?? null ) ? $runtime[ 'child_file' ] : '';
}

function shield_browser_fixture_mainwp_child_key() :string {
	$runtime = shield_browser_fixture_mainwp_runtime();
	return \is_string( $runtime[ 'child_key' ] ?? null ) ? $runtime[ 'child_key' ] : '';
}

function shield_browser_fixture_mainwp_sites() :array {
	$runtime = shield_browser_fixture_mainwp_runtime();
	return \is_array( $runtime[ 'sites' ] ?? null ) ? \array_values( $runtime[ 'sites' ] ) : [];
}

function shield_browser_fixture_mainwp_site_id( $website ) :int {
	if ( \is_array( $website ) ) {
		return (int)( $website[ 'id' ] ?? 0 );
	}
	if ( \is_object( $website ) ) {
		return (int)( $website->id ?? 0 );
	}
	return 0;
}

function shield_browser_fixture_mainwp_site_by_id( int $siteID ) :?array {
	foreach ( shield_browser_fixture_mainwp_sites() as $site ) {
		if ( \is_array( $site ) && (int)( $site[ 'id' ] ?? 0 ) === $siteID ) {
			return $site;
		}
	}

	return null;
}

function shield_browser_fixture_mainwp_sync_data_for_site( int $siteID ) :array {
	$runtime = shield_browser_fixture_mainwp_runtime();
	$sync = \is_array( $runtime[ 'sync' ] ?? null ) ? $runtime[ 'sync' ] : [];
	$data = $sync[ (string)$siteID ] ?? [];
	return \is_array( $data ) ? $data : [];
}

function shield_browser_fixture_mainwp_store_sync_data_for_site( int $siteID, array $syncData ) :void {
	$state = \get_option( shield_browser_fixture_mainwp_option_key(), [] );
	if ( !\is_array( $state ) || !\is_array( $state[ 'runtime' ] ?? null ) ) {
		return;
	}

	$sync = \is_array( $state[ 'runtime' ][ 'sync' ] ?? null ) ? $state[ 'runtime' ][ 'sync' ] : [];
	$sync[ (string)$siteID ] = $syncData;
	$state[ 'runtime' ][ 'sync' ] = $sync;
	\update_option( shield_browser_fixture_mainwp_option_key(), $state, false );
}

function shield_browser_fixture_mainwp_register_admin_page() :void {
	if ( !shield_browser_fixture_mainwp_active() || !\function_exists( 'add_menu_page' ) ) {
		return;
	}

	\add_menu_page(
		'MainWP Fixture',
		'MainWP Fixture',
		'manage_options',
		shield_browser_fixture_mainwp_page(),
		'shield_browser_fixture_mainwp_render_admin_page'
	);
}

function shield_browser_fixture_mainwp_render_admin_page() :void {
	$loader = '\FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\MwpExtensionLoader';
	if ( \class_exists( $loader ) ) {
		( new $loader() )->run();
	}
}

function shield_browser_fixture_mainwp_bootstrap() :void {
	if ( !shield_browser_fixture_mainwp_active() ) {
		return;
	}

	if ( !\defined( 'MAINWP_VERSION' ) ) {
		\define( 'MAINWP_VERSION', '5.2.0' );
	}

	if ( !\class_exists( 'MainWP\Dashboard\MainWP_Extensions_Handler', false ) ) {
		\class_alias( ShieldBrowserFixtureMainwpExtensionsHandler::class, 'MainWP\Dashboard\MainWP_Extensions_Handler' );
	}
	if ( !\class_exists( 'MainWP\Dashboard\MainWP_DB', false ) ) {
		\class_alias( ShieldBrowserFixtureMainwpDb::class, 'MainWP\Dashboard\MainWP_DB' );
	}
	if ( !\class_exists( 'MainWP\Dashboard\MainWP_Connect', false ) ) {
		\class_alias( ShieldBrowserFixtureMainwpConnect::class, 'MainWP\Dashboard\MainWP_Connect' );
	}
	if ( !\class_exists( 'MainWP\Dashboard\MainWP_Sync', false ) ) {
		\class_alias( ShieldBrowserFixtureMainwpSync::class, 'MainWP\Dashboard\MainWP_Sync' );
	}
	if ( !\class_exists( 'MainWP\Dashboard\MainWP_Extensions_Groups', false ) ) {
		\class_alias( ShieldBrowserFixtureMainwpExtensionsGroups::class, 'MainWP\Dashboard\MainWP_Extensions_Groups' );
	}

	\add_filter( 'mainwp_activated_check', static fn() :bool => true );
	\add_filter( 'mainwp_extension_enabled_check', static fn() :array => [
		'key' => shield_browser_fixture_mainwp_child_key(),
	] );
	\add_filter( 'mainwp_getsites', static fn() :array => shield_browser_fixture_mainwp_sites() );
	\add_filter( 'admin_body_class', static function ( string $classes ) :string {
		return \trim( $classes.' mainwp_page_'.shield_browser_fixture_mainwp_page() );
	} );
	\add_filter( 'shield/custom_enqueue_assets', static function ( array $assets ) :array {
		if ( (string)( $_GET[ 'page' ] ?? '' ) === shield_browser_fixture_mainwp_page() ) {
			$assets[] = 'mainwp_server';
		}
		return \array_values( \array_unique( $assets ) );
	} );
	\add_action( 'admin_menu', 'shield_browser_fixture_mainwp_register_admin_page', 20 );
}

if ( !\class_exists( 'ShieldBrowserFixtureMainwpExtensionsHandler', false ) ) {
	class ShieldBrowserFixtureMainwpExtensionsHandler {

		public static function get_extensions() :array {
			$extensions = \apply_filters( 'mainwp_getextensions', [] );
			$extensions = \is_array( $extensions ) ? $extensions : [];
			$childFile = shield_browser_fixture_mainwp_child_file();
			$hasShieldExtension = false;

			foreach ( $extensions as $key => $extension ) {
				if ( !\is_array( $extension ) ) {
					unset( $extensions[ $key ] );
					continue;
				}
				if ( (string)( $extension[ 'plugin' ] ?? '' ) === $childFile ) {
					$extension[ 'page' ] = $extension[ 'page' ] ?? shield_browser_fixture_mainwp_page();
					$extensions[ $key ] = $extension;
					$hasShieldExtension = true;
				}
			}

			if ( !$hasShieldExtension && $childFile !== '' ) {
				$extensions[] = [
					'plugin' => $childFile,
					'page'   => shield_browser_fixture_mainwp_page(),
				];
			}

			return \array_values( $extensions );
		}
	}
}

if ( !\class_exists( 'ShieldBrowserFixtureMainwpDb', false ) ) {
	class ShieldBrowserFixtureMainwpDb {

		private static ?self $instance = null;

		public static function instance() :self {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function get_website_by_id( int $siteID ) {
			$site = shield_browser_fixture_mainwp_site_by_id( $siteID );
			return \is_array( $site ) ? (object)$site : null;
		}

		public function get_website_option( $website, string $option ) :string {
			unset( $option );
			$siteID = shield_browser_fixture_mainwp_site_id( $website );
			$encoded = \wp_json_encode( shield_browser_fixture_mainwp_sync_data_for_site( $siteID ) );
			return \is_string( $encoded ) ? $encoded : '[]';
		}

		public function update_website_option( $website, string $option, string $value ) :void {
			unset( $option );
			$siteID = shield_browser_fixture_mainwp_site_id( $website );
			$decoded = \json_decode( $value, true );
			shield_browser_fixture_mainwp_store_sync_data_for_site(
				$siteID,
				\is_array( $decoded ) ? $decoded : []
			);
		}
	}
}

if ( !\class_exists( 'ShieldBrowserFixtureMainwpConnect', false ) ) {
	class ShieldBrowserFixtureMainwpConnect {

		public static function fetch_url_authed( $site, string $action, array $params = [] ) :array {
			unset( $site, $action, $params );
			return [ 'status' => 'SUCCESS' ];
		}
	}
}

if ( !\class_exists( 'ShieldBrowserFixtureMainwpSync', false ) ) {
	class ShieldBrowserFixtureMainwpSync {

		public static function sync_site( $site ) :bool {
			unset( $site );
			return true;
		}
	}
}

if ( !\class_exists( 'ShieldBrowserFixtureMainwpExtensionsGroups', false ) ) {
	class ShieldBrowserFixtureMainwpExtensionsGroups {

		public static function add_extension_menu( array $menu ) :void {
			unset( $menu );
		}
	}
}

shield_browser_fixture_mainwp_bootstrap();
