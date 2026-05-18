<?php
// WP-CLI eval-file wraps helpers before execution, so this file cannot declare strict_types first.

$fixtureArgs = isset( $args ) && is_array( $args ) ? $args : [];
$payload = (string)( $fixtureArgs[ 0 ] ?? '' );
$decoded = json_decode( (string)base64_decode( $payload, true ), true );
if ( !is_array( $decoded ) ) {
	fwrite( STDERR, 'Update config payload is invalid.'.PHP_EOL );
	exit( 1 );
}

$dir = WP_CONTENT_DIR.'/shield-upgrade-test';
if ( !is_dir( $dir ) && !mkdir( $dir, 0777, true ) && !is_dir( $dir ) ) {
	fwrite( STDERR, 'Could not create update config directory.'.PHP_EOL );
	exit( 1 );
}

file_put_contents(
	$dir.'/update.json',
	wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ).PHP_EOL
);
delete_site_transient( 'update_plugins' );

echo wp_json_encode( [
	'ok'      => true,
	'plugin'  => (string)( $decoded[ 'plugin' ] ?? '' ),
	'version' => (string)( $decoded[ 'new_version' ] ?? '' ),
] );
