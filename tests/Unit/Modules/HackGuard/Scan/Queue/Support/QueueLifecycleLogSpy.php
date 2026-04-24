<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support;

class QueueLifecycleLogSpy {

	private static array $messages = [];

	public static function reset() :void {
		self::$messages = [];
	}

	public static function record( string $message ) :void {
		self::$messages[] = $message;
	}

	/**
	 * @return string[]
	 */
	public static function messages() :array {
		return self::$messages;
	}

	public static function contains( string $needle ) :bool {
		foreach ( self::$messages as $message ) {
			if ( \strpos( $message, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
