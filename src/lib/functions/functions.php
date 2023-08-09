<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield\Functions;

if ( \function_exists( 'shield_security_get_plugin' ) ) {
	return;
}

function shield_security_get_plugin() :ICWP_WPSF_Shield_Security {
	return Functions\get_plugin();
}

function shield_get_visitor_scores( $IP = null ) :array {
	return Functions\get_visitor_scores( $IP );
}

function shield_get_visitor_score( $IP = null ) :int {
	return Functions\get_visitor_score( $IP );
}

/**
 * @param null $IP - defaults to current visitor
 * @throws \Exception
 */
function shield_test_ip_is_bot( $IP = null ) :bool {
	return Functions\test_ip_is_bot( $IP );
}

function shield_get_ip_state( string $ip = '' ) :string {
	return Functions\get_ip_state( $ip );
}

function shield_fire_event( string $event ) {
	Functions\fire_event( $event );
}

function shield_start_scans( array $scans ) {
	Functions\start_scans( $scans );
}