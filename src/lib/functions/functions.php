<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield;

if ( function_exists( 'shield_security_get_plugin' ) ) {
	return;
}

function shield_security_get_plugin() :ICWP_WPSF_Shield_Security {
	return ICWP_WPSF_Shield_Security::GetInstance();
}

function shield_get_bot_probability_score() :int {
	/** TODO: Enhancements to Bot scores */
	$isVerified = shield_security_get_plugin()->getController()
											  ->getModule_Plugin()
											  ->getHandlerAntibot()
											  ->verify();
	return $isVerified ? 0 : 100;
}