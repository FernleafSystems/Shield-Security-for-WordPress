<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha;

class SilentCaptchaComplexity {

	public const NONE = 'none';
	public const ADAPTIVE = 'adaptive';
	public const LOW = 'low';
	public const MEDIUM = 'medium';
	public const HIGH = 'high';

	public const VALID = [
		self::NONE,
		self::ADAPTIVE,
		self::LOW,
		self::MEDIUM,
		self::HIGH,
	];

	/**
	 * @param mixed $value
	 */
	public static function normalise( $value ) :string {
		$value = \is_scalar( $value ) ? (string)$value : '';
		if ( $value === 'legacy' ) {
			$value = self::LOW;
		}
		return \in_array( $value, self::VALID, true ) ? $value : self::MEDIUM;
	}

	/**
	 * @param mixed $value
	 */
	public static function resolve( $value ) :string {
		$value = self::normalise( $value );
		if ( $value === self::ADAPTIVE ) {
			$value = \wp_is_mobile() ? self::MEDIUM : self::HIGH;
		}
		return $value;
	}
}
