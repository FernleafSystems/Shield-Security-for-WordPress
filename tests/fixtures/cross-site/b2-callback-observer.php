<?php declare( strict_types=1 );

/**
 * Plugin Name: Shield Cross-Site B2 Callback Observer
 * Description: Test-only counter for real import/export handshake confirmation requests.
 */

define( 'SHIELD_CROSS_SITE_B2_CALLBACK_OBSERVER_ACTIVE', true );
define( 'SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION', 'shield_cross_site_b2_callback_count' );

add_action( 'muplugins_loaded', static function () :void {
	if ( (string)( $_GET[ 'action' ] ?? '' ) === 'shield_action'
		 && (string)( $_GET[ 'ex' ] ?? '' ) === 'importexport_handshake' ) {
		update_option(
			SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION,
			max( 0, (int)get_option( SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION, 0 ) ) + 1,
			false
		);
	}
} );
