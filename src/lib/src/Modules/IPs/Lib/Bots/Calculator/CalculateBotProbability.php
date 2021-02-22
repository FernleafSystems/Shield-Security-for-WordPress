<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\RetrieveIpBotRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CalculateBotProbability {

	use IpAddressConsumer;
	use ModConsumer;

	private $scores = [];

	public function scores() :array {
		$this->scores = ( new BuildScores() )
			->setEntryVO( $this->loadEntry() )
			->build();
		return $this->getActiveScores();
	}

	public function total() :int {
		return (int)array_sum( $this->scores() );
	}

	public function probability() :int {
		return (int)max( 0, min( 100, $this->total() ) );
	}

	private function getActiveScores() :array {
		return array_filter(
			$this->scores,
			function ( $score ) {
				return $score !== -1;
			}
		);
	}

	private function loadEntry() :EntryVO {
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