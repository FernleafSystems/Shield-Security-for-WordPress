<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

/**
 * Typed reads of request-supplied fields: an action's data or its decoded form (see BaseAction::fields()).
 *
 * Request data can arrive in any shape (a field can be an array, a number, a boolean or null), so a value is
 * only returned when it has the expected type. A missing field gives the default when there is one; a default is
 * returned as given, so it can be a sentinel such as 0 or -1. A field that is supplied with the wrong shape, or
 * is missing without a default, refuses the request with an ActionException, which every entry point turns into
 * a controlled response. Nothing is coerced: an array never becomes a string, the integer 1, or "yes".
 */
class RequestFields {

	private array $fields;

	public function __construct( array $fields ) {
		$this->fields = $fields;
	}

	/**
	 * @throws ActionException
	 */
	public function string( string $key, ?string $default = null ) :string {
		$value = $this->fields[ $key ] ?? null;
		if ( $value === null ) {
			return $this->defaultFor( $key, $default );
		}
		if ( !\is_string( $value ) ) {
			throw $this->invalid( $key );
		}
		return $value;
	}

	/**
	 * One of a fixed set of string values, compared exactly.
	 * @param string[] $allowed
	 * @throws ActionException
	 */
	public function oneOf( string $key, array $allowed, ?string $default = null ) :string {
		$value = $this->string( $key, $default );
		if ( !\in_array( $value, $allowed, true ) ) {
			throw $this->invalid( $key );
		}
		return $value;
	}

	/**
	 * An explicit yes or no, given as one of the two tokens the page sends. A boolean or integer counts as the
	 * text it's sent as in a form ('true', 'false', '1', '0'), so the same value works whether it arrives as a
	 * form field or in JSON. Anything else, including any other non-empty text, refuses the request.
	 * @throws ActionException
	 */
	public function flag( string $key, string $yes, string $no, ?bool $default = null ) :bool {
		$value = $this->fields[ $key ] ?? null;
		if ( $value === null ) {
			return $this->defaultFor( $key, $default );
		}
		if ( \is_bool( $value ) ) {
			$value = $value ? 'true' : 'false';
		}
		elseif ( \is_int( $value ) ) {
			$value = (string)$value;
		}
		if ( $value === $yes ) {
			return true;
		}
		if ( $value === $no ) {
			return false;
		}
		throw $this->invalid( $key );
	}

	/**
	 * A positive integer, given either as an integer or as a string of digits.
	 * @throws ActionException
	 */
	public function positiveInt( string $key, ?int $default = null ) :int {
		$value = $this->fields[ $key ] ?? null;
		if ( $value === null ) {
			return $this->defaultFor( $key, $default );
		}
		$int = self::PositiveIntFrom( $value );
		if ( $int === null ) {
			throw $this->invalid( $key );
		}
		return $int;
	}

	/**
	 * A selection of record IDs. The field must be a list; entries that aren't positive IDs are left out rather
	 * than coerced, so a selection can never reach a record nobody selected.
	 * @return int[]
	 * @throws ActionException
	 */
	public function positiveIntList( string $key, ?array $default = null ) :array {
		return ( $this->fields[ $key ] ?? null ) === null
			? $this->defaultFor( $key, $default )
			: \array_values( \array_filter( \array_map( [ self::class, 'PositiveIntFrom' ], $this->array( $key ) ) ) );
	}

	/**
	 * A selection of text values, such as keys or identifiers. The field must be a list; entries that aren't text
	 * are left out rather than coerced.
	 * @return string[]
	 * @throws ActionException
	 */
	public function stringList( string $key, ?array $default = null ) :array {
		return ( $this->fields[ $key ] ?? null ) === null
			? $this->defaultFor( $key, $default )
			: \array_values( \array_filter( $this->array( $key ), '\is_string' ) );
	}

	/**
	 * A field that is itself a set of fields, such as a table's request or a submitted form, as it arrived. Only
	 * its being an array is checked here: what's inside it is still request input.
	 * @throws ActionException
	 */
	public function array( string $key, ?array $default = null ) :array {
		$value = $this->fields[ $key ] ?? null;
		if ( $value === null ) {
			return $this->defaultFor( $key, $default );
		}
		if ( !\is_array( $value ) ) {
			throw $this->invalid( $key );
		}
		return $value;
	}

	/**
	 * The single parser for a positive ID or count from request input: an integer, or a string of ASCII digits
	 * (leading zeros allowed) that fits in an int. Anything else is null, never a coerced number.
	 * @param mixed $value
	 */
	public static function PositiveIntFrom( $value ) :?int {
		if ( \is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}
		if ( !\is_string( $value ) || \preg_match( '#^\d+$#D', $value ) !== 1 ) {
			return null;
		}

		$digits = \ltrim( $value, '0' );
		if ( $digits === '' ) {
			return null;
		}
		$max = (string)\PHP_INT_MAX;
		if ( \strlen( $digits ) > \strlen( $max )
			 || ( \strlen( $digits ) === \strlen( $max ) && \strcmp( $digits, $max ) > 0 ) ) {
			return null;
		}
		return (int)$digits;
	}

	/**
	 * @template T
	 * @param T|null $default
	 * @return T
	 * @throws ActionException
	 */
	private function defaultFor( string $key, $default ) {
		if ( $default === null ) {
			throw new ActionException( \sprintf( 'Missing request field: %s', $key ) );
		}
		return $default;
	}

	private function invalid( string $key ) :ActionException {
		return new ActionException( \sprintf( 'Invalid request field: %s', $key ) );
	}
}
