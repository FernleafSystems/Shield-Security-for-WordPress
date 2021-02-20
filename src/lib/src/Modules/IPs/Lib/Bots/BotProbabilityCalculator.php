<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BotProbabilityCalculator {

	use IpAddressConsumer;
	use ModConsumer;

	public function calculate() :int {
		$isBotScore = 0;
		$entry = $this->getEntry();
		$now = Services::Request()->ts();

		if ( empty( $entry->notbot_at ) ) {
			$isBotScore = 100;
		}
		elseif ( $now - $entry->notbot_at > HOUR_IN_SECONDS ) {
			$isBotScore = 50;
		}

		return $isBotScore;
	}

	private function getEntry() :EntryVO {
		$ip = $this->getIP();
		if ( empty( $ip ) ) {
			$ip = Services::IP()->getRequestIp();
		}
		try {
			$ip = $this->getIP();
			$entry = ( new RetrieveIpBotRecord() )
				->setMod( $this->getMod() )
				->forIP( $ip );
		}
		catch ( \Exception $e ) {
			$entry = new EntryVO();
			$entry->ip = $ip;
		}
		return $entry;
	}
}