<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\{
	BotSignalRecord,
	LoadBotSignalRecords,
	Ops as BotSignalDB
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRuleRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\Ops as UserMetaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Components\IpAddressConsumer,
	Lib\IpRules\IpRuleStatus,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsRecord {

	use IpAddressConsumer;
	use PluginControllerConsumer;

	public function delete() :bool {
		$thisReq = self::con()->this_req;
		/** @var BotSignalDB\Delete $deleter */
		$deleter = self::con()->db_con->bot_signals->getQueryDeleter();

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
				self::con()->db_con->bot_signals->getTable(),
				self::con()->db_con->ips->getTable(),
				$this->getIP()
			)
		);
	}

	/**
	 * @throws \Exception
	 */
	public function retrieve() :BotSignalRecord {
		$thisReq = self::con()->this_req;

		if ( $thisReq->ip === $this->getIP() && !empty( $thisReq->botsignal_record ) ) {
			return $thisReq->botsignal_record;
		}

		try {
			$r = ( new LoadBotSignalRecords() )
				->setIP( $this->getIP() )
				->loadRecord();
			$r->modified = false;
		}
		catch ( \Exception $e ) {
			$r = null;
		}

		if ( empty( $r ) ) {
			$r = new BotSignalRecord();
			$r->modified = true;
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

		if ( $r->auth_at === 0 && $r->ip_ref >= 0 ) {
			/** @var UserMetaDB\Select $userMetaSelect */
			$userMetaSelect = self::con()->db_con->user_meta->getQuerySelector();
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
		foreach ( self::con()->db_con->bot_signals->getTableSchema()->getColumnNames() as $col ) {
			if ( \preg_match( '#_at$#i', $col )
				 && !\in_array( $col, [ 'created_at', 'updated_at', 'deleted_at' ] )
				 && Services::Request()->carbon()->subMonth()->timestamp > $r->{$col} ) {
				$r->{$col} = 0;
			}
		}

		$this->store( $r );

		return $r;
	}

	/**
	 * @throws \Exception
	 */
	public function store( BotSignalRecord $record ) :bool {

		if ( !isset( $record->id ) ) {
			$record->ip_ref = ( new IPRecords() )->loadIP( $this->getIP() )->id;
			$success = self::con()
				->db_con
				->bot_signals
				->getQueryInserter()
				->insert( $record );
		}
		elseif ( $record->modified ) {
			$data = $record->getRawData();
			$data[ 'updated_at' ] = Services::Request()->ts();
			$success = self::con()
				->db_con
				->bot_signals
				->getQueryUpdater()
				->updateById( $record->id, $data );
		}
		else {
			$success = true;
		}

		$thisReq = self::con()->this_req;
		if ( $thisReq->ip === $record->ip ) {
			$thisReq->botsignal_record = $record;
		}

		return $success;
	}

	/**
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function updateSignalFields( array $fields, ?int $ts = null ) :BotSignalRecord {

		foreach ( $fields as $field ) {
			if ( !self::con()->db_con->bot_signals->getTableSchema()->hasColumn( $field ) ) {
				throw new \LogicException( sprintf( '"%s" is not a valid column on Bot Signals', $field ) );
			}
		}

		if ( $ts === null ) {
			$ts = Services::Request()->ts();
		}
		$record = $this->retrieve(); // false as we're going to store it anyway
		foreach ( $fields as $field ) {
			$record->{$field} = $ts;
		}

		$this->store( $record );

		return $record;
	}

	/**
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function updateSignalField( string $field, ?int $ts = null ) :BotSignalRecord {
		return $this->updateSignalFields( [ $field ], $ts );
	}
}