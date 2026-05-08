<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
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
		$reconcileQueue = new ReconcileQueue();

		$reconcileQueue->failBuildingScansOlderThan( $cutoff, ReconcileQueue::MESSAGE_TIMED_OUT );
		$reconcileQueue->failReadyScansWithNoItems( ReconcileQueue::MESSAGE_TIMED_OUT, $cutoff );
		$reconcileQueue->completeReadyScansWithOnlyFinishedItems( $cutoff );
	}
}
