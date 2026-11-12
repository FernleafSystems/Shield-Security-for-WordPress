<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

class ReportFrequency {

	public const DAILY = 'daily';
	public const WEEKLY = 'weekly';
	public const LEGACY_HOURLY = 'hourly';
	public const LEGACY_LONGER = [ 'biweekly', 'monthly' ];

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public static function normaliseLegacy( $value ) {
		return $value === self::LEGACY_HOURLY ? self::DAILY : $value;
	}

	/**
	 * Info reports offer the same choices as alert reports, so the longer periods they used to offer become weekly.
	 * @param mixed $value
	 * @return mixed
	 */
	public static function normaliseInfo( $value ) {
		$value = self::normaliseLegacy( $value );
		return \in_array( $value, self::LEGACY_LONGER, true ) ? self::WEEKLY : $value;
	}
}
