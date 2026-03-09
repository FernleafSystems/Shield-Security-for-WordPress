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
			   self::con()->comps->opts_lookup->getIpHighReputationMinimum();
	}
}
