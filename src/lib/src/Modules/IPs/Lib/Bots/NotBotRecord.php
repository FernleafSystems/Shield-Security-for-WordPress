<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class NotBotRecord {

	use ModConsumer;
	use IpAddressConsumer;

	/**
	 * @return EntryVO
	 * @throws \Exception
	 */
	public function retrieve() :EntryVO {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbHandler_BotSignals()->getQuerySelector();
		$entry = $select->filterByIPHuman( $this->getIP() )->first();
		if ( !$entry instanceof EntryVO ) {
			throw new \Exception( 'IP not registered' );
		}
		return $entry;
	}

	public function delete() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbHandler_BotSignals()->getQueryDeleter();
		return $select->filterByIPHuman( $this->getIP() )->query();
	}
}