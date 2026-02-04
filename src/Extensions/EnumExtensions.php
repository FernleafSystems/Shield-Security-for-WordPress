<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

class EnumExtensions {

	public const PROXY_CHECK_IO = 'proxycheck';

	public static function All() :array {
		return [
			self::PROXY_CHECK_IO,
		];
	}
}