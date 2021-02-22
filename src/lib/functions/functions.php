<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

if ( function_exists( 'shield_security_get_plugin' ) ) {
	return;
}

function shield_security_get_plugin() :ICWP_WPSF_Shield_Security {
	return ICWP_WPSF_Shield_Security::GetInstance();
}

function shield_get_bot_scores( $IP = null ) :array {
	return ( new Shield\Modules\IPs\Lib\Bots\Calculator\CalculateBotProbability() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::IP()->getRequestIp() )
		->scores();
}

function shield_get_bot_probability_score( $IP = null ) :int {
	return ( new Shield\Modules\IPs\Lib\Bots\Calculator\CalculateBotProbability() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::IP()->getRequestIp() )
		->probability();
}