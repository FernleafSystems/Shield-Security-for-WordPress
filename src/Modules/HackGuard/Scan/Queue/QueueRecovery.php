<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
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

		$unstartedItemID = $this->unstartedUnfinishedItemID( $scanID );
		if ( $unstartedItemID > 0 ) {
			$this->resumeUnstartedWork( $scan, $unstartedItemID );
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

		$this->touchScan( $scanID );
		self::con()->comps->scans_queue->getQueueProcessor()->dispatch();
	}

	private function resumeUnstartedWork( ScansDB\Record $scan, int $unstartedItemID ) :void {
		if ( $this->hasEarlierUnfinishedReadyWork( $scan, $unstartedItemID ) ) {
			$this->touchScan( (int)$scan->id );
			return;
		}

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
			'last_process_at' => $now,
			'meta'            => $scan->getRawData()[ 'meta' ],
		] );

		self::con()->comps->scans_queue->getQueueProcessor()->dispatch();
	}

	private function touchScan( int $scanID ) :void {
		if ( $scanID > 0 ) {
			self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
				'last_process_at' => Services::Request()->ts(),
			] );
		}
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

	private function unstartedUnfinishedItemID( int $scanID ) :int {
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT `id`
					FROM `%s`
					WHERE `scan_ref`=%d
					  AND `finished_at`=0
					  AND `started_at`=0
					ORDER BY `id` ASC
					LIMIT 1;",
				self::con()->db_con->scan_items->getTable(),
				$scanID
			)
		);
	}

	private function hasEarlierUnfinishedReadyWork( ScansDB\Record $scan, int $unstartedItemID ) :bool {
		$scanID = (int)$scan->id;
		$createdAt = (int)$scan->created_at;
		if ( $scanID < 1 || $unstartedItemID < 1 ) {
			return false;
		}

		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT 1
					FROM `%s` AS `blocker`
					INNER JOIN `%s` AS `blocker_item`
						ON `blocker_item`.`scan_ref`=`blocker`.`id`
						AND `blocker_item`.`finished_at`=0
					WHERE `blocker`.`id`<>%d
					  AND `blocker`.`finished_at`=0
					  AND `blocker`.`status` IN (%s)
					  AND `blocker`.`ready_at`>0
					  AND (
						`blocker`.`created_at`<%d
						OR (
							`blocker`.`created_at`=%d
							AND `blocker_item`.`id`<%d
						)
					  )
					LIMIT 1;",
				self::con()->db_con->scans->getTable(),
				self::con()->db_con->scan_items->getTable(),
				$scanID,
				ScanStatus::sqlList( ScanStatus::READY ),
				$createdAt,
				$createdAt,
				$unstartedItemID
			)
		) === 1;
	}
}
