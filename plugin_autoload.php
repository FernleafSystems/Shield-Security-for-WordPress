<?php declare( strict_types=1 );

if ( !\defined( 'ABSPATH' ) ) { exit(); }

require_once( __DIR__.'/vendor/autoload.php' );
if ( \file_exists( __DIR__.'/vendor_prefixed/autoload.php' ) ) {
	require_once( __DIR__.'/vendor_prefixed/autoload.php' );
}

/** We initialise our Carbon early. */
@\class_exists( '\Carbon\Carbon' );
