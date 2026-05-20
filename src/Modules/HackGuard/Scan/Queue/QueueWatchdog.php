<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class QueueWatchdog {

	use PluginControllerConsumer;

	public const STALE_AFTER = 180;
	public const CHECK_INTERVAL = 60;

	public function register() :void {
		add_action( $this->hook(), [ $this, 'runScheduled' ] );
	}

	public function runScheduled() :void {
		$this->run();
		$this->scheduleIfActive();
	}

	public function runIfStale() :void {
		if ( $this->hasStaleActiveScans() ) {
			$this->run();
			$this->scheduleIfActive();
		}
	}

	/**
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
		$cutoff = $this->cutoff();

		$maintenance = new QueueMaintenance();
		$maintenance->run();
		$maintenance->failStaleBuildingScans( $cutoff );

		foreach ( $this->staleQueuedScans( $cutoff ) as $scan ) {
			$this->recoverQueuedScan( $scan );
		}

		$recovery = new QueueRecovery();
		foreach ( $this->staleReadyScans( $cutoff ) as $scan ) {
			$recovery->recoverReadyScan( $scan );
		}

		$maintenance->run();
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

	private function recoverQueuedScan( ScansDB\Record $scan ) :void {
		$this->touchLastProcessAt( (int)$scan->id );
		self::con()->comps->scans_queue->getQueueBuilder()->dispatch();
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

	private function hasStaleActiveScans() :bool {
		$cutoff = $this->cutoff();
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT 1
					FROM `%s`
					WHERE `finished_at`=0
					  AND %s
					LIMIT 1;",
				self::con()->db_con->scans->getTable(),
				$this->staleActiveWhere( $cutoff )
			)
		) === 1;
	}

	/**
	 * @return ScansDB\Record[]
	 */
	private function staleQueuedScans( int $cutoff ) :array {
		return $this->recordsFromRows( Services::WpDb()->selectCustom(
			sprintf( "SELECT `id`
					FROM `%s`
					WHERE `finished_at`=0
					  AND %s
					ORDER BY `created_at` ASC, `id` ASC;",
				self::con()->db_con->scans->getTable(),
				$this->staleActiveWhere( $cutoff, [ ScanStatus::QUEUED ] )
			)
		) ?: [] );
	}

	/**
	 * @return ScansDB\Record[]
	 */
	private function staleReadyScans( int $cutoff ) :array {
		return $this->recordsFromRows( Services::WpDb()->selectCustom(
			sprintf( "SELECT `id`, `meta`, `created_at`
					FROM `%s`
					WHERE `finished_at`=0
					  AND %s
					ORDER BY `created_at` ASC, `id` ASC;",
				self::con()->db_con->scans->getTable(),
				$this->staleActiveWhere( $cutoff, ScanStatus::READY )
			)
		) ?: [] );
	}

	/**
	 * @return ScansDB\Record[]
	 */
	private function recordsFromRows( array $rows ) :array {
		return \array_map(
			static fn( array $row ) :ScansDB\Record => new ScansDB\Record( $row ),
			\array_filter( $rows, 'is_array' )
		);
	}

	/**
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
			$scan = (string)( \is_array( $row ) ? ( $row[ 'scan' ] ?? '' ) : ( $row->scan ?? '' ) );
			$id = (int)( \is_array( $row ) ? ( $row[ 'id' ] ?? 0 ) : ( $row->id ?? 0 ) );
			if ( $scan !== '' && $id > 0 && !isset( $blockers[ $scan ] ) ) {
				$blockers[ $scan ] = $id;
			}
		}
		return $blockers;
	}

	private function touchLastProcessAt( int $scanID ) :void {
		if ( $scanID > 0 ) {
			self::con()->db_con->scans->getQueryUpdater()->updateById( $scanID, [
				'last_process_at' => Services::Request()->ts(),
			] );
		}
	}

	private function staleActiveWhere( int $cutoff, array $statuses = [] ) :string {
		$statuses = empty( $statuses )
			? ScanStatus::ACTIVE
			: \array_values( \array_intersect( ScanStatus::ACTIVE, $statuses ) );
		$clauses = [];
		if ( \in_array( ScanStatus::QUEUED, $statuses, true ) ) {
			$clauses[] = sprintf( "( `status`='%s' AND %s )",
				ScanStatus::QUEUED,
				$this->staleTimestampWhere( 'last_process_at', 'created_at', $cutoff )
			);
		}
		if ( \in_array( ScanStatus::BUILDING, $statuses, true ) ) {
			$clauses[] = sprintf( "( `status`='%s' AND %s )",
				ScanStatus::BUILDING,
				$this->staleTimestampWhere( 'last_process_at', 'created_at', $cutoff )
			);
		}
		if ( \in_array( ScanStatus::BUILT, $statuses, true ) ) {
			$clauses[] = sprintf( "( `status`='%s' AND `ready_at`>0 AND %s )",
				ScanStatus::BUILT,
				$this->staleTimestampWhere( 'last_process_at', 'ready_at', $cutoff )
			);
		}
		if ( \in_array( ScanStatus::RUNNING, $statuses, true ) ) {
			$clauses[] = sprintf( "( `status`='%s' AND `ready_at`>0 AND %s )",
				ScanStatus::RUNNING,
				$this->staleTimestampWhere( 'last_process_at', 'created_at', $cutoff )
			);
		}
		return empty( $clauses ) ? '0=1' : '( '.\implode( ' OR ', $clauses ).' )';
	}

	private function staleTimestampWhere( string $lastProcessColumn, string $fallbackColumn, int $cutoff ) :string {
		return sprintf(
			"( ( `%s`>0 AND `%s`<%d ) OR ( `%s`=0 AND `%s`<%d ) )",
			$lastProcessColumn,
			$lastProcessColumn,
			$cutoff,
			$lastProcessColumn,
			$fallbackColumn,
			$cutoff
		);
	}

	private function sqlStringList( array $values ) :string {
		return "'".\implode( "','", \array_map( 'esc_sql', $values ) )."'";
	}

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
