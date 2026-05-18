<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueueRecovery {

	use PluginControllerConsumer;

	public const MAX_ITEM_ATTEMPTS = 2;
	public const MAX_RESUME_ATTEMPTS = 2;
	public const RESUME_COOLDOWN = 60;

	public function recoverReadyScan( ScansDB\Record $scan ) :void {
		$scanID = (int)$scan->id;
		if ( $scanID < 1 ) {
			return;
		}

		$claimedItem = $this->startedUnfinishedItem( $scanID );
		if ( !empty( $claimedItem ) ) {
			$this->recoverClaimedItem( $scanID, $claimedItem );
			return;
		}

		if ( $this->hasUnstartedUnfinishedItems( $scanID ) ) {
			$this->resumeUnstartedWork( $scan );
		}
	}

	private function recoverClaimedItem( int $scanID, array $item ) :void {
		$itemID = (int)( $item[ 'id' ] ?? 0 );
		$attempts = (int)( $item[ 'attempts' ] ?? 0 );
		if ( $itemID < 1 ) {
			return;
		}

		if ( $attempts >= self::MAX_ITEM_ATTEMPTS ) {
			( new RunState() )->markFailed( $scanID, ReconcileQueue::MESSAGE_TIMED_OUT );
			return;
		}

		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s`
					SET `started_at`=0
					WHERE `id`=%d
					  AND `scan_ref`=%d
					  AND `finished_at`=0
					  AND `started_at`>0
					  AND `attempts`<%d;",
				self::con()->db_con->scan_items->getTable(),
				$itemID,
				$scanID,
				self::MAX_ITEM_ATTEMPTS
			)
		);

		self::con()->comps->scans_queue->getQueueProcessor()->dispatch();
	}

	private function resumeUnstartedWork( ScansDB\Record $scan ) :void {
		$now = Services::Request()->ts();
		$meta = \is_array( $scan->meta ) ? $scan->meta : [];
		$recovery = \is_array( $meta[ RunState::META_KEY_WATCHDOG_RECOVERY ] ?? null )
			? $meta[ RunState::META_KEY_WATCHDOG_RECOVERY ]
			: [];

		$lastAttemptAt = (int)( $recovery[ 'last_attempt_at' ] ?? 0 );
		if ( $lastAttemptAt > $now - self::RESUME_COOLDOWN ) {
			return;
		}

		$attempts = (int)( $recovery[ 'attempts' ] ?? 0 ) + 1;
		if ( $attempts >= self::MAX_RESUME_ATTEMPTS ) {
			( new RunState() )->markFailed( (int)$scan->id, ReconcileQueue::MESSAGE_TIMED_OUT );
			return;
		}

		$meta[ RunState::META_KEY_WATCHDOG_RECOVERY ] = [
			'attempts'        => $attempts,
			'last_attempt_at' => $now,
		];
		$scan->meta = $meta;
		self::con()->db_con->scans->getQueryUpdater()->updateById( (int)$scan->id, [
			'meta' => $scan->getRawData()[ 'meta' ],
		] );

		self::con()->comps->scans_queue->getQueueProcessor()->dispatch();
	}

	private function startedUnfinishedItem( int $scanID ) :array {
		$row = Services::WpDb()->selectRow(
			sprintf( "SELECT `id`, `attempts`
					FROM `%s`
					WHERE `scan_ref`=%d
					  AND `finished_at`=0
					  AND `started_at`>0
					ORDER BY `id` ASC
					LIMIT 1;",
				self::con()->db_con->scan_items->getTable(),
				$scanID
			)
		);
		return \is_array( $row ) ? $row : [];
	}

	private function hasUnstartedUnfinishedItems( int $scanID ) :bool {
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT 1
					FROM `%s`
					WHERE `scan_ref`=%d
					  AND `finished_at`=0
					  AND `started_at`=0
					LIMIT 1;",
				self::con()->db_con->scan_items->getTable(),
				$scanID
			)
		) === 1;
	}
}
