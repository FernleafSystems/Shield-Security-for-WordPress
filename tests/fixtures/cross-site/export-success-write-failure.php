<?php declare( strict_types=1 );

/**
 * Plugin Name: Shield Cross-Site Export Success Write Failure
 * Description: Test-only bounded failure injection for master export-success persistence.
 */

define( 'SHIELD_CROSS_SITE_EXPORT_SUCCESS_FAILURE_ACTIVE', true );
define( 'SHIELD_CROSS_SITE_EXPORT_SUCCESS_FAILURE_ENABLED_OPTION', 'shield_cross_site_export_success_failure_enabled' );
define( 'SHIELD_CROSS_SITE_EXPORT_SUCCESS_FAILURE_LIMIT_OPTION', 'shield_cross_site_export_success_failure_limit' );
define( 'SHIELD_CROSS_SITE_EXPORT_SUCCESS_ATTEMPTS_OPTION', 'shield_cross_site_export_success_attempts' );

add_filter( 'query', static function ( string $query ) :string {
	static $recording = false;
	$successColumn = strpos( $query, '`last_export_success_at`=' );
	$where = strpos( $query, ' WHERE ' );
	if ( $recording
		 || strpos( $query, 'UPDATE `' ) !== 0
		 || strpos( $query, 'importexport_sites' ) === false
		 || $successColumn === false
		 || $where === false
		 || $successColumn > $where
		 || !get_option( SHIELD_CROSS_SITE_EXPORT_SUCCESS_FAILURE_ENABLED_OPTION, false ) ) {
		return $query;
	}

	$recording = true;
	$attempt = max( 0, (int)get_option( SHIELD_CROSS_SITE_EXPORT_SUCCESS_ATTEMPTS_OPTION, 0 ) ) + 1;
	update_option( SHIELD_CROSS_SITE_EXPORT_SUCCESS_ATTEMPTS_OPTION, $attempt, false );
	$failureLimit = max( 0, (int)get_option( SHIELD_CROSS_SITE_EXPORT_SUCCESS_FAILURE_LIMIT_OPTION, 0 ) );
	$recording = false;

	return $attempt <= $failureLimit
		? 'UPDATE intentionally_invalid_export_success_sql'
		: $query;
}, PHP_INT_MAX );
