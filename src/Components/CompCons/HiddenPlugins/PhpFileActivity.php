<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

final class PhpFileActivity {

	public const Inert = 'inert';

	public const Executable = 'executable';

	public const Unreadable = 'unreadable';

	public const Invalid = 'invalid';

	public const ALL = [
		self::Inert,
		self::Executable,
		self::Unreadable,
		self::Invalid,
	];

	/**
	 * @phpstan-param value-of<self::ALL> $activity
	 */
	public static function isAlertable( string $activity ) :bool {
		self::assertValid( $activity );

		return $activity !== self::Inert;
	}

	/**
	 * @phpstan-assert value-of<self::ALL> $activity
	 */
	public static function assertValid( string $activity ) :void {
		if ( !\in_array( $activity, self::ALL, true ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Unsupported PHP file activity: %s', $activity ) );
		}
	}
}
