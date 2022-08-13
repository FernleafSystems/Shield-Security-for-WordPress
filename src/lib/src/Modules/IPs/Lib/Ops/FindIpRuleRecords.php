<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\{
	IpRuleRecord,
	Ops as IpRulesDB
};
use IPLib\Factory;

class FindIpRuleRecords {

	use ModConsumer;
	use IPs\Components\IpAddressConsumer;

	const MATCH_ALL = 0;
	const MATCH_RANGES = 1;
	const MATCH_SINGLES = 2;

	/**
	 * @var string[]
	 */
	private $listTypes = [];

	/**
	 * @var bool
	 */
	private $isBlocked;

	/**
	 * Finds the "first" entry - not an ideal approach, but the legacy approach to-date.
	 * @return IpRuleRecord|null
	 * @deprecated 16.0
	 */
	public function lookup( bool $includeRanges = true ) {
		return current( $includeRanges ? $this->all() : $this->all( self::MATCH_SINGLES ) );
	}

	/**
	 * @return IpRuleRecord|null
	 */
	public function firstAll() {
		return current( $this->all() );
	}

	/**
	 * @return IpRuleRecord|null
	 */
	public function firstSingle() {
		return current( $this->singles() );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function singles() :array {
		return $this->all( self::MATCH_SINGLES );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function ranges() :array {
		return $this->all( self::MATCH_RANGES );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	public function all( int $match = self::MATCH_ALL ) :array {
		switch ( $match ) {

			case self::MATCH_RANGES:
				$res = $this->searchRanges();
				break;

			case self::MATCH_SINGLES:
				$res = $this->searchSingles();
				break;

			case self::MATCH_ALL:
			default:
				$res = array_merge( $this->searchRanges(), $this->searchSingles() );
				break;
		}
		return $res;
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function searchRanges() :array {
		$records = [];

		$parsedIP = Factory::parseRangeString( $this->getIP() );
		foreach ( $this->selectRanges() as $maybe ) {
			$maybeParsed = Factory::parseRangeString( $maybe->ipAsSubnetRange( true ) );
			if ( !empty( $maybeParsed ) && $maybeParsed->containsRange( $parsedIP ) ) {
				$records[] = $maybe;
			}
		}

		return $records;
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function searchSingles() :array {
		$loader = ( new IPs\DB\IpRules\LoadIpRules() )
			->setMod( $this->getMod() )
			->setIP( $this->getIP() );

		$loader->limit = 1;
		$loader->wheres = array_merge( $this->getWheresFromParams(), [
			"`ir`.`is_range`='0'"
		] );

		return $loader->select();
	}

	/**
	 * @return IpRuleRecord|null
	 */
	public function lookupIp() {
		$loader = ( new IPs\DB\IpRules\LoadIpRules() )
			->setMod( $this->getMod() )
			->setIP( $this->getIP() );

		$loader->limit = 1;
		$loader->wheres = array_merge( $this->getWheresFromParams(), [
			"`ir`.`is_range`='0'"
		] );

		$results = $loader->select();
		return count( $results ) === 1 ? array_shift( $results ) : null;
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function selectRanges() :array {
		$loader = ( new IPs\DB\IpRules\LoadIpRules() )->setMod( $this->getMod() );

		$loader->wheres = array_merge( $this->getWheresFromParams(), [
			"`ir`.`is_range`='1'"
		] );

		return $loader->select();
	}

	private function getWheresFromParams() :array {
		$wheres = [];

		$types = $this->getListTypes();
		if ( !empty( $types ) ) {
			$wheres[] = sprintf( "`ir`.`type` IN ('%s')", implode( "','", array_filter( $types, function ( $type ) {
				return IpRulesDB\Handler::IsValidType( $type );
			} ) ) );
		}

		if ( !is_null( $this->isBlocked ) ) {
			$wheres[] = sprintf( "`ir`.`blocked_at`%s'0'", $this->isBlocked ? '>' : '=' );
		}

		return $wheres;
	}

	public function getListTypes() :array {
		return $this->listTypes;
	}

	public function setIsIpBlocked( bool $blocked ) :self {
		$this->isBlocked = $blocked;
		return $this;
	}

	public function setListTypeBlock() :self {
		$this->listTypes = [ IpRulesDB\Handler::T_MANUAL_BLACK, IpRulesDB\Handler::T_AUTO_BLACK ];
		return $this;
	}

	public function setListTypeAutoBlock() :self {
		return $this->setListType( IpRulesDB\Handler::T_AUTO_BLACK );
	}

	public function setListTypeBypass() :self {
		return $this->setListType( IpRulesDB\Handler::T_MANUAL_WHITE );
	}

	public function setListTypeCrowdsec() :self {
		return $this->setListType( IpRulesDB\Handler::T_CROWDSEC );
	}

	public function setListTypeManualBlock() :self {
		return $this->setListType( IpRulesDB\Handler::T_MANUAL_BLACK );
	}

	/**
	 * @return $this
	 */
	public function setListType( string $type ) :self {
		$this->listTypes = [ $type ];
		return $this;
	}
}