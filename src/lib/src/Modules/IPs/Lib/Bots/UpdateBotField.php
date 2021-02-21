<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UpdateBotField {

	use ModConsumer;
	use IpAddressConsumer;

	public function run( string $field, $ts = null ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$IP = $this->getVisitorEntry();
		$IP->{$field} = is_null( $ts ) ? Services::Request()->ts() : $ts;

		if ( empty( $IP->id ) ) {
			$mod->getDbHandler_BotSignals()
				->getQueryInserter()
				->insert( $IP );
		}
		else {
			$mod->getDbHandler_BotSignals()
				->getQueryUpdater()
				->updateEntry( $IP, $IP->getRawData() );
		}
	}

	private function getVisitorEntry() :EntryVO {
		try {
			$entry = ( new RetrieveIpBotRecord() )
				->setMod( $this->getMod() )
				->forIP( $this->getIP() );
		}
		catch ( \Exception $e ) {
			$entry = new EntryVO();
			$entry->ip = $this->getIP();
		}
		return $entry;
	}
}