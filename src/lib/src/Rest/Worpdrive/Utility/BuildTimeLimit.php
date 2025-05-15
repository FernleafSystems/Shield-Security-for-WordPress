<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Utility;

class BuildTimeLimit {

	public const DEFAULT_TIME_LIMIT = 5;

	public static function Build( int $ts = 0 ) :int {
		return \time() + ( empty( $ts ) ? static::DEFAULT_TIME_LIMIT : $ts );
	}
}