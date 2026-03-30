<?php declare( strict_types=1 );

namespace Safe;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;
use Safe\Exceptions\DatetimeException;

class DateTimeImmutable extends \DateTimeImmutable {

	private \DateTimeImmutable $innerDateTime;

	public function __construct( string $datetime = 'now', ?DateTimeZone $timezone = null ) {
		parent::__construct( $datetime, $timezone );
		$this->innerDateTime = new parent( $datetime, $timezone );
	}

	private static function createFromRegular( \DateTimeImmutable $datetime ) :self {
		$safeDateTime = new self( $datetime->format( 'Y-m-d H:i:s.u' ), $datetime->getTimezone() );
		$safeDateTime->innerDateTime = $datetime;
		return $safeDateTime;
	}

	public static function createFromFormat( string $format, string $datetime, ?DateTimeZone $timezone = null ) :self {
		$result = parent::createFromFormat( $format, $datetime, $timezone );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function format( string $format ) :string {
		$result = $this->innerDateTime->format( $format );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return $result;
	}

	public function diff( DateTimeInterface $targetObject, bool $absolute = false ) :DateInterval {
		$result = $this->innerDateTime->diff( $targetObject, $absolute );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return $result;
	}

	public function modify( string $modifier ) :self {
		$result = $this->innerDateTime->modify( $modifier );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function setDate( int $year, int $month, int $day ) :self {
		$result = $this->innerDateTime->setDate( $year, $month, $day );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function setISODate( int $year, int $week, int $dayOfWeek = 1 ) :self {
		$result = $this->innerDateTime->setISODate( $year, $week, $dayOfWeek );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function setTime( int $hour, int $minute, int $second = 0, int $microsecond = 0 ) :self {
		$result = $this->innerDateTime->setTime( $hour, $minute, $second, $microsecond );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function setTimestamp( int $timestamp ) :self {
		$result = $this->innerDateTime->setTimestamp( $timestamp );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function setTimezone( DateTimeZone $timezone ) :self {
		$result = $this->innerDateTime->setTimezone( $timezone );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function sub( DateInterval $interval ) :self {
		$result = $this->innerDateTime->sub( $interval );
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return self::createFromRegular( $result );
	}

	public function getOffset() :int {
		$result = $this->innerDateTime->getOffset();
		if ( $result === false ) {
			throw DatetimeException::createFromPhpError();
		}
		return $result;
	}

	public function add( DateInterval $interval ) :self {
		return self::createFromRegular( $this->innerDateTime->add( $interval ) );
	}

	public static function __set_state( array $array ) :self {
		return self::createFromRegular( parent::__set_state( $array ) );
	}

	public function getTimezone() :DateTimeZone {
		return $this->innerDateTime->getTimezone();
	}

	public function getTimestamp() :int {
		return $this->innerDateTime->getTimestamp();
	}
}
