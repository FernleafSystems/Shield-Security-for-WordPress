<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type ActiveScanStatus from \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus
 * @phpstan-import-type ActiveScanRow from \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus
 */
class QueueWatchdog {

	use PluginControllerConsumer;

	public const STALE_AFTER = 180;
	public const CHECK_INTERVAL = 60;
	/** @var array<ActiveScanStatus,array{fallback_column:'created_at'|'ready_at',requires_ready:bool}> */
	private const STALE_STATUS_RULES = [
		ScanStatus::QUEUED   => [
			'fallback_column' => 'created_at',
			'requires_ready'  => false,
		],
		ScanStatus::BUILDING => [
			'fallback_column' => 'created_at',
			'requires_ready'  => false,
		],
		ScanStatus::BUILT    => [
			'fallback_column' => 'ready_at',
			'requires_ready'  => true,
		],
		ScanStatus::RUNNING  => [
			'fallback_column' => 'created_at',
			'requires_ready'  => true,
		],
	];

	public function register() :void {
		add_action( $this->hook(), [ $this, 'runScheduled' ] );
	}

	public function runScheduled() :void {
		$this->run();
		$this->scheduleIfActive();
	}

	public function recoverScanIfStale( int $scanID ) :bool {
		if ( $scanID < 1 ) {
			return false;
		}

		try {
			if ( !$this->claimStaleActiveScan( $scanID ) ) {
				return false;
			}

			/** @var ?ScansDB\Record $scan */
			$scan = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
			if ( $scan === null
				 || $scan->finished_at > 0
				 || !\in_array( $scan->status, ScanStatus::ACTIVE, true ) ) {
				return true;
			}

			switch ( $scan->status ) {
				case ScanStatus::QUEUED:
					if ( !$this->recoverStaleQueuedScan( $scan ) ) {
						return false;
					}
					break;

				case ScanStatus::BUILDING:
					( new RunState() )->markFailed( $scanID, ReconcileQueue::MESSAGE_TIMED_OUT );
					break;

				case ScanStatus::BUILT:
				case ScanStatus::RUNNING:
					$reconciled = ( new ReconcileQueue() )->reconcileReadyScan( $scan );
					if ( $reconciled === false && !( new QueueRecovery() )->recoverReadyScan( $scan ) ) {
						return false;
					}
					break;

				default:
					throw new \UnexpectedValueException( 'Unsupported active scan status.' );
			}

			( new CompleteQueue() )->complete();
			return true;
		}
		finally {
			$this->scheduleIfActive();
		}
	}

	/**
	 * @param ActiveScanRow $scan
	 */
	public function isActiveScanStale( array $scan, int $cutoff ) :bool {
		$rule = self::STALE_STATUS_RULES[ $scan[ 'status' ] ];
		if ( $rule[ 'requires_ready' ] && $scan[ 'ready_at' ] <= 0 ) {
			return false;
		}

		$lastProcessAt = $scan[ 'last_process_at' ];
		$fallbackAt = $scan[ $rule[ 'fallback_column' ] ];
		return $lastProcessAt > 0 ? $lastProcessAt < $cutoff : $fallbackAt < $cutoff;
	}

	/**
	 * @param list<string> $slugs
	 * @return array<string,int>
	 */
	public function runForStaleStartBlockers( array $slugs, string $scopeType = 'full', string $scopeKey = '' ) :array {
		$blockers = $this->staleStartBlockerIDsBySlug( $slugs, $scopeType, $scopeKey );
		if ( !empty( $blockers ) ) {
			$this->run();
			$this->scheduleIfActive();
		}
		return $blockers;
	}

	public function run() :void {
		$hadActive = $this->hasActiveScans();
		$cutoff = $this->cutoff();

		$maintenance = new QueueMaintenance();
		$maintenance->run();

		$runState = new RunState();
		foreach ( $this->staleScans( $cutoff, [ ScanStatus::BUILDING ] ) as $scan ) {
			$runState->markFailed( $scan->id, ReconcileQueue::MESSAGE_TIMED_OUT );
		}

		$builderDispatchAllowed = true;
		/** @var ?ScansDB\Record $queuedScan */
		$queuedScan = self::con()->db_con->scans->getQuerySelector()
						 ->filterByStatus( ScanStatus::QUEUED )
						 ->filterByNotFinished()
						 ->setOrderBy( 'created_at', 'ASC', true )
						 ->setOrderBy( 'id', 'ASC' )
						 ->first();
		if ( $queuedScan !== null ) {
			$lastProcessAt = (int)$queuedScan->last_process_at;
			$staleAt = $lastProcessAt > 0 ? $lastProcessAt : (int)$queuedScan->created_at;
			if ( $staleAt < $cutoff ) {
				$builderDispatchAllowed = $this->recoverStaleQueuedScan( $queuedScan );
			}
		}

		$recovery = new QueueRecovery();
		foreach ( $this->staleScans( $cutoff, ScanStatus::READY, [ 'id', 'meta', 'created_at' ] ) as $scan ) {
			$recovery->recoverReadyScan( $scan );
		}

		$maintenance->run();

		if ( $hadActive && $builderDispatchAllowed ) {
			( new CompleteQueue() )->complete();
		}
	}

	public function scheduleIfActive() :void {
		if ( !$this->hasActiveScans() ) {
			$this->clearScheduled();
			return;
		}

		if ( !wp_next_scheduled( $this->hook() ) ) {
			wp_schedule_single_event(
				Services::Request()->ts() + self::CHECK_INTERVAL,
				$this->hook()
			);
		}
	}

	public function hook() :string {
		return self::con()->prefix( 'scan_queue_watchdog' );
	}

	private function recoverStaleQueuedScan( ScansDB\Record $scan ) :bool {
		$scanID = (int)$scan->id;
		$meta = \is_array( $scan->meta ) ? $scan->meta : [];
		$recovery = \is_array( $meta[ RunState::META_KEY_WATCHDOG_RECOVERY ] ?? null )
			? $meta[ RunState::META_KEY_WATCHDOG_RECOVERY ]
			: [];

		$attempts = (int)( $recovery[ 'attempts' ] ?? 0 );
		if ( $attempts >= QueueRecovery::MAX_RESUME_ATTEMPTS ) {
			( new RunState() )->markFailed( $scanID, ReconcileQueue::MESSAGE_TIMED_OUT );
			return true;
		}

		$now = Services::Request()->ts();
		$meta[ RunState::META_KEY_WATCHDOG_RECOVERY ] = [
			'attempts'        => $attempts + 1,
			'last_attempt_at' => $now,
		];
		$scan->meta = $meta;
		if ( !self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
			'last_process_at' => $now,
			'meta'            => $scan->getRawData()[ 'meta' ],
		] ) ) {
			error_log( \sprintf(
				'Shield scan recovery persistence failed: scan_id=%d phase=queued',
				$scanID
			) );
			return false;
		}

		return true;
	}

	private function hasActiveScans() :bool {
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT 1
					FROM `%s`
					WHERE `finished_at`=0
					  AND `status` IN (%s)
					LIMIT 1;",
				self::con()->db_con->scans->getTable(),
				ScanStatus::sqlList( ScanStatus::ACTIVE )
			)
		) === 1;
	}

	/**
	 * @param list<ActiveScanStatus> $statuses
	 * @param list<string> $columns
	 * @return list<ScansDB\Record>
	 */
	private function staleScans( int $cutoff, array $statuses, array $columns = [ 'id' ] ) :array {
		return $this->recordsFromRows( Services::WpDb()->selectCustom(
			sprintf( "SELECT `%s`
					FROM `%s`
					WHERE `finished_at`=0
					  AND %s
					ORDER BY `created_at` ASC, `id` ASC;",
				\implode( '`, `', $columns ),
				self::con()->db_con->scans->getTable(),
				$this->staleActiveWhere( $cutoff, $statuses )
			)
		) ?: [] );
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<ScansDB\Record>
	 */
	private function recordsFromRows( array $rows ) :array {
		return \array_map(
			static fn( array $row ) :ScansDB\Record => new ScansDB\Record( $row ),
			$rows
		);
	}

	/**
	 * @param list<string> $slugs
	 * @return array<string,int>
	 */
	private function staleStartBlockerIDsBySlug( array $slugs, string $scopeType, string $scopeKey ) :array {
		$slugs = $this->normalizeSlugs( $slugs );
		if ( empty( $slugs ) ) {
			return [];
		}

		$rows = Services::WpDb()->selectCustom(
			sprintf( "SELECT `id`, `scan`
					FROM `%s`
					WHERE `finished_at`=0
					  AND `scan` IN (%s)
					  AND `scope_type`='%s'
					  AND `scope_key`='%s'
					  AND %s
					ORDER BY `created_at` ASC, `id` ASC;",
				self::con()->db_con->scans->getTable(),
				$this->sqlStringList( $slugs ),
				esc_sql( $scopeType ),
				esc_sql( $scopeKey ),
				$this->staleActiveWhere( $this->cutoff() )
			)
		) ?: [];

		$blockers = [];
		foreach ( $rows as $row ) {
			$scan = (string)( $row[ 'scan' ] ?? '' );
			$id = (int)( $row[ 'id' ] ?? 0 );
			if ( $scan !== '' && $id > 0 && !isset( $blockers[ $scan ] ) ) {
				$blockers[ $scan ] = $id;
			}
		}
		return $blockers;
	}

	private function claimStaleActiveScan( int $scanID ) :bool {
		$now = Services::Request()->ts();
		$table = self::con()->db_con->scans->getTable();
		return Services::WpDb()->doSql(
			sprintf( "UPDATE `%s`
					SET `last_process_at`=%d
					WHERE `id`=%d
					  AND `finished_at`=0
					  AND %s
					  AND `id`=(
						SELECT `active_head`.`id`
						FROM (
							SELECT `candidate`.`id`
							FROM `%s` AS `candidate`
							WHERE `candidate`.`finished_at`=0
							  AND `candidate`.`status` IN (%s)
							ORDER BY CASE WHEN `candidate`.`status` IN (%s) THEN 0 ELSE 1 END ASC,
									 `candidate`.`created_at` ASC,
									 `candidate`.`id` ASC
							LIMIT 1
						) AS `active_head`
					  );",
				$table,
				$now,
				$scanID,
				$this->staleActiveWhere( $now - self::STALE_AFTER ),
				$table,
				ScanStatus::sqlList( ScanStatus::ACTIVE ),
				ScanStatus::sqlList( ScanStatus::CURRENT )
			)
		) === 1;
	}

	/** @param list<ActiveScanStatus> $statuses */
	private function staleActiveWhere( int $cutoff, array $statuses = [] ) :string {
		$statuses = empty( $statuses ) ? ScanStatus::ACTIVE : $statuses;
		$clauses = [];
		foreach ( $statuses as $status ) {
			$rule = self::STALE_STATUS_RULES[ $status ];
			$clauses[] = sprintf( "( `status`='%s'%s AND %s )",
				$status,
				$rule[ 'requires_ready' ] ? ' AND `ready_at`>0' : '',
				$this->staleTimestampWhere( $rule[ 'fallback_column' ], $cutoff )
			);
		}
		return '( '.\implode( ' OR ', $clauses ).' )';
	}

	private function staleTimestampWhere( string $fallbackColumn, int $cutoff ) :string {
		return sprintf(
			"( ( `last_process_at`>0 AND `last_process_at`<%d ) OR ( `last_process_at`=0 AND `%s`<%d ) )",
			$cutoff,
			$fallbackColumn,
			$cutoff
		);
	}

	/** @param list<string> $values */
	private function sqlStringList( array $values ) :string {
		return "'".\implode( "','", \array_map( 'esc_sql', $values ) )."'";
	}

	/**
	 * @param list<mixed> $slugs
	 * @return list<string>
	 */
	private function normalizeSlugs( array $slugs ) :array {
		$normalized = [];
		foreach ( $slugs as $slug ) {
			$slug = \is_string( $slug ) ? trim( $slug ) : '';
			if ( $slug !== '' && !\in_array( $slug, $normalized, true ) ) {
				$normalized[] = $slug;
			}
		}
		return $normalized;
	}

	private function cutoff() :int {
		return Services::Request()->ts() - self::STALE_AFTER;
	}

	private function clearScheduled() :void {
		$timestamp = wp_next_scheduled( $this->hook() );
		if ( \is_numeric( $timestamp ) ) {
			wp_unschedule_event( (int)$timestamp, $this->hook() );
		}
	}
}
