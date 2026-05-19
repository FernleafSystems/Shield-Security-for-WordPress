<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueueHeartbeat {

	use PluginControllerConsumer;

	public const MIN_INTERVAL = 30;

	private static array $lastWriteAt = [];

	public function tick( int $scanID, bool $force = false ) :bool {
		if ( $scanID < 1 ) {
			return false;
		}

		$now = Services::Request()->ts();
		if ( !$force && isset( self::$lastWriteAt[ $scanID ] )
			 && self::$lastWriteAt[ $scanID ] > $now - self::MIN_INTERVAL ) {
			return false;
		}

		$written = (int)Services::WpDb()->doSql(
			sprintf( "UPDATE `%s`
					SET `last_process_at`=%d
					WHERE `id`=%d
					  AND `finished_at`=0
					  AND `status`='%s'
					  AND `last_process_at`<%d;",
				self::con()->db_con->scans->getTable(),
				$now,
				$scanID,
				ScanStatus::RUNNING,
				$now - self::MIN_INTERVAL
			)
		) > 0;

		self::prime( $scanID, $now );

		return $written;
	}

	public static function prime( int $scanID, ?int $timestamp = null ) :void {
		if ( $scanID > 0 ) {
			self::$lastWriteAt[ $scanID ] = $timestamp ?? Services::Request()->ts();
		}
	}

	public static function resetRuntimeCache() :void {
		self::$lastWriteAt = [];
	}
}
