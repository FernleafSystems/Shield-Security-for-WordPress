<?php declare( strict_types=1 );

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

if ( function_exists( 'shield_security_get_plugin' ) ) {
	return;
}

function shield_security_get_plugin() :ICWP_WPSF_Shield_Security {
	return ICWP_WPSF_Shield_Security::GetInstance();
}

function shield_get_visitor_scores( $IP = null ) :array {
	return ( new Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::IP()->getRequestIp() )
		->scores();
}

function shield_get_visitor_score( $IP = null ) :int {
	return ( new Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::IP()->getRequestIp() )
		->probability();
}

/**
 * Calculates the visitor score then compares it against the user-defined score minimum for bots
 * @param null $IP - defaults to current visitor
 * @return bool - true if bot, false otherwise
 * @throws Exception
 */
function shield_test_ip_is_bot( $IP = null ) :bool {
	return shield_security_get_plugin()->getController()
									   ->getModule_IPs()
									   ->getBotSignalsController()
									   ->isBot( (string)$IP );
}