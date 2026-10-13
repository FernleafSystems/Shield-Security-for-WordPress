<?php declare( strict_types=1 );

/**
 * Starts the virtual patching checks as soon as Shield's plugin file is included. Composer isn't loaded yet, so
 * the self-contained classes are required directly. Nothing here may stop the site loading.
 */
if ( !\defined( 'ABSPATH' ) ) {
	return;
}

( static function () :void {
	try {
		$namespace = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\VirtualPatching\\';
		foreach ( [ 'RuleEvaluator', 'IpBypass', 'BlockLog', 'Ruleset\\RulesetFile', 'RequestGuard' ] as $class ) {
			if ( !\class_exists( $namespace.$class, false ) ) {
				require_once __DIR__.'/'.\str_replace( '\\', '/', $class ).'.php';
			}
		}
		\FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\RequestGuard::boot();
	}
	catch ( \Throwable $e ) {
	}
} )();
