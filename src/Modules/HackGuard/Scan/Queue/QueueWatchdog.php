<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
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
		unset( $scan );
		self::con()->comps->scans_queue->getQueueBuilder()->dispatch();
	}

	private function hasActiveScans() :bool {
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT 1
					FROM `%s`
					WHERE `finished_at`=0
					  AND `status` IN ('queued','building','built','running')
					LIMIT 1;",
				self::con()->db_con->scans->getTable()
			)
		) === 1;
	}

	private function hasStaleActiveScans() :bool {
		$cutoff = $this->cutoff();
		return (int)Services::WpDb()->getVar(
			sprintf( "SELECT 1
					FROM `%s`
					WHERE `finished_at`=0
					  AND (
						( `status`='queued' AND `created_at`<%d )
						OR ( `status`='building' AND (
							( `last_process_at`>0 AND `last_process_at`<%d )
							OR ( `last_process_at`=0 AND `created_at`<%d )
						) )
						OR ( `status`='built' AND `ready_at`>0 AND `ready_at`<%d )
						OR ( `status`='running' AND (
							( `last_process_at`>0 AND `last_process_at`<%d )
							OR ( `last_process_at`=0 AND `created_at`<%d )
						) )
					  )
					LIMIT 1;",
				self::con()->db_con->scans->getTable(),
				$cutoff,
				$cutoff,
				$cutoff,
				$cutoff,
				$cutoff,
				$cutoff
			)
		) === 1;
	}

	/**
	 * @return ScansDB\Record[]
	 */
	private function staleQueuedScans( int $cutoff ) :array {
		return self::con()->db_con->scans->getQuerySelector()
				   ->filterByStatus( 'queued' )
				   ->filterByNotFinished()
				   ->addWhereOlderThan( $cutoff, 'created_at' )
				   ->queryWithResult();
	}

	/**
	 * @return ScansDB\Record[]
	 */
	private function staleReadyScans( int $cutoff ) :array {
		$rows = Services::WpDb()->selectCustom(
			sprintf( "SELECT *
					FROM `%s`
					WHERE `finished_at`=0
					  AND (
						( `status`='built' AND `ready_at`>0 AND `ready_at`<%d )
						OR ( `status`='running' AND (
							( `last_process_at`>0 AND `last_process_at`<%d )
							OR ( `last_process_at`=0 AND `created_at`<%d )
						) )
					  )
					ORDER BY `created_at` ASC, `id` ASC;",
				self::con()->db_con->scans->getTable(),
				$cutoff,
				$cutoff,
				$cutoff
			)
		) ?: [];

		return \array_map(
			static fn( array $row ) :ScansDB\Record => new ScansDB\Record( $row ),
			\array_filter( $rows, 'is_array' )
		);
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
