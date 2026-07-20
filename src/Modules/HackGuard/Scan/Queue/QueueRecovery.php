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

	public function recoverReadyScan( ScansDB\Record $scan ) :bool {
		$scanID = (int)$scan->id;
		if ( $scanID < 1 ) {
			return false;
		}

		$claimedItems = $this->startedUnfinishedItems( $scanID );
		if ( !empty( $claimedItems ) ) {
			$this->recoverClaimedItems( $scanID, $claimedItems );
			return true;
		}

		$unstartedItemID = $this->unstartedUnfinishedItemID( $scanID );
		if ( $unstartedItemID > 0 ) {
			return $this->resumeUnstartedWork( $scan, $unstartedItemID );
		}

		return true;
	}

	/**
	 * @param list<array{id:int,attempts:int}> $items
	 */
	private function recoverClaimedItems( int $scanID, array $items ) :void {
		$itemIDs = [];
		foreach ( $items as $item ) {
			if ( $item[ 'attempts' ] >= self::MAX_ITEM_ATTEMPTS ) {
				( new RunState() )->markFailed( $scanID, ReconcileQueue::MESSAGE_TIMED_OUT );
				return;
			}

			$itemIDs[] = $item[ 'id' ];
		}

		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s`
					SET `started_at`=0
					WHERE `scan_ref`=%d
					  AND `id` IN (%s)
					  AND `finished_at`=0
					  AND `started_at`>0
					  AND `attempts`<%d;",
				self::con()->db_con->scan_items->getTable(),
				$scanID,
				\implode( ',', $itemIDs ),
				self::MAX_ITEM_ATTEMPTS
			)
		);

		$this->touchScan( $scanID );
		self::con()->comps->scans_queue->getQueueProcessor()->dispatch();
	}

	private function resumeUnstartedWork( ScansDB\Record $scan, int $unstartedItemID ) :bool {
		if ( $this->hasEarlierUnfinishedReadyWork( $scan, $unstartedItemID ) ) {
			$this->touchScan( (int)$scan->id );
			return true;
		}

		$now = Services::Request()->ts();
		$meta = \is_array( $scan->meta ) ? $scan->meta : [];
		$recovery = \is_array( $meta[ RunState::META_KEY_WATCHDOG_RECOVERY ] ?? null )
			? $meta[ RunState::META_KEY_WATCHDOG_RECOVERY ]
			: [];

		$lastAttemptAt = (int)( $recovery[ 'last_attempt_at' ] ?? 0 );
		if ( $lastAttemptAt > $now - self::RESUME_COOLDOWN ) {
			return true;
		}

		$attempts = (int)( $recovery[ 'attempts' ] ?? 0 );
		if ( $attempts >= self::MAX_RESUME_ATTEMPTS ) {
			( new RunState() )->markFailed( (int)$scan->id, ReconcileQueue::MESSAGE_TIMED_OUT );
			return true;
		}
		$attempts++;

		$meta[ RunState::META_KEY_WATCHDOG_RECOVERY ] = [
			'attempts'        => $attempts,
			'last_attempt_at' => $now,
		];
		$scan->meta = $meta;
		if ( !self::con()->db_con->scans->getQueryUpdater()->updateById( (int)$scan->id, [
			'last_process_at' => $now,
			'meta'            => $scan->getRawData()[ 'meta' ],
		] ) ) {
			error_log( \sprintf(
				'Shield scan recovery persistence failed: scan_id=%d phase=ready-unstarted',
				(int)$scan->id
			) );
			return false;
		}

		self::con()->comps->scans_queue->getQueueProcessor()->dispatch();
		return true;
	}

	private function touchScan( int $scanID ) :void {
		if ( $scanID > 0 ) {
			self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
				'last_process_at' => Services::Request()->ts(),
			] );
		}
	}

	/**
	 * @return list<array{id:int,attempts:int}>
	 */
	private function startedUnfinishedItems( int $scanID ) :array {
		$rows = Services::WpDb()->selectCustom(
			sprintf( "SELECT `id`, `attempts`
					FROM `%s`
					WHERE `scan_ref`=%d
					  AND `finished_at`=0
					  AND `started_at`>0
					ORDER BY `id` ASC;",
				self::con()->db_con->scan_items->getTable(),
				$scanID
			)
		);
		$items = [];
		foreach ( \is_array( $rows ) ? $rows : [] as $row ) {
			if ( !\is_array( $row ) ) {
				continue;
			}
			$itemID = (int)( $row[ 'id' ] ?? 0 );
			if ( $itemID < 1 ) {
				continue;
			}
			$items[] = [
				'id'       => $itemID,
				'attempts' => (int)( $row[ 'attempts' ] ?? 0 ),
			];
		}
		return $items;
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
