<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Services\Exceptions\NotAnIpAddressOrRangeException;
use FernleafSystems\Wordpress\Services\Services;

class PerformConditionMatch {

	private $matchType;

	private $incomingValue;

	private $matchAgainst;

	public function __construct( $incomingValue, $matchAgainst, string $matchType ) {
		$this->incomingValue = $incomingValue;
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
			case EnumMatchTypes::MATCH_TYPE_CONTAINS_I:
				$matched = $this->matchContainsI();
				break;
			case EnumMatchTypes::MATCH_TYPE_IP_EQUALS:
			case EnumMatchTypes::MATCH_TYPE_IP_RANGE:
				$matched = $this->matchIpRange();
				break;
			case EnumMatchTypes::MATCH_TYPE_LESS_THAN:
				$matched = $this->matchLessThan();
				break;
			case EnumMatchTypes::MATCH_TYPE_GREATER_THAN:
				$matched = $this->matchGreaterThan();
				break;
			default:
				throw new \Exception( 'No handling for match type: '.$this->matchType );
		}
		return $matched;
	}

	private function matchRegex() :bool {
		$incoming = $this->incomingScalarAsString();
		return $incoming !== null && (bool)\preg_match( $this->matchAgainst, $incoming );
	}

	private function matchEquals() :bool {
		return !\is_array( $this->incomingValue ) && \strval( $this->incomingValue ) === \strval( $this->matchAgainst );
	}

	/**
	 * @throws \Exception
	 */
	private function matchContains() :bool {
		if ( \is_scalar( $this->incomingValue ) ) {
			$match = \str_contains( \strval( $this->incomingValue ), \strval( $this->matchAgainst ) );
		}
		elseif ( \is_array( $this->incomingValue ) ) {
			$match = \in_array( \strval( $this->matchAgainst ), \array_map( '\strval', $this->incomingValue ) );
		}
		else {
			throw new \Exception( sprintf( 'Invalid type for incoming value: %s', var_export( $this->incomingValue, true ) ) );
		}
		return $match;
	}

	private function matchContainsI() :bool {
		$incoming = $this->incomingScalarAsString();
		return $incoming !== null && \str_contains( \strtolower( $incoming ), \strtolower( $this->matchAgainst ) );
	}

	private function matchEqualsI() :bool {
		$incoming = $this->incomingScalarAsString();
		return $incoming !== null && \strtolower( $incoming ) === \strtolower( $this->matchAgainst );
	}

	/**
	 * Request values can arrive as arrays (e.g. ?name[]=x) and parameter names as integers (e.g. ?0=x).
	 * Scalars compare as strings; anything else can't match a string pattern.
	 */
	private function incomingScalarAsString() :?string {
		return \is_scalar( $this->incomingValue ) ? \strval( $this->incomingValue ) : null;
	}

	private function matchIpRange() :bool {
		try {
			$in = Services::IP()->IpIn( $this->incomingValue, [ $this->matchAgainst ] );
		}
		catch ( NotAnIpAddressOrRangeException $e ) {
			$in = false;
		}
		return $in;
	}

	private function matchLessThan() :bool {
		return $this->incomingValue < $this->matchAgainst;
	}

	private function matchGreaterThan() :bool {
		return $this->incomingValue > $this->matchAgainst;
	}
}