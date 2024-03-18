<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class IsHighReputationIP {

	use PluginControllerConsumer;
	use IpAddressConsumer;

	public function query() :bool {
		return ( new CalculateVisitorBotScores() )
				   ->setIP( $this->getIP() )
				   ->total() >
			   apply_filters( 'shield/high_reputation_ip_minimum', self::con()->opts->optGet( 'antibot_high_reputation_minimum' ) );
	}
}