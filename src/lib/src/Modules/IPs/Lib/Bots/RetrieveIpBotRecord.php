<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RetrieveIpBotRecord {

	use ModConsumer;

	/**
	 * @return EntryVO
	 * @throws \Exception
	 */
	public function current() :EntryVO {
		return $this->forIP( Services::IP()->getRequestIp() );
	}

	/**
	 * @param string $ip
	 * @return EntryVO
	 * @throws \Exception
	 */
	public function forIP( string $ip ) :EntryVO {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbHandler_BotSignals()->getQuerySelector();
		$entry = $select->filterByIPHuman( $ip )->first();
		if ( !$entry instanceof EntryVO ) {
			throw new \Exception( 'IP not registered' );
		}
		return $entry;
	}
}