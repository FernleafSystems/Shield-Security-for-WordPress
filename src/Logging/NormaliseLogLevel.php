<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging;

class NormaliseLogLevel {

	private const LEGACY_LEVEL_MAP = [
		'alert'   => 'warning',
		'debug'   => 'info',
	];

	private const CANONICAL_LEVELS = [
		'warning',
		'notice',
		'info',
	];

	public static function forEvent( string $level ) :string {
		$level = self::mapLegacyLevel( $level );
		return \in_array( $level, self::CANONICAL_LEVELS, true ) ? $level : 'notice';
	}

	/**
	 * @param string[]|string|mixed $levels
	 */
	public static function forDbSelection( $levels ) :array {
		if ( \is_string( $levels ) ) {
			$levels = [ $levels ];
		}

		$levels = \array_values( \array_unique( \array_filter(
			\array_map(
				fn( $level ) => self::mapLegacyLevel( \is_scalar( $level ) ? (string)$level : '' ),
				\is_array( $levels ) ? $levels : []
			)
		) ) );

		if ( \in_array( 'disabled', $levels, true ) ) {
			$levels= [ 'disabled' ];
		}
		else {
			$levels = \array_values(
				\array_filter( $levels, fn( string $level ) => \in_array( $level, self::CANONICAL_LEVELS, true ) )
			);
		}

		return $levels;
	}

	private static function mapLegacyLevel( string $level ) :string {
		$level = \strtolower( \trim( $level ) );
		return self::LEGACY_LEVEL_MAP[ $level ] ?? $level;
	}
}
