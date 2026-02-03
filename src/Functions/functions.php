<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Functions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Services\Services;

function get_plugin() :\ICWP_WPSF_Shield_Security {
	return \ICWP_WPSF_Shield_Security::GetInstance();
}

function get_visitor_scores( $IP = null ) :array {
	return ( new IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setIP( $IP ?? Services::Request()->ip() )
		->scores();
}

function get_visitor_score( $IP = null ) :int {
	return ( new IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
		->setIP( $IP ?? Services::Request()->ip() )
		->probability();
}

/**
 * Calculates the visitor score then compares it against the user-defined score minimum for bots
 * @param null $IP - defaults to current visitor
 * @throws \Exception
 */
function test_ip_is_bot( $IP = null ) :bool {
	return shield_security_get_plugin()->getController()->comps->bot_signals->isBot( (string)$IP );
}

function get_ip_state( string $ip = '' ) :string {
	$state = 'none';
	try {
		$ipRuleStatus = new IpRuleStatus( empty( $ip ) ? Services::Request()->ip() : $ip );
		if ( $ipRuleStatus->isBypass() ) {
			$state = 'bypass';
		}
		elseif ( $ipRuleStatus->isBlockedByCrowdsec() ) {
			$state = 'crowdsec';
		}
		elseif ( $ipRuleStatus->isBlocked() ) {
			$state = 'blocked';
		}
		elseif ( $ipRuleStatus->isAutoBlacklisted() ) {
			$state = 'offense';
		}
	}
	catch ( \Exception $e ) {
	}
	return $state;
}

function fire_event( string $event ) {
	get_plugin()->getController()->fireEvent( $event );
}

function start_scans( array $scans ) {
	$con = shield_security_get_plugin()->getController();
	if ( $con->caps->hasCap( 'scan_frequent' ) ) {
		$con->comps->scans->startNewScans( $scans );
	}
}