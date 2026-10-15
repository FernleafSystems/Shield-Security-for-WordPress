<?php declare( strict_types=1 );

/**
 * Starts the virtual patching checks as soon as Shield's plugin file is included. Composer isn't loaded yet, so
 * the self-contained classes, and the rule package classes they use, are required directly. Nothing here may stop
 * the site loading.
 */
if ( !\defined( 'ABSPATH' ) ) {
	return;
}

( static function () :void {
	try {
		$namespace = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\VirtualPatching\\';
		// Part way through a plugin update or deletion, a file can be missing, and a failed require is a fatal
		// error that can't be caught, so nothing is required unless it's there.
		if ( !\is_file( __DIR__.'/rule-package.php' ) ) {
			return;
		}
		$files = require __DIR__.'/rule-package.php';
		foreach ( [ 'IpBypass', 'BlockLog', 'Ruleset\\RulesetFile', 'RequestGuard' ] as $class ) {
			$files[ $namespace.$class ] = __DIR__.'/'.\str_replace( '\\', '/', $class ).'.php';
		}
		foreach ( $files as $class => $file ) {
			if ( !\class_exists( $class, false ) ) {
				if ( !\is_file( $file ) ) {
					return;
				}
				require_once $file;
			}
		}
		\FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\RequestGuard::boot();
	}
	catch ( \Throwable $e ) {
	}
} )();
