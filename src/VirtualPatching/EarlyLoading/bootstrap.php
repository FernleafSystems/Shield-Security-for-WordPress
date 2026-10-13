<?php declare( strict_types=1 );

/**
 * The early pass, included by the early loader stub through auto_prepend_file before the requested script
 * runs. Composer and WordPress aren't loaded, so the self-contained classes are required directly. Nothing
 * here may stop the site loading, and nothing is left in the global scope apart from SHIELD_EARLY_RAN.
 */
if ( !\defined( 'SHIELD_EARLY_RULESET' ) || \defined( 'SHIELD_EARLY_RAN' ) || \PHP_SAPI === 'cli' ) {
	return;
}

( static function ( $rulesetPath ) :void {
	try {
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
				require_once \dirname( __DIR__ ).'/'.\str_replace( '\\', '/', $class ).'.php';
			}
		}

		$ruleset = \is_string( $rulesetPath )
			? \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\RulesetFile::read( $rulesetPath )
			: null;
		if ( $ruleset === null || empty( $ruleset[ 'rules' ] ) ) {
			return;
		}

		$pass = new \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\EarlyPass( $ruleset, $_SERVER, $_GET, $_POST );
		$blocked = $pass->run();
		if ( $blocked !== null ) {
			( new \FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\RequestGuard( $ruleset ) )->sendForbidden( $blocked, 'early' );
		}
		if ( $pass->isComplete() ) {
			\define( 'SHIELD_EARLY_RAN', $ruleset[ 'hash' ] );
		}
	}
	catch ( \Throwable $e ) {
	}
} )( \constant( 'SHIELD_EARLY_RULESET' ) );
