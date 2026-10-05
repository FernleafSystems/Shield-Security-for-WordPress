<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

final class PositiveIntegerInput {

	public static function parse( string $value, string $source ) :int {
		if ( !\ctype_digit( $value ) || (int)$value < 1 ) {
			throw new \InvalidArgumentException( $source.' must be a positive integer.' );
		}
		return (int)$value;
	}
}
