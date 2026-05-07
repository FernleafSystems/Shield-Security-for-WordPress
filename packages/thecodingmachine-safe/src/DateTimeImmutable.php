<?php declare( strict_types=1 );

namespace Safe;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;
use Safe\Exceptions\DatetimeException;

class DateTimeImmutable extends \DateTimeImmutable {

	private \DateTimeImmutable $innerDateTime;

	/**
	 * @param string $datetime
	 */
	public function __construct( $datetime = 'now', ?DateTimeZone $timezone = null ) {
		parent::__construct( $datetime, $timezone );
		$this->innerDateTime = new parent( $datetime, $timezone );
	}

	private static function createFromRegular( \DateTimeImmutable $datetime ) :self {
		$safeDateTime = new self( $datetime->format( 'Y-m-d H:i:s.u' ), $datetime->getTimezone() );
		$safeDateTime->innerDateTime = $datetime;
		return $safeDateTime;
	}

	/**
	 * @param string            $format
	 * @param string            $datetime
	 * @param DateTimeZone|null $timezone
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public static function createFromFormat( $format, $datetime, ?DateTimeZone $timezone = null ) {
		$result = parent::createFromFormat( $format, $datetime, $timezone );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @param string $format
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function format( $format ) {
		$result = $this->innerDateTime->format( $format );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return $result;
	}

	/**
	 * @param DateTimeInterface $targetObject
	 * @param bool              $absolute
	 * @return DateInterval
	 */
	#[\ReturnTypeWillChange]
	public function diff( $targetObject, $absolute = false ) {
		$result = $this->innerDateTime->diff( $targetObject, $absolute );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return $result;
	}

	/**
	 * @param string $modifier
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function modify( $modifier ) {
		$result = $this->innerDateTime->modify( $modifier );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function setDate( $year, $month, $day ) {
		$result = $this->innerDateTime->setDate( $year, $month, $day );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @param int $year
	 * @param int $week
	 * @param int $dayOfWeek
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function setISODate( $year, $week, $dayOfWeek = 1 ) {
		$result = $this->innerDateTime->setISODate( $year, $week, $dayOfWeek );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @param int $hour
	 * @param int $minute
	 * @param int $second
	 * @param int $microsecond
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function setTime( $hour, $minute, $second = 0, $microsecond = 0 ) {
		$result = $this->innerDateTime->setTime( $hour, $minute, $second, $microsecond );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @param int $timestamp
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function setTimestamp( $timestamp ) {
		$result = $this->innerDateTime->setTimestamp( $timestamp );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @param DateTimeZone $timezone
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function setTimezone( $timezone ) {
		$result = $this->innerDateTime->setTimezone( $timezone );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @param DateInterval $interval
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function sub( $interval ) {
		$result = $this->innerDateTime->sub( $interval );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	/**
	 * @return int
	 */
	#[\ReturnTypeWillChange]
	public function getOffset() {
		$result = $this->innerDateTime->getOffset();
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return $result;
	}

	/**
	 * @param DateInterval $interval
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public function add( $interval ) {
		return self::createFromRegular( $this->innerDateTime->add( $interval ) );
	}

	/**
	 * @param array<string,mixed> $array
	 * @return self
	 */
	#[\ReturnTypeWillChange]
	public static function __set_state( $array ) {
		return self::createFromRegular( parent::__set_state( $array ) );
	}

	/**
	 * @return DateTimeZone
	 */
	#[\ReturnTypeWillChange]
	public function getTimezone() {
		return $this->innerDateTime->getTimezone();
	}

	/**
	 * @return int
	 */
	#[\ReturnTypeWillChange]
	public function getTimestamp() {
		return $this->innerDateTime->getTimestamp();
	}
}
