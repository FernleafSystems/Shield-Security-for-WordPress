<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Components\IpAddressConsumer,
	ModConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;

class CalculateVisitorBotScores {

	use IpAddressConsumer;
	use ModConsumer;

	private $scores = [];

	public function scores() :array {
		$this->scores = ( new BuildScores() )
			->setRecord( $this->loadRecord() )
			->build();
		return $this->getActiveScores();
	}

	public function total() :int {
		return (int)\array_sum( $this->scores() );
	}

	public function probability() :int {
		return (int)\max( 0, \min( 100, $this->total() ) );
	}

	private function getActiveScores() :array {
		return \array_filter(
			$this->scores,
			function ( $score ) {
				return $score !== -1;
			}
		);
	}

	private function loadRecord() :BotSignalRecord {
		$ip = $this->getIP();
		if ( empty( $ip ) ) {
			$ip = self::con()->this_req->ip;
		}

		try {
			$entry = ( new BotSignalsRecord() )
				->setIP( $ip )
				->retrieve();
		}
		catch ( \Exception $e ) {
			$entry = new BotSignalRecord();
			$entry->ip = $ip;
		}

		return $entry;
	}
}