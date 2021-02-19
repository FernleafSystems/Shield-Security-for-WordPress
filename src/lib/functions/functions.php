<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield;

if ( function_exists( 'shield_security_get_plugin' ) ) {
	return;
}

function shield_security_get_plugin() :ICWP_WPSF_Shield_Security {
	return ICWP_WPSF_Shield_Security::GetInstance();
}