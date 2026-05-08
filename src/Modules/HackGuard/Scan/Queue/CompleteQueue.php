<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CompleteQueue {

	use PluginControllerConsumer;

	public function complete() {
		$con = self::con();
		$reconcileQueue = new ReconcileQueue();
		$reconcileQueue->completeReadyScansWithOnlyFinishedItems();

		/** @var ScanItemsDB\Delete $deleter */
		$deleter = $con->db_con->scan_items->getQueryDeleter();
		$deleter->filterByFinished()->query();

		$reconcileQueue->failReadyScansWithNoItems( ReconcileQueue::MESSAGE_ORPHANED_QUEUE );

		$activeCounts = $this->activeStatusCounts();
		if ( \array_sum( $activeCounts ) > 0 ) {
			if ( ( $activeCounts[ 'queued' ] ?? 0 ) > 0 ) {
				$con->comps->scans_queue->getQueueBuilder()->dispatch();
			}
			return;
		}

		if ( $con->opts->optGet( 'is_scan_cron' ) && !wp_next_scheduled( $con->prefix( 'post_scan' ) ) ) {
			wp_schedule_single_event( Services::Request()->ts() + 5, $con->prefix( 'post_scan' ) );
		}

		do_action( 'shield/scan_queue_completed' );

		self::con()->opts->optSet( 'is_scan_cron', false );
	}

	private function activeStatusCounts() :array {
		$counts = [];
		foreach ( Services::WpDb()->selectCustom(
			sprintf( "SELECT `status`, COUNT(*) as `count`
						FROM `%s`
						WHERE `finished_at`=0
						  AND `status` IN ('queued','building','built','running')
						GROUP BY `status`;",
				self::con()->db_con->scans->getTable()
			)
		) ?: [] as $row ) {
			$status = (string)( \is_array( $row ) ? ( $row[ 'status' ] ?? '' ) : ( $row->status ?? '' ) );
			if ( $status !== '' ) {
				$counts[ $status ] = (int)( \is_array( $row ) ? ( $row[ 'count' ] ?? 0 ) : ( $row->count ?? 0 ) );
			}
		}
		return $counts;
	}
}
