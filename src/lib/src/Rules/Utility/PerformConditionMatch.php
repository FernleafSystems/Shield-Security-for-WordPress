<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Services\Exceptions\NotAnIpAddressOrRangeException;
use FernleafSystems\Wordpress\Services\Services;

class PerformConditionMatch {

	private $matchType;

	private $value;

	private $matchAgainst;

	public function __construct( $incomingValue, $matchAgainst, string $matchType ) {
		$this->value = $incomingValue;
		$this->matchAgainst = $matchAgainst;
		$this->matchType = $matchType;
	}

	/**
	 * @throws \Exception
	 */
	public function doMatch() :bool {
		switch ( $this->matchType ) {
			case EnumMatchTypes::MATCH_TYPE_REGEX:
				$matched = $this->matchRegex();
				break;
			case EnumMatchTypes::MATCH_TYPE_EQUALS:
				$matched = $this->matchEquals();
				break;
			case EnumMatchTypes::MATCH_TYPE_EQUALS_I:
				$matched = $this->matchEqualsI();
				break;
			case EnumMatchTypes::MATCH_TYPE_CONTAINS:
				$matched = $this->matchContains();
				break;
			case EnumMatchTypes::MATCH_TYPE_IP_RANGE:
				$matched = $this->matchIpRange();
				break;
			default:
				throw new \Exception( 'No handling for match type: '.$this->matchType );
		}
		return $matched;
	}

	private function matchRegex() :bool {
		return (bool)\preg_match( sprintf( '#%s#i', $this->matchAgainst ), $this->value );
	}

	private function matchEquals() :bool {
		return \strval( $this->value ) === \strval( $this->matchAgainst );
	}

	private function matchContains() :bool {
		return \str_contains( $this->value, $this->matchAgainst );
	}

	private function matchEqualsI() :bool {
		return \strtolower( $this->value ) === \strtolower( $this->matchAgainst );
	}

	private function matchIpRange() :bool {
		try {
			$in = Services::IP()->IpIn( $this->value, [ $this->matchAgainst ] );
		}
		catch ( NotAnIpAddressOrRangeException $e ) {
			$in = false;
		}
		return $in;
	}
}