<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\BrowserFixtureRegistry;

\add_filter( 'pre_http_request', 'shield_browser_fixture_filelocker_api_response', 10, 3 );

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
		'ip-analysis-activity-meta' => [ 'seed', 'cleanup' ],
		'ip-rules-table' => [ 'seed', 'cleanup' ],
		'merlin-welcome' => [ 'seed', 'cleanup' ],
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
		'tests/Helpers/ActionRouter/ImportExportFileFixtureBuilder.php',
		'tests/Helpers/ActionRouter/IpAnalysisActivityMetaFixtureBuilder.php',
		'tests/Helpers/ActionRouter/IpRulesTableFixtureBuilder.php',
		'tests/Helpers/ActionRouter/MerlinWelcomeFixtureBuilder.php',
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
