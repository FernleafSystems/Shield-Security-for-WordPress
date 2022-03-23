<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class IsHighReputationIP {

	use ModConsumer;
	use IPs\Components\IpAddressConsumer;

	public function query() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return ( new IPs\Lib\Bots\Calculator\CalculateVisitorBotScores() )
				   ->setMod( $this->getMod() )
				   ->setIP( $this->getIP() )
				   ->total() >
			   (int)apply_filters( 'shield/high_reputation_ip_minimum', $opts->getAntiBotHighReputationMinimum() );
	}
}