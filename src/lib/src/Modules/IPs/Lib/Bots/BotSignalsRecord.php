<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops\Retrieve;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsRecord {

	use ModConsumer;
	use IpAddressConsumer;

	public function delete() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbHandler_BotSignals()->getQueryDeleter();
		return $select->filterByIPHuman( $this->getIP() )->query();
	}

	public function retrieve( bool $storeOnLoad = true ) :EntryVO {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbHandler_BotSignals()->getQuerySelector();
		$e = $select->filterByIPHuman( $this->getIP() )->first();
		if ( !$e instanceof EntryVO ) {
			$e = new EntryVO();
			$e->ip = $this->getIP();
		}

		$ipOnList = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setIP( $e->ip )
			->lookupIp();

		if ( !empty( $ipOnList ) ) {
			if ( empty( $e->bypass_at ) && $ipOnList->list === $mod::LIST_MANUAL_WHITE ) {
				$e->bypass_at = $ipOnList->created_at;
			}
			if ( empty( $e->offense_at ) && $ipOnList->list === $mod::LIST_AUTO_BLACK ) {
				$e->offense_at = $ipOnList->last_access_at;
			}
			$e->blocked_at = $ipOnList->blocked_at;
		}

		if ( empty( $e->notbot_at ) && Services::IP()->getRequestIp() === $this->getIP() ) {
			$e->notbot_at = $mod->getBotSignalsController()
								->getHandlerNotBot()
								->hasCookie() ? Services::Request()->ts() : 0;
		}

		if ( empty( $e->auth_at ) ) {
			$dbhSessions = $this->getCon()
								->getModule_Sessions()
								->getDbHandler_Sessions();
			/** @var Session\Select $selector */
			$selector = $dbhSessions->getQuerySelector();
			$session = $selector->setIncludeSoftDeleted( true )
								->filterByIp( $this->getIP() )
								->first();
			$e->auth_at = empty( $session ) ? 0 : $session->created_at;
		}

		if ( $storeOnLoad ) {
			$this->store( $e );
		}

		return $e;
	}

	public function store( EntryVO $entry ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( empty( $entry->id ) ) {
			$success = $mod->getDbHandler_BotSignals()
						   ->getQueryInserter()
						   ->insert( $entry );
		}
		else {
			$data = $entry->getRawData();
			$data[ 'updated_at' ] = Services::Request()->ts();
			$success = $mod->getDbHandler_BotSignals()
						   ->getQueryUpdater()
						   ->updateById( $entry->id, $data );
		}
		return $success;
	}

	/**
	 * @param string   $field
	 * @param int|null $ts
	 * @return EntryVO
	 * @throws \LogicException
	 */
	public function updateSignalField( string $field, $ts = null ) :EntryVO {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !$mod->getDbHandler_BotSignals()->getTableSchema()->hasColumn( $field ) ) {
			throw new \LogicException( sprintf( '"%s" is not a valid column on Bot Signals', $field ) );
		}

		$entry = $this->retrieve( false ); // false as we're going to store it anyway
		$entry->{$field} = is_null( $ts ) ? Services::Request()->ts() : $ts;

		$this->store( $entry );

		return $entry;
	}
}