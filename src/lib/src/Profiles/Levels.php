<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Profiles;

class Levels {

	public const CURRENT = 'current';
	public const LIGHT = 'light';
	public const MEDIUM = 'medium';
	public const STRONG = 'strong';

	public static function Sub( string $level ) :?string {
		return [
				   self::LIGHT  => null,
				   self::MEDIUM => self::LIGHT,
				   self::STRONG => self::MEDIUM,
			   ][ $level ] ?? null;
	}

	public static function Enum() :array {
		return [
			self::LIGHT,
			self::MEDIUM,
			self::STRONG,
		];
	}
}