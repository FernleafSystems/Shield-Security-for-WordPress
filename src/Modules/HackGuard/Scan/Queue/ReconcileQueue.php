<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ReconcileQueue {

	use PluginControllerConsumer;

	public const MESSAGE_ORPHANED_QUEUE = 'Scan queue was empty before the scan could finish.';
	public const MESSAGE_TIMED_OUT = 'Scan timed out before it could finish.';

	public function completeReadyScansWithOnlyFinishedItems() :void {
		foreach ( $this->readyScanIDsWithOnlyFinishedItems() as $scanID ) {
			( new SetScanCompleted() )->run( $scanID );
		}
	}

	public function failReadyScansWithNoItems( string $message ) :void {
		$runState = new RunState();
		foreach ( $this->readyScanIDsWithNoItems() as $scanID ) {
			$runState->markFailed( $scanID, $message );
		}
	}

	public function reconcileReadyScan( ScansDB\Record $scan ) :?bool {
		$scanID = $scan->id;
		if ( $scanID < 1 ) {
			return null;
		}

		$counts = Services::WpDb()->selectRow(
			sprintf( "SELECT COUNT(*) AS `total`,
						 SUM(CASE WHEN `finished_at`=0 THEN 1 ELSE 0 END) AS `unfinished`
					FROM `%s`
					WHERE `scan_ref`=%d;",
				self::con()->db_con->scan_items->getTable(),
				$scanID
			)
		);
		if ( !\is_array( $counts ) ) {
			return null;
		}

		$total = (int)$counts[ 'total' ];
		$unfinished = (int)$counts[ 'unfinished' ];
		if ( $total < 1 ) {
			( new RunState() )->markFailed( $scanID, self::MESSAGE_ORPHANED_QUEUE );
			return true;
		}
		if ( $unfinished < 1 ) {
			( new SetScanCompleted() )->run( $scanID, $scan );
			return true;
		}

		return false;
	}

	private function readyScanIDsWithOnlyFinishedItems() :array {
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
				$this->readyWhere(),
				self::con()->db_con->scan_items->getTable(),
				self::con()->db_con->scan_items->getTable()
			)
		) ?: [] );
	}

	private function readyScanIDsWithNoItems() :array {
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
				$this->readyWhere(),
				self::con()->db_con->scan_items->getTable()
			)
		) ?: [] );
	}

	private function readyWhere() :string {
		return sprintf( "( `scans`.`status` IN (%s) AND `scans`.`ready_at`>0 )",
			ScanStatus::sqlList( ScanStatus::READY )
		);
	}

	/**
	 * @param list<array{id:int|string}> $rows
	 * @return list<int>
	 */
	private function idsFromRows( array $rows ) :array {
		return \array_values( \array_unique( \array_filter( \array_map(
			static fn( array $row ) :int => (int)$row[ 'id' ],
			$rows
		) ) ) );
	}
}
