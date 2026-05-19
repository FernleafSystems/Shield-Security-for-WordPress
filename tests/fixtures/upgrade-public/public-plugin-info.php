<?php
// WP-CLI eval-file wraps helpers before execution, so this file cannot declare strict_types first.

require_once ABSPATH.'wp-admin/includes/plugin-install.php';

$info = plugins_api( 'plugin_information', [
	'slug'   => 'wp-simple-firewall',
	'fields' => [
		'versions' => true,
	],
] );

if ( is_wp_error( $info ) ) {
	fwrite( STDERR, $info->get_error_message().PHP_EOL );
	exit( 1 );
}

echo wp_json_encode( [
	'slug'          => 'wp-simple-firewall',
	'version'       => (string)( $info->version ?? '' ),
	'download_link' => (string)( $info->download_link ?? '' ),
	'requires'      => (string)( $info->requires ?? '' ),
	'requires_php'  => (string)( $info->requires_php ?? '' ),
	'tested'        => (string)( $info->tested ?? '' ),
] );
