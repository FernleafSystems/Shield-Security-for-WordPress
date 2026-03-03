<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Common;

class EnumEnabledStatus {

	public const BAD = 'bad';
	public const GOOD = 'good';
	public const OKAY = 'okay';
	public const NEUTRAL = 'neutral';
	public const NEUTRAL_ENABLED = 'neutral_enabled';

	public static function toSeverity( string $enabledStatus, string $defaultSeverity = 'good' ) :string {
		$defaultSeverity = \in_array( $defaultSeverity, [ 'good', 'warning', 'critical' ], true )
			? $defaultSeverity
			: 'good';

		switch ( \strtolower( \trim( $enabledStatus ) ) ) {
			case self::BAD:
				return 'critical';

			case self::OKAY:
			case self::NEUTRAL:
				return 'warning';

			case self::GOOD:
			case self::NEUTRAL_ENABLED:
				return 'good';

			default:
				return $defaultSeverity;
		}
	}
}
