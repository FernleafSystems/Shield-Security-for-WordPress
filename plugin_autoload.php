<?php declare( strict_types=1 );

if ( !\defined( 'ABSPATH' ) ) { exit(); }

\call_user_func( function () {
	$shieldLoader = require __DIR__.'/vendor/autoload.php';
	$shieldPrefixedLoader = is_file( __DIR__.'/vendor_prefixed/autoload.php' ) ? require __DIR__.'/vendor_prefixed/autoload.php' : null;

	/**
	 * Unfortunately, the code profiler doesn't distinguish composer class loaders granularly enough, so Shield
	 * gets attribution for Composer loader calls/misses, as it instantiates its loaders early.
	 * Here we get out of the way for the profiler so they can attribute a more accurate rating to Shield
	 */
	if ( \function_exists( 'add_action' ) ) {
		add_action( 'plugins_loaded', static function () use ( $shieldLoader, $shieldPrefixedLoader ) {
			if ( \defined( 'CODE_PROFILER_VERSION' ) ) {
				foreach ( [ $shieldLoader, $shieldPrefixedLoader ] as $l ) {
					if ( \is_object( $l ) && \method_exists( $l, 'unregister' ) && \method_exists( $l, 'register' ) ) {
						$l->unregister();
						$l->register( false );
					}
				}
			}
		}, PHP_INT_MAX );
	}
} );

/** We initialise our Carbon early. */
@\class_exists( '\Carbon\Carbon' );
