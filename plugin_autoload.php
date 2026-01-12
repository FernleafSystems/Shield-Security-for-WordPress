<?php declare( strict_types=1 );

if ( !\defined( 'ABSPATH' ) ) { exit(); }

require_once( __DIR__.'/src/lib/vendor/autoload.php' );

/** We initialise our Carbon early. */
@\class_exists( '\Carbon\Carbon' );