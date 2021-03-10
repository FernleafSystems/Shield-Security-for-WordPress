<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UpdateBotField {

	use ModConsumer;
	use IpAddressConsumer;

	public function run( string $field, $ts = null ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$notBotRecord = $this->getVisitorEntry();
		$ts = is_null( $ts ) ? Services::Request()->ts() : $ts;

		if ( empty( $notBotRecord->id ) ) {
			$notBotRecord->{$field} = $ts;
			$mod->getDbHandler_BotSignals()
				->getQueryInserter()
				->insert( $notBotRecord );
		}
		else {
			$mod->getDbHandler_BotSignals()
				->getQueryUpdater()
				->updateEntry( $notBotRecord, [ $field => $ts ] );
		}
	}

	private function getVisitorEntry() :EntryVO {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		try {
			$entry = ( new NotBotRecord() )
				->setMod( $this->getMod() )
				->setIP( $this->getIP() )
				->retrieve();
		}
		catch ( \Exception $e ) {
			$entry = new EntryVO();
			$entry->ip = $this->getIP();

			$ipOnList = ( new LookupIpOnList() )
				->setDbHandler( $mod->getDbHandler_IPs() )
				->setIP( $entry->ip )
				->lookupIp();

			if ( !empty( $ipOnList ) ) {
				$entry->bypass_at = $ipOnList->list === $mod::LIST_MANUAL_WHITE ? Services::Request()->ts() : 0;
				$entry->blocked_at = $ipOnList->blocked_at;
				$entry->offense_at = $ipOnList->list === $mod::LIST_AUTO_BLACK ? Services::Request()->ts() : 0;
			}
		}
		return $entry;
	}
}