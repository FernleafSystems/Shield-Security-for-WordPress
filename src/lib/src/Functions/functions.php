<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Functions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

function get_plugin() :\ICWP_WPSF_Shield_Security {
	return \ICWP_WPSF_Shield_Security::GetInstance();
}

function get_visitor_scores( $IP = null ) :array {
	return ( new IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::Request()->ip() )
		->scores();
}

function get_visitor_score( $IP = null ) :int {
	return ( new IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setMod( shield_security_get_plugin()->getController()->getModule_IPs() )
		->setIP( $IP ?? Services::Request()->ip() )
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

function get_ip_state( string $ip = '' ) :string {
	$mod = get_plugin()->getController()->getModule_IPs();
	$dbh = $mod->getDbH_IPRules();

	$state = 'none';

	$ip = ( new IPs\Lib\Ops\LookupIP() )
		->setMod( $mod )
		->setIP( empty( $ip ) ? Services::Request()->ip() : $ip )
		->lookupIp();

	if ( !empty( $ip ) ) {
		switch ( $ip->type ) {

			case $dbh::T_MANUAL_WHITE:
				$state = 'bypass';
				break;

			case $dbh::T_MANUAL_BLACK:
				$state = 'blocked';
				break;

			case $dbh::T_AUTO_BLACK:
				$state = $ip->blocked_at ? 'blocked' : 'offense';
				break;

			case $dbh::T_CROWDSEC:
				$state = 'crowdsec';
				break;

			default:
				throw new \Exception( 'unknown state:'.$state );
		}
	}
	return $state;
}

function fire_event( string $event ) {
	get_plugin()->getController()->fireEvent( $event );
}