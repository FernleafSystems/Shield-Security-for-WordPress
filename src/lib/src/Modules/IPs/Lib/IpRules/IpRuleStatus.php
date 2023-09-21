<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\{
	IpRuleRecord,
	LoadIpRules,
	MergeAutoBlockRules,
	Ops\Handler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IsHighReputationIP;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use IPLib\Factory;

class IpRuleStatus {

	use ModConsumer;

	private $ipOrRange;

	/**
	 * @var IpRuleRecord[][]
	 */
	private static $cache = [];

	/**
	 * @var IpRuleRecord[]
	 */
	private static $ranges = null;

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
		return $this->getRules( [ Handler::T_CROWDSEC ] );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function getRulesForBypass() :array {
		return $this->purgeDuplicateRulesForWhiteAndBlack( $this->getRules( [ Handler::T_MANUAL_BYPASS ] ) );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function getRulesForAutoBlock() :array {
		$rules = $this->getRules( [ Handler::T_AUTO_BLOCK ] );

		if ( \count( $rules ) === 1 ) {
			$record = \current( $rules );
			if ( $record->last_access_at < ( Services::Request()->ts() - $this->opts()->getAutoExpireTime() ) ) {
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

		$rules = $this->getRules( [ Handler::T_AUTO_BLOCK ] );

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
					$rules = $this->getRules( [ Handler::T_AUTO_BLOCK ] );
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
		return $this->purgeDuplicateRulesForWhiteAndBlack( $this->getRules( [ Handler::T_MANUAL_BLOCK ] ) );
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

	public function hasHighReputation() :bool {
		return $this->hasAutoBlock()
			   && ( new IsHighReputationIP() )
				   ->setIP( $this->getRuleForAutoBlock()->ip )
				   ->query();
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

		if ( $this->mod()->getDbH_IPRules()->isReady() ) {

			$loader = new LoadIpRules();

			$cachedRanges = IpRulesCache::Get( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
			if ( self::$ranges === null && \is_array( $cachedRanges ) ) {
				self::$ranges = \array_map( function ( array $record ) {
					return ( new IpRuleRecord() )->applyFromArray( $record );
				}, $cachedRanges );
			}

			if ( self::$ranges === null ) {
				self::$ranges = [];
				$buildRanges = true;

				$loader->wheres = [
					sprintf( '(%s) OR (%s)',
						sprintf( "`ips`.ip=INET6_ATON('%s') AND `ir`.`is_range`='0'", $this->getIP() ),
						"`ir`.`is_range`='1'"
					)
				];
			}
			else {
				$buildRanges = false;
				$loader->wheres = [
					sprintf( "`ips`.ip=INET6_ATON('%s') AND `ir`.`is_range`='0'", $this->getIP() )
				];
			}

			foreach ( $buildRanges ? $loader->select() : \array_merge( $loader->select(), self::$ranges ) as $record ) {
				if ( $record->is_range ) {
					$maybeParsed = Factory::parseRangeString( $record->ipAsSubnetRange( true ) );
					if ( !empty( $maybeParsed ) ) {
						if ( $buildRanges ) {
							self::$ranges[] = $record;
						}
						if ( $maybeParsed->containsRange( $parsedIP ) ) {
							$records[] = $record;
						}
					}
				}
				else {
					$records[] = $record;
				}
			}

			if ( $buildRanges && \count( self::$ranges ) < 30 ) {
				IpRulesCache::Add( IpRulesCache::COLLECTION_RANGES, \array_map( function ( IpRuleRecord $record ) {
					return $record->getRawData();
				}, self::$ranges ), IpRulesCache::GROUP_COLLECTIONS );
			}

			if ( \count( $records ) === 0 ) {
				IpRulesCache::Add( $this->getIP(), $this->getIP(), IpRulesCache::GROUP_NO_RULES );
			}
		}

		return $records;
	}
}