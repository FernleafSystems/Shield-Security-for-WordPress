<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

class ReportFrequency {

	public const DAILY = 'daily';
	public const LEGACY_HOURLY = 'hourly';

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public static function normaliseLegacy( $value ) {
		return $value === self::LEGACY_HOURLY ? self::DAILY : $value;
	}
}
