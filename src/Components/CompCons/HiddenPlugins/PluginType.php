<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

final class PluginType {

	public const Standard = 'plugin';

	public const MustUse = 'mu-plugin';

	public const ALL = [
		self::Standard,
		self::MustUse,
	];

	/**
	 * @phpstan-param value-of<self::ALL> $type
	 */
	public static function label( string $type ) :string {
		self::assertValid( $type );

		switch ( $type ) {
			case self::Standard:
				return __( 'Plugin', 'wp-simple-firewall' );
			case self::MustUse:
				return __( 'Must-Use Plugin', 'wp-simple-firewall' );
		}
	}

	/**
	 * @phpstan-assert value-of<self::ALL> $type
	 */
	public static function assertValid( string $type ) :void {
		if ( !\in_array( $type, self::ALL, true ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Unsupported hidden plugin type: %s', $type ) );
		}
	}
}
