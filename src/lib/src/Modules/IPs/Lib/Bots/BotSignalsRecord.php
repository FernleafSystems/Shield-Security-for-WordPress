<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops as UserMetaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	DB\BotSignal,
	DB\BotSignal\BotSignalRecord,
	DB\BotSignal\LoadBotSignalRecords,
	DB\IpRules\IpRuleRecord,
	Lib\IpRules\IpRuleStatus,
	ModCon
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsRecord {

	use ModConsumer;
	use IpAddressConsumer;

	public function delete() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$thisReq = $this->getCon()->this_req;
		/** @var BotSignal\Ops\Select $select */
		$select = $mod->getDbH_BotSignal()->getQueryDeleter();

		if ( $thisReq->ip === $this->getIP() ) {
			unset( $thisReq->botsignal_record );
		}

		try {
			return $select->filterByIP(
				( new IPs\IPRecords() )
					->setMod( $this->getCon()->getModule_Data() )
					->loadIP( $this->getIP() )->id
			)->query();
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	public function retrieveNotBotAt() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT `bs`.`notbot_at`
						FROM `%s` as `bs`
						INNER JOIN `%s` as `ips`
							ON `ips`.id = `bs`.`ip_ref` 
							AND `ips`.`ip`=INET6_ATON('%s')
						ORDER BY `bs`.`updated_at` DESC
						LIMIT 1;",
				$mod->getDbH_BotSignal()->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				$this->getIP()
			)
		);
	}

	public function retrieve( bool $createNew = true ) :BotSignalRecord {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$thisReq = $this->getCon()->this_req;

		if ( $thisReq->ip === $this->getIP() && !empty( $thisReq->botsignal_record ) ) {
			return $thisReq->botsignal_record;
		}

		$r = $this->dbLoad();
		if ( empty( $r ) ) {
			if ( $createNew ) {
				$r = new BotSignalRecord();
				try {
					$r->ip_ref = ( new IPs\IPRecords() )
						->setMod( $this->getCon()->getModule_Data() )
						->loadIP( $this->getIP() )->id;
				}
				catch ( \Exception $e ) {
					$r->ip_ref = -1;
				}
			}
			else {
				throw new \Exception( 'No BotSignals record exists' );
			}
		}

		$ruleStatus = ( new IpRuleStatus( $this->getIP() ) )->setMod( $this->getMod() );
		if ( $ruleStatus->hasRules() ) {
			if ( $r->bypass_at === 0 && $ruleStatus->isBypass() ) {
				/** @var IpRuleRecord $ruleRecord */
				$ruleRecord = current( $ruleStatus->getRulesForBypass() );
				$r->bypass_at = $ruleRecord->created_at;
			}
			if ( $r->offense_at === 0 && $ruleStatus->isAutoBlacklisted() ) {
				$r->offense_at = $ruleStatus->getRuleForAutoBlock()->last_access_at;
			}

			/** @var IpRuleRecord $blockedRuleRecord */
			$blockedRuleRecord = current( $ruleStatus->getRulesForBlock() );
			if ( !empty( $blockedRuleRecord ) ) {
				$r->blocked_at = $blockedRuleRecord->blocked_at;
				$r->unblocked_at = $blockedRuleRecord->unblocked_at;
			}
		}

		if ( $r->notbot_at === 0 && $thisReq->ip === $this->getIP() ) {
			$r->notbot_at = $mod->getBotSignalsController()
								->getHandlerNotBot()
								->hasCookie() ? Services::Request()->ts() : 0;
		}

		if ( $r->auth_at === 0 && $r->ip_ref >= 0 ) {
			/** @var UserMetaDB\Select $userMetaSelect */
			$userMetaSelect = $this->getCon()->getModule_Data()->getDbH_UserMeta()->getQuerySelector();
			/** @var UserMetaDB\Record $lastUserMetaLogin */
			$lastUserMetaLogin = $userMetaSelect->filterByIPRef( $r->ip_ref )
												->setColumnsToSelect( [ 'last_login_at' ] )
												->setOrderBy( 'last_login_at' )
												->first();
			if ( !empty( $lastUserMetaLogin ) ) {
				$r->auth_at = $lastUserMetaLogin->last_login_at;
			}
		}

		$this->store( $r );

		return $r;
	}

	/**
	 * @return BotSignal\BotSignalRecord|null
	 */
	private function dbLoad() {
		try {
			$record = ( new LoadBotSignalRecords() )
				->setMod( $this->getMod() )
				->setIP( $this->getIP() )
				->loadRecord();
		}
		catch ( \Exception $e ) {
			$record = null;
		}

		return $record;
	}

	public function store( BotSignalRecord $record ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !isset( $record->id ) ) {
			if ( $record->ip_ref == -1 ) {
				unset( $record->ip_ref );
			}
			$success = $mod->getDbH_BotSignal()
						   ->getQueryInserter()
						   ->insert( $record );
		}
		else {
			$data = $record->getRawData();
			$data[ 'updated_at' ] = Services::Request()->ts();
			$success = $mod->getDbH_BotSignal()
						   ->getQueryUpdater()
						   ->updateById( $record->id, $data );
		}

		$thisReq = $this->getCon()->this_req;
		if ( $thisReq->ip === $record->ip ) {
			$thisReq->botsignal_record = $record;
		}

		return $success;
	}

	/**
	 * @param int|null $ts
	 * @throws \LogicException
	 */
	public function updateSignalField( string $field, $ts = null ) :BotSignalRecord {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !$mod->getDbH_BotSignal()->getTableSchema()->hasColumn( $field ) ) {
			throw new \LogicException( sprintf( '"%s" is not a valid column on Bot Signals', $field ) );
		}

		$record = $this->retrieve(); // false as we're going to store it anyway
		$record->{$field} = is_null( $ts ) ? Services::Request()->ts() : $ts;

		$this->store( $record );

		return $record;
	}

	/**
	 * @throws \Exception
	 * @deprecated 16.0
	 */
	private function getIPRecord() :IPs\Ops\Record {
		return ( new IPs\IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $this->getIP(), false );
	}
}