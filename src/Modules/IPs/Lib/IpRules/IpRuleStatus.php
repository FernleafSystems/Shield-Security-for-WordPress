<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRuleRecord,
	LoadIpRules,
	MergeAutoBlockRules,
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use IPLib\Factory;

class IpRuleStatus {

	use PluginControllerConsumer;

	private $ipOrRange;

	/**
	 * @var IpRuleRecord[][]
	 */
	private static $cache = [];

	/**
	 * @var IpRuleRecord[]
	 */
	private static $ranges = null;

	/**
	 * @var IpRuleRecord[]
	 */
	private static $bypass = null;

	public function __construct( string $ipOrRange ) {
		$this->ipOrRange = $ipOrRange;
	}

	public function getBlockType() :string {
		return $this->isBlocked() ? \current( $this->getRulesForBlock() )->type : '';
	}

	public function getOffenses() :int {
		$rule = $this->getRuleForAutoBlock();
		return empty( $rule ) ? 0 : $rule->offenses;
	}

	public function getRuleForAutoBlock() :?IpRuleRecord {
		$record = \current( $this->getRulesForAutoBlock() );
		return $record instanceof IpRuleRecord ? $record : null;
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getRules( array $filterByLists = [] ) :array {
		$ip = $this->getIP();
		if ( !isset( self::$cache[ $ip ] ) ) {
			try {
				self::$cache[ $ip ] = IpRulesCache::Has( $this->getIP(), IpRulesCache::GROUP_NO_RULES ) ? [] : $this->loadRecordsForIP();
			}
			catch ( \Exception $e ) {
				self::$cache[ $ip ] = [];
			}
		}
		return \array_filter(
			self::$cache[ $ip ],
			function ( $rule ) use ( $filterByLists ) {
				return empty( $filterByLists ) || \in_array( $rule->type, $filterByLists );
			}
		);
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getRulesForCrowdsec() :array {
		return $this->getRules( [ IpRulesDB\Handler::T_CROWDSEC ] );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getRulesForBypass() :array {
		return $this->purgeDuplicateRulesForWhiteAndBlack( $this->getRules( [ IpRulesDB\Handler::T_MANUAL_BYPASS ] ) );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function getRulesForAutoBlock() :array {
		$rules = $this->getRules( [ IpRulesDB\Handler::T_AUTO_BLOCK ] );

		if ( \count( $rules ) === 1 ) {
			$record = \current( $rules );
			if ( $record->last_access_at < ( Services::Request()
													 ->ts() - self::con()->comps->opts_lookup->getIpAutoBlockTTL() ) ) {
				self::ClearStatusForIP( $this->getIP() );
			}
		}
		elseif ( \count( $rules ) > 1 ) {
			// Should only have 1 auto-block rule. So we merge & delete all the older rules.
			try {
				( new MergeAutoBlockRules() )->byRecords( $rules );
			}
			catch ( \Exception $e ) {
			}
			self::ClearStatusForIP( $this->getIP() );
		}

		$rules = $this->getRules( [ IpRulesDB\Handler::T_AUTO_BLOCK ] );

		// Just in case we've previously blocked a Search Provider - perhaps a failed rDNS at the time.
		if ( !empty( $rules ) ) {
			try {
				[ $ipKey, ] = ( new IpID( $this->getIP() ) )
					->setIgnoreUserAgent()
					->run();
				if ( \in_array( $ipKey, Services::ServiceProviders()->getSearchProviders() ) ) {
					foreach ( $rules as $rule ) {
						( new DeleteRule() )->byRecord( $rule );
					}
					self::ClearStatusForIP( $this->getIP() );
					$rules = $this->getRules( [ IpRulesDB\Handler::T_AUTO_BLOCK ] );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		return $rules;
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getRulesForManualBlock() :array {
		return $this->purgeDuplicateRulesForWhiteAndBlack( $this->getRules( [ IpRulesDB\Handler::T_MANUAL_BLOCK ] ) );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getRulesForShieldBlock() :array {
		return \array_merge( $this->getRulesForAutoBlock(), $this->getRulesForManualBlock() );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getRulesForBlock() :array {
		return \array_merge( $this->getRulesForManualBlock(), $this->getRulesForCrowdsec(), $this->getRulesForAutoBlock() );
	}

	public function isBypass() :bool {
		return !empty( $this->getRulesForBypass() );
	}

	public function hasCrowdsecBlock() :bool {
		$has = false;
		foreach ( $this->getRulesForCrowdsec() as $rule ) {
			$has = $rule->blocked_at > $rule->unblocked_at;
			if ( !$rule->is_range ) {
				break;
			}
		}
		return $has;
	}

	public function hasAutoBlock() :bool {
		$rule = $this->getRuleForAutoBlock();
		return !empty( $rule ) && $rule->blocked_at > 0 && ( $rule->blocked_at >= $rule->unblocked_at );
	}

	public function hasManualBlock() :bool {
		return !empty( $this->getRulesForManualBlock() );
	}

	public function hasRules() :bool {
		return !empty( $this->getRules() );
	}

	/**
	 * This includes IPs on the autoblock list, but that aren't fully blocked.
	 */
	public function isAutoBlacklisted() :bool {
		return !empty( $this->getRuleForAutoBlock() );
	}

	public function isBlockedByShield() :bool {
		return !$this->isBypass() && ( $this->hasManualBlock() || $this->hasAutoBlock() );
	}

	public function isBlockedByCrowdsec() :bool {
		return !$this->isBypass() && $this->hasCrowdsecBlock();
	}

	public function isBlocked() :bool {
		return $this->isBlockedByShield() || $this->isBlockedByCrowdsec();
	}

	public function isUnBlocked() :bool {
		$isUnblocked = false;
		if ( $this->isAutoBlacklisted() ) {
			$rule = $this->getRuleForAutoBlock();
			$isUnblocked = $rule->unblocked_at > $rule->blocked_at;
		}
		elseif ( $this->hasCrowdsecBlock() ) {
			foreach ( $this->getRulesForCrowdsec() as $rule ) {
				if ( !$rule->is_range && $rule->unblocked_at > $rule->blocked_at ) {
					$isUnblocked = true;
					break;
				}
			}
		}
		return $isUnblocked;
	}

	private function removeRecordFromCache( IpRuleRecord $recordToRemove ) {
		foreach ( self::$cache[ $this->getIP() ] as $key => $record ) {
			if ( $record->id == $recordToRemove->id ) {
				unset( self::$cache[ $this->getIP() ][ $key ] );
			}
		}
	}

	public static function ClearStatusForIP( string $ipOrRange ) {
		unset( self::$cache[ $ipOrRange ] );
	}

	private function getIP() :string {
		return $this->ipOrRange;
	}

	/**
	 * Deletes single records where there's also range that captures it.
	 * @param IpRuleRecord[] $rules
	 * @return IpRuleRecord[]
	 */
	private function purgeDuplicateRulesForWhiteAndBlack( array $rules ) :array {
		if ( \count( $rules ) > 1 ) {
			$toDelete = [];
			$ruleToKeep = null;
			$ruleToKeepKey = null;
			foreach ( $rules as $key => $record ) {
				if ( empty( $ruleToKeep ) ) {
					$ruleToKeep = $record;
					$ruleToKeepKey = $key;
				}
				elseif ( !$ruleToKeep->is_range && $record->is_range ) {

					$toDelete[ $ruleToKeepKey ] = $ruleToKeep;

					$ruleToKeep = $record;
					$ruleToKeepKey = $key;
				}
				elseif ( $ruleToKeep->is_range && !$record->is_range ) {
					$toDelete[ $key ] = $record;
				}
			}

			foreach ( $toDelete as $deleteKey => $deleteRecord ) {
				( new DeleteRule() )->byRecord( $deleteRecord );
				$this->removeRecordFromCache( $deleteRecord );
				unset( $rules[ $deleteKey ] );
			}
		}
		return $rules;
	}

	/**
	 * @throws \Exception
	 */
	private function loadRecordsForIP() :array {
		$parsedIP = Factory::parseRangeString( $this->getIP() );
		if ( empty( $parsedIP ) ) {
			throw new \Exception( 'Not a valid IP Address or Range' );
		}

		$records = [];

		if ( self::con()->db_con->ip_rules->isReady() ) {

			$loader = new LoadIpRules();
			$loader->wheres = [
				sprintf( "`ips`.`ip`=INET6_ATON('%s') AND `ir`.`is_range`='0'", $this->getIP() )
			];

			foreach ( \array_merge( $this->getRanges(), $this->getBypasses(), $loader->select() ) as $rec ) {
				if ( Services::IP()->IpIn( $this->getIP(), [ $rec->ipAsSubnetRange( true ) ] ) ) {
					$records[] = $rec;
				}
			}

			if ( \count( $records ) === 0 ) {
				IpRulesCache::Add( $this->getIP(), $this->getIP(), IpRulesCache::GROUP_NO_RULES );
			}
		}

		return $records;
	}

	private function getRanges() :array {
		if ( self::$ranges === null ) {

			$cachedRanges = IpRulesCache::Get( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
			if ( \is_array( $cachedRanges ) ) {
				self::$ranges = \array_map( function ( array $record ) {
					return ( new IpRuleRecord() )->applyFromArray( $record );
				}, $cachedRanges );
			}
			else {
				self::$ranges = [];

				$loader = new LoadIpRules();
				$loader->wheres = [ "`ir`.`is_range`='1'" ];
				foreach ( $loader->select() as $record ) {
					$maybeParsed = Factory::parseRangeString( $record->ipAsSubnetRange( true ) );
					if ( !empty( $maybeParsed ) ) {
						self::$ranges[] = $record;
					}
				}

				if ( \count( self::$ranges ) < 30 ) {
					IpRulesCache::Add(
						IpRulesCache::COLLECTION_RANGES,
						\array_map( function ( IpRuleRecord $record ) {
							return $record->getRawData();
						}, self::$ranges ),
						IpRulesCache::GROUP_COLLECTIONS
					);
				}
			}
		}
		return self::$ranges;
	}

	private function getBypasses() :array {
		if ( self::$bypass === null ) {

			$cachedBypasses = IpRulesCache::Get( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
			if ( \is_array( $cachedBypasses ) ) {
				self::$bypass = \array_map( function ( array $record ) {
					return ( new IpRuleRecord() )->applyFromArray( $record );
				}, $cachedBypasses );
			}
			else {
				self::$bypass = [];

				$loader = new LoadIpRules();
				$loader->wheres = [
					sprintf( "`ips`.`ip`=INET6_ATON('%s')", $this->getIP() ),
					sprintf( "`ir`.`type`='%s'", IpRulesDB\Handler::T_MANUAL_BYPASS ),
					"`ir`.`is_range`='0'",
				];
				self::$bypass = \array_values( $loader->select() );

				if ( \count( self::$bypass ) < 50 ) {
					IpRulesCache::Add(
						IpRulesCache::COLLECTION_BYPASS,
						\array_map( function ( IpRuleRecord $record ) {
							return $record->getRawData();
						}, self::$bypass ),
						IpRulesCache::GROUP_COLLECTIONS
					);
				}
			}
		}
		return self::$bypass;
	}
}