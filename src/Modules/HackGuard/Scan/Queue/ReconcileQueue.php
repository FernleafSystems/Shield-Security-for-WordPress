<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\SetScanCompleted;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ReconcileQueue {

	use PluginControllerConsumer;

	public const MESSAGE_ORPHANED_QUEUE = 'Scan queue was empty before the scan could finish.';
	public const MESSAGE_TIMED_OUT = 'Scan timed out before it could finish.';

	public function completeReadyScansWithOnlyFinishedItems( ?int $cutoff = null ) :void {
		foreach ( $this->readyScanIDsWithOnlyFinishedItems( $cutoff ) as $scanID ) {
			( new SetScanCompleted() )->run( $scanID );
		}
	}

	public function failReadyScansWithNoItems( string $message, ?int $cutoff = null ) :void {
		$runState = new RunState();
		foreach ( $this->readyScanIDsWithNoItems( $cutoff ) as $scanID ) {
			$runState->markFailed( $scanID, $message );
		}
	}

	public function failBuildingScansOlderThan( int $cutoff, string $message ) :void {
		$runState = new RunState();
		foreach ( $this->buildingScanIDsOlderThan( $cutoff ) as $scanID ) {
			$runState->markFailed( $scanID, $message );
		}
	}

	private function readyScanIDsWithOnlyFinishedItems( ?int $cutoff ) :array {
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
				$this->readyWhere( $cutoff ),
				self::con()->db_con->scan_items->getTable(),
				self::con()->db_con->scan_items->getTable()
			)
		) ?: [] );
	}

	private function readyScanIDsWithNoItems( ?int $cutoff ) :array {
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
				$this->readyWhere( $cutoff ),
				self::con()->db_con->scan_items->getTable()
			)
		) ?: [] );
	}

	private function buildingScanIDsOlderThan( int $cutoff ) :array {
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

	private function readyWhere( ?int $cutoff ) :string {
		return \is_int( $cutoff )
			? sprintf( "(
							( `scans`.`status`='built' AND ( `scans`.`ready_at`<%d OR `scans`.`last_process_at`<%d ) )
							OR ( `scans`.`status`='running' AND `scans`.`last_process_at`<%d )
						)",
				$cutoff,
				$cutoff,
				$cutoff
			)
			: "( `scans`.`status` IN ('built','running') AND `scans`.`ready_at`>0 )";
	}

	private function idsFromRows( array $rows ) :array {
		return \array_values( \array_unique( \array_filter( \array_map(
			static fn( $row ) :int => (int)( \is_array( $row ) ? ( $row[ 'id' ] ?? 0 ) : ( $row->id ?? 0 ) ),
			$rows
		) ) ) );
	}
}
