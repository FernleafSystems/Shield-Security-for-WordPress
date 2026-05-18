<?php
// WP-CLI eval-file wraps helpers before execution, so this file cannot declare strict_types first.

$configPath = WP_CONTENT_DIR.'/shield-upgrade-test/update.json';
$config = is_file( $configPath ) ? json_decode( (string)file_get_contents( $configPath ), true ) : [];
$url = is_array( $config ) ? (string)( $config[ 'package' ] ?? '' ) : '';
if ( $url === '' ) {
	echo wp_json_encode( [
		'ok'      => false,
		'message' => 'Package URL missing from update config.',
	] );
	return;
}

$response = wp_safe_remote_head( $url, [
	'timeout'     => 20,
	'redirection' => 0,
] );

if ( is_wp_error( $response ) ) {
	echo wp_json_encode( [
		'ok'      => false,
		'url'     => $url,
		'message' => $response->get_error_message(),
	] );
	return;
}

$status = (int)wp_remote_retrieve_response_code( $response );
echo wp_json_encode( [
	'ok'      => $status >= 200 && $status < 300,
	'url'     => $url,
	'status'  => $status,
	'message' => $status >= 200 && $status < 300 ? 'ok' : 'Unexpected status '.$status,
] );
