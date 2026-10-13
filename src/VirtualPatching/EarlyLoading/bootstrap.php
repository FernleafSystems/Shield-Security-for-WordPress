<?php declare( strict_types=1 );

/**
 * The early pass, included by the early loader stub through auto_prepend_file before the requested script
 * runs. Composer and WordPress aren't loaded, so the self-contained classes are required directly.
 *
 * Nothing here may stop the site loading or print anything: shared hosts often display PHP warnings, so any
 * warning is suppressed and any output is discarded, and on any failure the request carries on as normal.
 * Nothing is left in the global scope apart from SHIELD_EARLY_RAN.
 */
if ( !\defined( 'SHIELD_EARLY_RULESET' ) || !\defined( 'SHIELD_EARLY_SCOPE' ) || \defined( 'SHIELD_EARLY_RAN' )
	 || \PHP_SAPI === 'cli' ) {
	return;
}

( static function ( $rulesetPath, $scope ) :void {
	$ruleset = null;
	$blocked = null;
	$complete = false;

	$quiet = \function_exists( 'set_error_handler' ) && \function_exists( 'restore_error_handler' );
	if ( $quiet ) {
		\set_error_handler( static fn() => true );
	}
	$buffered = \function_exists( 'ob_start' ) && \ob_start();
	try {
		$script = $_SERVER[ 'SCRIPT_FILENAME' ] ?? '';
		$namespace = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\VirtualPatching\\';
		foreach (
			[
				'RuleEvaluator',
				'IpBypass',
				'BlockLog',
				'Ruleset\\RulesetFile',
				'RequestGuard',
				'EarlyLoading\\EarlyPass',
			] as $class
		) {
			if ( !\class_exists( $namespace.$class, false ) ) {
				// Part way through a plugin update or deletion, a file can be missing; on PHP 7 a failed
				// require can't be caught, so nothing is required unless it's there.
				$file = \dirname( __DIR__ ).'/'.\str_replace( '\\', '/', $class ).'.php';
				if ( !\is_file( $file ) ) {
					return;
				}
				require_once $file;
			}
		}

		if ( \is_string( $rulesetPath ) && \is_array( $scope ) && \is_string( $script )
			 && \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\EarlyPass::coversScript( $script, $scope ) ) {
			$ruleset = \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile::read( $rulesetPath );
			if ( $ruleset !== null && !empty( $ruleset[ 'rules' ] ) ) {
				$pass = new \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\EarlyPass( $ruleset, $_SERVER, $_GET, $_POST );
				$blocked = $pass->run();
				$complete = $pass->isComplete();
			}
		}
	}
	catch ( \Throwable $e ) {
		$blocked = null;
		$complete = false;
	}
	finally {
		if ( $buffered ) {
			\ob_end_clean();
		}
		if ( $quiet ) {
			\restore_error_handler();
		}
	}

	if ( $blocked !== null && $ruleset !== null ) {
		try {
			( new \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\RequestGuard( $ruleset ) )->sendForbidden( $blocked, 'early' );
		}
		catch ( \Throwable $e ) {
		}
	}
	if ( $complete && $ruleset !== null ) {
		\define( 'SHIELD_EARLY_RAN', $ruleset[ 'hash' ] );
	}
} )( \constant( 'SHIELD_EARLY_RULESET' ), \constant( 'SHIELD_EARLY_SCOPE' ) );
