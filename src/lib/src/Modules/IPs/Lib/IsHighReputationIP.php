<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Components\IpAddressConsumer,
	ModConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;

class IsHighReputationIP {

	use ModConsumer;
	use IpAddressConsumer;

	public function query() :bool {
		return ( new CalculateVisitorBotScores() )
				   ->setIP( $this->getIP() )
				   ->total() >
			   apply_filters( 'shield/high_reputation_ip_minimum', $this->opts()->getAntiBotHighReputationMinimum() );
	}
}