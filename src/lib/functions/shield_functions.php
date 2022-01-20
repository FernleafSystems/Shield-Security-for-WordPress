<?php declare( strict_types=1 );

namespace Shield;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

if ( function_exists( '\Shield\shield_security_get_plugin' ) ) {
	return;
}

function get_plugin() :\ICWP_WPSF_Shield_Security {
	return \ICWP_WPSF_Shield_Security::GetInstance();
}

function get_visitor_scores( $IP = null ) :array {
	return ( new IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::IP()->getRequestIp() )
		->scores();
}

function get_visitor_score( $IP = null ) :int {
	return ( new IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::IP()->getRequestIp() )
		->probability();
}

/**
 * Calculates the visitor score then compares it against the user-defined score minimum for bots
 * @param null $IP - defaults to current visitor
 * @throws \Exception
 */
function test_ip_is_bot( $IP = null ) :bool {
	return shield_security_get_plugin()->getController()
									   ->getModule_IPs()
									   ->getBotSignalsController()
									   ->isBot( (string)$IP );
}

function get_ip_state( string $ip = '', string $state = 'bypass' ) :string {
	$mod = get_plugin()->getController()->getModule_IPs();

	$state = 'none';

	$ip = ( new IPs\Lib\Ops\LookupIpOnList() )
		->setDbHandler( $mod->getDbHandler_IPs() )
		->setIP( empty( $ip ) ? Services::IP()->getRequestIp() : $ip )
		->lookupIp();

	if ( !empty( $ip ) ) {
		switch ( $ip->list ) {

			case $mod::LIST_MANUAL_WHITE:
				$state = 'bypass';
				break;

			case $mod::LIST_MANUAL_BLACK:
				$state = 'blocked';
				break;

			case $mod::LIST_AUTO_BLACK:
				$state = $ip->blocked_at ? 'blocked' : 'offense';
				break;

			default:
				throw new \Exception( 'unknown state:'.$state );
		}
	}
	return $state;
}