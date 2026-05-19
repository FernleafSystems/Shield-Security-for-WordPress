<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

class QueueMaintenance {

	public function run() :void {
		$reconcile = new ReconcileQueue();
		$reconcile->completeReadyScansWithOnlyFinishedItems();
		$reconcile->failReadyScansWithNoItems( ReconcileQueue::MESSAGE_ORPHANED_QUEUE );
	}

	public function failStaleBuildingScans( int $cutoff ) :void {
		( new ReconcileQueue() )->failBuildingScansOlderThan( $cutoff, ReconcileQueue::MESSAGE_TIMED_OUT );
	}
}
