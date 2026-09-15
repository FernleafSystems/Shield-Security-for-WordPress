<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

class NotificationMetadata {

	public const META_KEY = 'notification_attempts_started';
	public const MAX_ATTEMPTS = 3;

	public function attemptsStarted( array $meta ) :int {
		return \min( self::MAX_ATTEMPTS, \max( 0, (int)( $meta[ self::META_KEY ] ?? 0 ) ) );
	}

	public function startFreshCycle( array $meta ) :array {
		$meta[ self::META_KEY ] = 1;
		return $meta;
	}

	public function increment( array $meta ) :array {
		$meta[ self::META_KEY ] = \min( self::MAX_ATTEMPTS, $this->attemptsStarted( $meta ) + 1 );
		return $meta;
	}

	public function reset( array $meta ) :array {
		$meta[ self::META_KEY ] = 0;
		return $meta;
	}
}
