<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\BrowserFixtureRegistry;

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
 * @return array<string,list<string>>
 */
function shield_browser_fixture_allowed_actions() :array {
	return [
		'__all__' => [ 'cleanup' ],
		'actions-queue' => [ 'seed', 'cleanup', 'inspect' ],
		'ip-analysis-activity-meta' => [ 'seed', 'cleanup' ],
		'mfa-profile' => [ 'seed', 'cleanup' ],
		'public-block-recovery' => [ 'seed', 'cleanup' ],
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
		'tests/Helpers/ActionRouter/IpAnalysisActivityMetaFixtureBuilder.php',
		'tests/Helpers/ActionRouter/MfaProfileFixtureBuilder.php',
		'tests/Helpers/ActionRouter/PublicBlockRecoveryFixtureBuilder.php',
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
