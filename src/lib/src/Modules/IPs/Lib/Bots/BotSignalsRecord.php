<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops as UserMetaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Components\IpAddressConsumer,
	DB\BotSignal,
	DB\BotSignal\BotSignalRecord,
	DB\BotSignal\LoadBotSignalRecords,
	DB\IpRules\IpRuleRecord,
	Lib\IpRules\IpRuleStatus,
	ModConsumer
};
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsRecord {

	use ModConsumer;
	use IpAddressConsumer;

	public function delete() :bool {
		$thisReq = self::con()->this_req;
		/** @var BotSignal\Ops\Delete $deleter */
		$deleter = $this->mod()->getDbH_BotSignal()->getQueryDeleter();

		if ( $thisReq->ip === $this->getIP() ) {
			unset( $thisReq->botsignal_record );
		}

		try {
			return $deleter->filterByIP( ( new IPRecords() )->loadIP( $this->getIP() )->id )->query();
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	public function retrieveNotBotAt() :int {
		return (int)Services::WpDb()->getVar(
			\sprintf( "SELECT `bs`.`notbot_at`
						FROM `%s` as `bs`
						INNER JOIN `%s` as `ips`
							ON `ips`.id = `bs`.`ip_ref` 
							AND `ips`.`ip`=INET6_ATON('%s')
						ORDER BY `bs`.`updated_at` DESC
						LIMIT 1;",
				$this->mod()->getDbH_BotSignal()->getTableSchema()->table,
				self::con()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				$this->getIP()
			)
		);
	}

	public function retrieve( bool $createNew = true ) :BotSignalRecord {
		$thisReq = self::con()->this_req;

		if ( $thisReq->ip === $this->getIP() && !empty( $thisReq->botsignal_record ) ) {
			return $thisReq->botsignal_record;
		}

		try {
			$r = ( new LoadBotSignalRecords() )
				->setIP( $this->getIP() )
				->loadRecord();
		}
		catch ( \Exception $e ) {
			$r = null;
		}

		if ( empty( $r ) ) {
			if ( $createNew ) {
				$r = new BotSignalRecord();
				try {
					$r->ip_ref = ( new IPRecords() )->loadIP( $this->getIP() )->id;
				}
				catch ( \Exception $e ) {
					$r->ip_ref = -1;
				}
			}
			else {
				throw new \Exception( 'No BotSignals record exists' );
			}
		}

		$ruleStatus = new IpRuleStatus( $this->getIP() );
		if ( $ruleStatus->hasRules() ) {
			if ( $r->bypass_at === 0 && $ruleStatus->isBypass() ) {
				/** @var IpRuleRecord $ruleRecord */
				$ruleRecord = \current( $ruleStatus->getRulesForBypass() );
				$r->bypass_at = $ruleRecord->created_at;
			}
			if ( $r->offense_at === 0 && $ruleStatus->isAutoBlacklisted() ) {
				$r->offense_at = $ruleStatus->getRuleForAutoBlock()->last_access_at;
			}

			/** @var IpRuleRecord $blockedRuleRecord */
			$blockedRuleRecord = \current( $ruleStatus->getRulesForBlock() );
			if ( !empty( $blockedRuleRecord ) ) {
				$r->blocked_at = $blockedRuleRecord->blocked_at;
				$r->unblocked_at = $blockedRuleRecord->unblocked_at;
			}
		}

		if ( $r->notbot_at === 0 && $thisReq->ip === $this->getIP() ) {
			$r->notbot_at = $this->mod()
								 ->getBotSignalsController()
								 ->getHandlerNotBot()
								 ->hasCookie() ? Services::Request()->ts() : 0;
		}

		if ( $r->auth_at === 0 && $r->ip_ref >= 0 ) {
			/** @var UserMetaDB\Select $userMetaSelect */
			$userMetaSelect = self::con()->getModule_Data()->getDbH_UserMeta()->getQuerySelector();
			/** @var UserMetaDB\Record $lastUserMetaLogin */
			$lastUserMetaLogin = $userMetaSelect->filterByIPRef( $r->ip_ref )
												->setColumnsToSelect( [ 'last_login_at' ] )
												->setOrderBy( 'last_login_at' )
												->first();
			if ( !empty( $lastUserMetaLogin ) ) {
				$r->auth_at = $lastUserMetaLogin->last_login_at;
			}
		}

		/** Clean out old signals that have no bearing on bot calculations */
		foreach ( $this->mod()->getDbH_BotSignal()->getTableSchema()->getColumnNames() as $col ) {
			if ( \preg_match( '#_at$#i', $col )
				 && !\in_array( $col, [ 'created_at', 'updated_at', 'deleted_at' ] )
				 && Services::Request()->carbon()->subMonth()->timestamp > $r->{$col} ) {
				$r->{$col} = 0;
			}
		}

		$this->store( $r );

		return $r;
	}

	public function store( BotSignalRecord $record ) :bool {

		if ( !isset( $record->id ) ) {
			if ( $record->ip_ref == -1 ) {
				unset( $record->ip_ref );
			}
			$success = $this->mod()
							->getDbH_BotSignal()
							->getQueryInserter()
							->insert( $record );
		}
		else {
			$data = $record->getRawData();
			$data[ 'updated_at' ] = Services::Request()->ts();
			$success = $this->mod()
							->getDbH_BotSignal()
							->getQueryUpdater()
							->updateById( $record->id, $data );
		}

		$thisReq = self::con()->this_req;
		if ( $thisReq->ip === $record->ip ) {
			$thisReq->botsignal_record = $record;
		}

		return $success;
	}

	/**
	 * @throws \LogicException
	 */
	public function updateSignalField( string $field, ?int $ts = null ) :BotSignalRecord {

		if ( !$this->mod()->getDbH_BotSignal()->getTableSchema()->hasColumn( $field ) ) {
			throw new \LogicException( sprintf( '"%s" is not a valid column on Bot Signals', $field ) );
		}

		$record = $this->retrieve(); // false as we're going to store it anyway
		$record->{$field} = is_null( $ts ) ? Services::Request()->ts() : $ts;

		$this->store( $record );

		return $record;
	}
}