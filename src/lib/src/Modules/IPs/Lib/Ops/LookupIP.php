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

class LookupIP {

	use ModConsumer;
	use IPs\Components\IpAddressConsumer;

	/**
	 * @var string
	 */
	private $listType;

	/**
	 * @var bool
	 */
	private $isBlocked;

	/**
	 * @return IpRuleRecord|null
	 */
	public function lookup( bool $includeRanges = true ) {
		$record = null;

		$parsedIP = Factory::parseRangeString( $this->getIP() );
		if ( !empty( $parsedIP ) ) {
			if ( $includeRanges ) {
				foreach ( $this->selectRanges() as $maybe ) {
					$maybeParsed = Factory::parseRangeString( $maybe->ipAsSubnetRange( true ) );
					if ( !empty( $maybeParsed ) && $maybeParsed->containsRange( $parsedIP ) ) {
						$record = $maybe;
						break;
					}
				}
			}
			if ( empty( $record ) ) {
				$record = $this->lookupIp();
			}
		}
		return $record;
	}

	/**
	 * @return IpRuleRecord|null
	 */
	public function lookupIp() {
		$loader = ( new IPs\DB\IpRules\LoadIpRules() )
			->setMod( $this->getMod() )
			->setIP( $this->getIP() );

		$wheres = $this->getWheresFromParams();
		$wheres[] = "`ir`.`is_range`='0'";
		$loader->wheres = $wheres;
		$loader->limit = 1;

		$results = $loader->select();
		return count( $results ) === 1 ? array_shift( $results ) : null;
	}

	/**
	 * @return IpRuleRecord[]
	 */
	private function selectRanges() :array {
		$loader = ( new IPs\DB\IpRules\LoadIpRules() )->setMod( $this->getMod() );

		$wheres = $this->getWheresFromParams();
		$wheres[] = "`ir`.`is_range`='1'";
		$loader->wheres = $wheres;

		return $loader->select();
	}

	private function getWheresFromParams() :array {

		$wheres = [];
		if ( $this->getListType() == 'white' ) {
			$wheres[] = sprintf( "`ir`.`type`='%s'", IpRulesDB\Handler::T_MANUAL_WHITE );
		}
		elseif ( $this->getListType() == 'black' ) {
			$wheres[] = sprintf( "`ir`.`type` IN ('%s')", implode( "','", [
				IpRulesDB\Handler::T_AUTO_BLACK,
				IpRulesDB\Handler::T_MANUAL_BLACK
			] ) );
			if ( !is_null( $this->isIpBlocked() ) ) {
				$wheres[] = sprintf( "`ir`.`blocked_at`%s'0'", $this->isIpBlocked() ? '>' : '=' );
			}
		}
		elseif ( $this->getListType() == 'crowdsec' ) {
			$wheres[] = sprintf( "`ir`.`type`='%s'", IpRulesDB\Handler::T_CROWDSEC );
		}

		return $wheres;
	}

	/**
	 * @return string
	 */
	public function getListType() {
		return $this->listType;
	}

	/**
	 * @return bool|null
	 */
	public function isIpBlocked() {
		return $this->isBlocked;
	}

	/**
	 * @return $this
	 */
	public function setIsIpBlocked( bool $blocked ) {
		$this->isBlocked = $blocked;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setListTypeBlock() :self {
		$this->listType = 'black';
		return $this;
	}

	public function setListTypeCrowdsec() :self {
		$this->listType = 'crowdsec';
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setListTypeBypass() :self {
		$this->listType = 'white';
		return $this;
	}
}