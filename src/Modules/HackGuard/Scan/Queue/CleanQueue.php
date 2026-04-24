<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanQueue {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		$this->resetStaleScanItems();
		$this->resolveStaleScans();
	}

	private function resetStaleScanItems() {
		Services::WpDb()->doSql(
			sprintf( "UPDATE `%s` SET `started_at`=0 WHERE `finished_at`=0 AND `started_at` > 0 AND `started_at` < %s",
				self::con()->db_con->scan_items->getTable(),
				Services::Request()->carbon()->subMinutes( 2 )->timestamp
			)
		);
	}

	private function resolveStaleScans() {
		$this->resolveStaleScansForTime();
	}

	/**
	 * Stale scans are resolved according to their current lifecycle state.
	 */
	private function resolveStaleScansForTime() {
		$cutoff = Services::Request()->carbon()->subMinutes( 9 )->timestamp;

		foreach ( $this->staleBuildingScanIDs( $cutoff ) as $scanID ) {
			if ( $scanID > 0 ) {
				$this->markTimedOut( $scanID );
			}
		}

		foreach ( $this->staleReadyScanIDsWithNoItems( $cutoff ) as $scanID ) {
			if ( $scanID > 0 ) {
				$this->markTimedOut( $scanID );
			}
		}

		foreach ( $this->staleReadyScanIDsWithOnlyFinishedItems( $cutoff ) as $scanID ) {
			if ( $scanID > 0 ) {
				( new SetScanCompleted() )->run( $scanID );
			}
		}
	}

	private function markTimedOut( int $scanID ) :void {
		( new RunState() )->markFailed( $scanID, 'Scan timed out before it could finish.' );
	}

	private function staleBuildingScanIDs( int $cutoff ) :array {
		return $this->idsFromRows( Services::WpDb()->selectCustom(
			sprintf( "SELECT DISTINCT `scans`.`id`
						FROM `%s` as `scans`
						WHERE `scans`.`status`='building'
						  AND `scans`.`finished_at`=0
						  AND `scans`.`last_process_at`<%d;",
				self::con()->db_con->scans->getTable(),
				$cutoff
			)
		) ?: [] );
	}

	private function staleReadyScanIDsWithNoItems( int $cutoff ) :array {
		return $this->idsFromRows( Services::WpDb()->selectCustom(
			sprintf( "SELECT DISTINCT `scans`.`id`
						FROM `%s` as `scans`
						WHERE `scans`.`finished_at`=0
						  AND %s
						  AND NOT EXISTS (
							SELECT 1
							FROM `%s` as `si`
							WHERE `si`.`scan_ref`=`scans`.`id`
						  );",
				self::con()->db_con->scans->getTable(),
				$this->staleReadyWhere( $cutoff ),
				self::con()->db_con->scan_items->getTable()
			)
		) ?: [] );
	}

	private function staleReadyScanIDsWithOnlyFinishedItems( int $cutoff ) :array {
		return $this->idsFromRows( Services::WpDb()->selectCustom(
			sprintf( "SELECT DISTINCT `scans`.`id`
						FROM `%s` as `scans`
						WHERE `scans`.`finished_at`=0
						  AND %s
						  AND EXISTS (
							SELECT 1
							FROM `%s` as `si_any`
							WHERE `si_any`.`scan_ref`=`scans`.`id`
						  )
						  AND NOT EXISTS (
							SELECT 1
							FROM `%s` as `si_unfinished`
							WHERE `si_unfinished`.`scan_ref`=`scans`.`id`
							  AND `si_unfinished`.`finished_at`=0
						  );",
				self::con()->db_con->scans->getTable(),
				$this->staleReadyWhere( $cutoff ),
				self::con()->db_con->scan_items->getTable(),
				self::con()->db_con->scan_items->getTable()
			)
		) ?: [] );
	}

	private function staleReadyWhere( int $cutoff ) :string {
		return sprintf( "(
							( `scans`.`status`='built' AND ( `scans`.`ready_at`<%d OR `scans`.`last_process_at`<%d ) )
							OR ( `scans`.`status`='running' AND `scans`.`last_process_at`<%d )
						)",
			$cutoff,
			$cutoff,
			$cutoff
		);
	}

	private function idsFromRows( array $rows ) :array {
		return \array_values( \array_unique( \array_filter( \array_map(
			static fn( $row ) :int => (int)( \is_array( $row ) ? ( $row[ 'id' ] ?? 0 ) : ( $row->id ?? 0 ) ),
			$rows
		) ) ) );
	}
}
