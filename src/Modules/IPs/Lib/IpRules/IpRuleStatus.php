<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRuleRecord,
	LoadIpRules,
	MergeAutoBlockRules,
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\IpAddressSql;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use IPLib\Factory;
use IPLib\Address\AddressInterface;
use IPLib\Range\RangeInterface;

class IpRuleStatus {

	use PluginControllerConsumer;

	private string $ipOrRange;

	/**
	 * @var array<string, IpRuleRecord[]>
	 */
	private static array $cache = [];

	/**
	 * @var ?IpRuleRecord[]
	 */
	private static ?array $ranges = null;

	/**
	 * @var ?array<int, array{record: IpRuleRecord, range: RangeInterface}>
	 */
	private static ?array $rangeMatchers = null;

	private ?AddressInterface $parsedAddress = null;
	private bool $parsedAddressLoaded = false;
	private ?RangeInterface $parsedRange = null;
	private bool $parsedRangeLoaded = false;

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
				self::$cache[ $ip ] = $this->isSingleAddressLookup() && IpRulesCache::Has( $this->getIP(), IpRulesCache::GROUP_NO_RULES )
					? []
					: $this->loadRecordsForIP();
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
		$parsedRange = $this->getParsedRange();
		if ( !$parsedRange instanceof RangeInterface ) {
			throw new \Exception( 'Not a valid IP Address or Range' );
		}

		if ( !self::con()->db_con->ip_rules->isReady() ) {
			return [];
		}

		$parsedAddress = $this->getParsedAddress();

		return ( $parsedRange->getSize() === 1 && $parsedAddress instanceof AddressInterface )
			? $this->loadRecordsForSingleAddress( $parsedAddress )
			: $this->loadRecordsForGenericInput();
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function loadRecordsForSingleAddress( AddressInterface $address ) :array {
		$records = $this->loadExactNonRangeRules();

		foreach ( $this->getRangeMatchers() as $matcher ) {
			if ( $address->matches( $matcher[ 'range' ] ) ) {
				$records[] = $matcher[ 'record' ];
			}
		}

		if ( \count( $records ) === 0 ) {
			IpRulesCache::Add( $this->getIP(), $this->getIP(), IpRulesCache::GROUP_NO_RULES );
		}

		return $records;
	}

	/**
	 * Preserve the current broad containment semantics for non-single inputs such as CLI/admin range lookups.
	 * @return IpRuleRecord[]
	 */
	private function loadRecordsForGenericInput() :array {
		$records = [];

		foreach ( \array_merge( $this->getRangeRecords(), $this->loadExactNonRangeRules() ) as $record ) {
			if ( Services::IP()->IpIn( $this->getIP(), [ $record->ipAsSubnetRange( true ) ] ) ) {
				$records[] = $record;
			}
		}

		return $records;
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function loadExactNonRangeRules() :array {
		$loader = new LoadIpRules();
		$loader->wheres = [
			sprintf( '%s AND `ir`.`is_range`=0', IpAddressSql::equality( '`ips`.`ip`', $this->getIP() ) )
		];

		return \array_values( $loader->select() );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function getRangeRecords() :array {
		if ( self::$ranges === null ) {

			$cachedRanges = IpRulesCache::Get( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
			if ( \is_array( $cachedRanges ) ) {
				self::$ranges = \array_map( fn( array $r ) => ( new IpRuleRecord() )->applyFromArray( $r ), $cachedRanges );
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

	/**
	 * @return array<int, array{record: IpRuleRecord, range: RangeInterface}>
	 */
	private function getRangeMatchers() :array {
		if ( self::$rangeMatchers === null ) {
			self::$rangeMatchers = [];

			foreach ( $this->getRangeRecords() as $record ) {
				$parsedRange = Factory::parseRangeString( $record->ipAsSubnetRange( true ) );
				if ( $parsedRange instanceof RangeInterface ) {
					self::$rangeMatchers[] = [
						'record' => $record,
						'range'  => $parsedRange,
					];
				}
			}
		}

		return self::$rangeMatchers;
	}

	private function isSingleAddressLookup() :bool {
		$parsedRange = $this->getParsedRange();
		return $parsedRange instanceof RangeInterface
			   && $parsedRange->getSize() === 1
			   && $this->getParsedAddress() instanceof AddressInterface;
	}

	private function getParsedAddress() :?AddressInterface {
		if ( !$this->parsedAddressLoaded ) {
			$this->parsedAddress = Factory::parseAddressString( $this->getIP() );
			$this->parsedAddressLoaded = true;
		}
		return $this->parsedAddress;
	}

	private function getParsedRange() :?RangeInterface {
		if ( !$this->parsedRangeLoaded ) {
			$this->parsedRange = Factory::parseRangeString( $this->getIP() );
			$this->parsedRangeLoaded = true;
		}
		return $this->parsedRange;
	}
}
